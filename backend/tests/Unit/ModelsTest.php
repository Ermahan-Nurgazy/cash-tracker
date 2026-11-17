<?php

namespace Tests\Unit;

use app\models\Account;
use app\models\Category;
use app\models\LoginForm;
use app\models\RegisterForm;
use app\models\Transaction;
use app\models\User;
use Codeception\Test\Unit;
use Tests\Support\UnitTester;

/**
 * Валидация и правила моделей. Каждый тест выполняется в транзакции, которая откатывается.
 */
class ModelsTest extends Unit
{
    protected UnitTester $tester;

    private function makeUser(?string $email = null): User
    {
        $form = new RegisterForm([
            'email' => $email ?? uniqid('u', true) . '@example.com',
            'password' => 'secret123',
        ]);
        $this->assertTrue($form->validate());

        return $form->register();
    }

    private function makeAccount(User $user, string $currency = 'KZT'): Account
    {
        $account = new Account(['name' => 'Счёт ' . uniqid(), 'currency' => $currency]);
        $account->user_id = $user->id;
        $this->assertTrue($account->save());

        return $account;
    }

    private function makeCategory(User $user, string $type = 'expense'): Category
    {
        $category = new Category(['name' => 'Кат ' . uniqid(), 'type' => $type]);
        $category->user_id = $user->id;
        $this->assertTrue($category->save());

        return $category;
    }

    private function newTransaction(User $user, Account $account, Category $category, array $attrs = []): Transaction
    {
        $tx = new Transaction($attrs + [
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => '10.00',
            'type' => $category->type,
            'occurred_at' => '2026-11-10',
        ]);
        $tx->user_id = $user->id;

        return $tx;
    }

    // --- User / формы ---

    public function testRegisterNormalizesEmailAndHashesPassword(): void
    {
        $user = $this->makeUser('MiXed@Example.COM');

        $this->assertEquals('mixed@example.com', $user->email);
        $this->assertNotEquals('secret123', $user->password_hash);
        $this->assertTrue($user->validatePassword('secret123'));
        $this->assertFalse($user->validatePassword('wrong'));
        $this->assertGreaterThanOrEqual(32, strlen($user->access_token));
    }

    public function testRegisterFormRules(): void
    {
        $this->makeUser('taken@example.com');

        $form = new RegisterForm(['email' => 'TAKEN@example.com', 'password' => 'secret123']);
        $this->assertFalse($form->validate());
        $this->assertArrayHasKey('email', $form->errors);

        $form = new RegisterForm(['email' => 'not-email', 'password' => 'short']);
        $this->assertFalse($form->validate());
        $this->assertArrayHasKey('email', $form->errors);
        $this->assertArrayHasKey('password', $form->errors);

        $form = new RegisterForm(['email' => 'a@example.com', 'password' => str_repeat('x', 73)]);
        $this->assertFalse($form->validate());
    }

    public function testLoginReissuesTokenAndRejectsBadCredentials(): void
    {
        $user = $this->makeUser('login@example.com');
        $old = $user->access_token;

        $form = new LoginForm(['email' => 'LOGIN@example.com', 'password' => 'secret123']);
        $this->assertTrue($form->validate());
        $logged = $form->authenticate();
        $this->assertNotNull($logged);
        $this->assertNotEquals($old, $logged->access_token);
        $this->assertNull(User::findIdentityByAccessToken($old));

        $this->assertNull((new LoginForm(['email' => 'login@example.com', 'password' => 'bad']))->authenticate());
        $this->assertNull((new LoginForm(['email' => 'nobody@example.com', 'password' => 'secret123']))->authenticate());
    }

    public function testUserFieldsHideSecrets(): void
    {
        $fields = array_keys($this->makeUser()->toArray());

        $this->assertNotContains('password_hash', $fields);
        $this->assertNotContains('auth_key', $fields);
        $this->assertNotContains('access_token', $fields);
    }

    // --- Account ---

    public function testAccountCurrencyNormalizedAndValidated(): void
    {
        $user = $this->makeUser();

        $account = $this->makeAccount($user, 'usd');
        $this->assertEquals('USD', $account->currency);

        $bad = new Account(['name' => 'X', 'currency' => 'TENGE']);
        $bad->user_id = $user->id;
        $this->assertFalse($bad->validate());
        $this->assertArrayHasKey('currency', $bad->errors);
    }

    public function testAccountNameUniquePerUserOnly(): void
    {
        $first = $this->makeUser();
        $second = $this->makeUser();
        $account = $this->makeAccount($first);

        $dup = new Account(['name' => $account->name, 'currency' => 'KZT']);
        $dup->user_id = $first->id;
        $this->assertFalse($dup->validate());

        $other = new Account(['name' => $account->name, 'currency' => 'KZT']);
        $other->user_id = $second->id;
        $this->assertTrue($other->validate());
    }

    public function testAccountCurrencyLockedWhenTransactionsExist(): void
    {
        $user = $this->makeUser();
        $account = $this->makeAccount($user);
        $category = $this->makeCategory($user);

        $account->currency = 'USD';
        $this->assertTrue($account->validate());
        $account->refresh();

        $this->assertTrue($this->newTransaction($user, $account, $category)->save());

        $account->currency = 'USD';
        $this->assertFalse($account->validate());
        $this->assertArrayHasKey('currency', $account->errors);
    }

    public function testUserIdIsNotMassAssignable(): void
    {
        $user = $this->makeUser();
        $account = new Account();
        $account->load(['name' => 'A', 'currency' => 'KZT', 'user_id' => 12345], '');

        $this->assertNull($account->user_id);
        $this->assertEquals('A', $account->name);
        $this->assertNotEquals(12345, $user->id);
    }

    // --- Category ---

    public function testCategoryValidation(): void
    {
        $user = $this->makeUser();
        $category = $this->makeCategory($user);

        $dup = new Category(['name' => $category->name, 'type' => $category->type]);
        $dup->user_id = $user->id;
        $this->assertFalse($dup->validate());

        // То же имя, но другой тип: допустимо
        $sameNameOtherType = new Category(['name' => $category->name, 'type' => 'income']);
        $sameNameOtherType->user_id = $user->id;
        $this->assertTrue($sameNameOtherType->validate());

        $badColor = new Category(['name' => 'Ц', 'type' => 'expense', 'color' => 'red']);
        $badColor->user_id = $user->id;
        $this->assertFalse($badColor->validate());
        $this->assertArrayHasKey('color', $badColor->errors);

        $badType = new Category(['name' => 'Т', 'type' => 'transfer']);
        $badType->user_id = $user->id;
        $this->assertFalse($badType->validate());
    }

    public function testCategoryTypeLockedWhenTransactionsExist(): void
    {
        $user = $this->makeUser();
        $account = $this->makeAccount($user);
        $category = $this->makeCategory($user);

        $this->assertTrue($this->newTransaction($user, $account, $category)->save());

        $category->type = 'income';
        $this->assertFalse($category->validate());
        $this->assertArrayHasKey('type', $category->errors);
    }

    // --- Transaction ---

    public function testTransactionValid(): void
    {
        $user = $this->makeUser();
        $tx = $this->newTransaction($user, $this->makeAccount($user), $this->makeCategory($user));

        $this->assertTrue($tx->save());
        $this->assertNotNull($tx->refresh());
        $this->assertEquals('10.00', $tx->amount);
        $this->assertArrayHasKey('account', $tx->toArray());
        $this->assertArrayHasKey('category', $tx->toArray());
        $this->assertArrayNotHasKey('user_id', $tx->toArray());
    }

    /**
     * @dataProvider invalidTransactions
     */
    public function testTransactionInvalid(array $override, string $errorField): void
    {
        $user = $this->makeUser();
        $tx = $this->newTransaction($user, $this->makeAccount($user), $this->makeCategory($user), $override);

        $this->assertFalse($tx->validate());
        $this->assertArrayHasKey($errorField, $tx->errors);
    }

    public static function invalidTransactions(): array
    {
        return [
            'нулевая сумма' => [['amount' => '0'], 'amount'],
            'отрицательная сумма' => [['amount' => '-1'], 'amount'],
            'слишком большая сумма' => [['amount' => '1000000000000'], 'amount'],
            'не число' => [['amount' => 'abc'], 'amount'],
            'тип не из списка' => [['type' => 'transfer'], 'type'],
            'тип не совпадает с категорией' => [['type' => 'income'], 'type'],
            'неверный формат даты' => [['occurred_at' => '10.11.2026'], 'occurred_at'],
            'несуществующая дата' => [['occurred_at' => '2026-02-30'], 'occurred_at'],
            'слишком длинная заметка' => [['note' => str_repeat('я', 2001)], 'note'],
        ];
    }

    public function testTransactionRejectsForeignAccountAndCategory(): void
    {
        $owner = $this->makeUser();
        $stranger = $this->makeUser();
        $foreignAccount = $this->makeAccount($stranger);
        $foreignCategory = $this->makeCategory($stranger);

        $tx = $this->newTransaction($owner, $foreignAccount, $this->makeCategory($owner));
        $this->assertFalse($tx->validate());
        $this->assertArrayHasKey('account_id', $tx->errors);

        $tx = $this->newTransaction($owner, $this->makeAccount($owner), $foreignCategory);
        $this->assertFalse($tx->validate());
        $this->assertArrayHasKey('category_id', $tx->errors);
    }
}
