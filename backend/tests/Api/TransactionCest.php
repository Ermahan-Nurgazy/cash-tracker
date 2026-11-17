<?php

namespace Tests\Api;

use Tests\Support\ApiTester;

class TransactionCest extends ApiBase
{
    public function createReturnsNestedAccountAndCategory(ApiTester $I): void
    {
        $this->registerAndAuth($I);
        $account = $this->createAccount($I, 'Карта', 'KZT');
        $category = $this->createCategory($I, 'Кафе');

        $id = $this->createTransaction($I, $account, $category, '1234.5', 'expense', '2026-11-10', 'Обед');

        $I->sendGet(self::API . "/transactions/$id");
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson([
            'amount' => '1234.50',
            'type' => 'expense',
            'occurred_at' => '2026-11-10',
            'note' => 'Обед',
            'account' => ['id' => $account, 'name' => 'Карта', 'currency' => 'KZT'],
            'category' => ['id' => $category, 'name' => 'Кафе', 'type' => 'expense'],
        ]);
        $I->dontSeeResponseJsonMatchesJsonPath('$.user_id');
    }

    public function validation(ApiTester $I): void
    {
        $this->registerAndAuth($I);
        $account = $this->createAccount($I);
        $expense = $this->createCategory($I, 'Еда', 'expense');
        $base = ['account_id' => $account, 'category_id' => $expense, 'amount' => '10', 'type' => 'expense', 'occurred_at' => '2026-11-10'];

        $cases = [
            'нулевая сумма' => ['amount' => '0'],
            'отрицательная сумма' => ['amount' => '-5'],
            'не число' => ['amount' => 'abc'],
            'неверный тип' => ['type' => 'transfer'],
            'тип не совпадает с категорией' => ['type' => 'income'],
            'неверная дата' => ['occurred_at' => '10.11.2026'],
            'несуществующая дата' => ['occurred_at' => '2026-02-30'],
            'нет счёта' => ['account_id' => 999999],
            'нет категории' => ['category_id' => 999999],
        ];

        foreach ($cases as $label => $override) {
            $I->sendPost(self::API . '/transactions', array_merge($base, $override));
            $I->seeResponseCodeIs(422);
        }

        $I->sendPost(self::API . '/transactions', []);
        $I->seeResponseCodeIs(422);
    }

    public function cannotUseForeignAccountOrCategory(ApiTester $I): void
    {
        $this->registerAndAuth($I);
        $foreignAccount = $this->createAccount($I);
        $foreignCategory = $this->createCategory($I);

        $this->registerAndAuth($I);
        $account = $this->createAccount($I);
        $category = $this->createCategory($I);

        $base = ['amount' => '10', 'type' => 'expense', 'occurred_at' => '2026-11-10'];
        $I->sendPost(self::API . '/transactions', $base + ['account_id' => $foreignAccount, 'category_id' => $category]);
        $I->seeResponseCodeIs(422);
        $I->sendPost(self::API . '/transactions', $base + ['account_id' => $account, 'category_id' => $foreignCategory]);
        $I->seeResponseCodeIs(422);
    }

    public function otherUsersCannotTouchTransaction(ApiTester $I): void
    {
        $owner = $this->registerAndAuth($I);
        $id = $this->createTransaction($I, $this->createAccount($I), $this->createCategory($I));

        $this->registerAndAuth($I);
        $I->sendGet(self::API . "/transactions/$id");
        $I->seeResponseCodeIs(404);
        $I->sendPatch(self::API . "/transactions/$id", ['amount' => '1']);
        $I->seeResponseCodeIs(404);
        $I->sendDelete(self::API . "/transactions/$id");
        $I->seeResponseCodeIs(404);
        $I->sendGet(self::API . '/transactions');
        $I->assertCount(0, $I->grabDataFromResponseByJsonPath('$[*]'));

        $this->useToken($I, $owner);
        $I->sendGet(self::API . "/transactions/$id");
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['amount' => '100.00']);
    }

    public function updateAndDelete(ApiTester $I): void
    {
        $this->registerAndAuth($I);
        $account = $this->createAccount($I);
        $category = $this->createCategory($I);
        $id = $this->createTransaction($I, $account, $category);

        $I->sendPatch(self::API . "/transactions/$id", ['amount' => '55.55', 'note' => 'Правка']);
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['amount' => '55.55', 'note' => 'Правка']);

        // Смена категории на доходную без смены типа запрещена
        $income = $this->createCategory($I, 'Зарплата', 'income');
        $I->sendPatch(self::API . "/transactions/$id", ['category_id' => $income]);
        $I->seeResponseCodeIs(422);
        $I->sendPatch(self::API . "/transactions/$id", ['category_id' => $income, 'type' => 'income']);
        $I->seeResponseCodeIs(200);

        $I->sendDelete(self::API . "/transactions/$id");
        $I->seeResponseCodeIs(204);
        $I->sendGet(self::API . "/transactions/$id");
        $I->seeResponseCodeIs(404);
    }

    public function filters(ApiTester $I): void
    {
        $this->registerAndAuth($I);
        $cash = $this->createAccount($I, 'Наличные');
        $card = $this->createAccount($I, 'Карта');
        $food = $this->createCategory($I, 'Еда');
        $salary = $this->createCategory($I, 'Зарплата', 'income');

        $this->createTransaction($I, $cash, $food, '10', 'expense', '2026-10-01', 'Молоко 100%');
        $this->createTransaction($I, $cash, $food, '20', 'expense', '2026-10-15', 'Хлеб');
        $this->createTransaction($I, $card, $food, '30', 'expense', '2026-11-01', 'Сыр');
        $this->createTransaction($I, $card, $salary, '500', 'income', '2026-11-05', 'Аванс');

        $count = function (string $query) use ($I): int {
            $I->sendGet(self::API . '/transactions' . $query);
            $I->seeResponseCodeIs(200);

            return count($I->grabDataFromResponseByJsonPath('$[*]'));
        };

        $I->assertSame(4, $count(''));
        $I->assertSame(2, $count('?date_from=2026-10-10&date_to=2026-11-01'));
        $I->assertSame(1, $count('?date_from=2026-11-01&date_to=2026-11-01&type=expense'));
        $I->assertSame(2, $count("?account_id=$cash"));
        $I->assertSame(1, $count("?category_id=$salary"));
        $I->assertSame(1, $count('?type=income'));
        $I->assertSame(1, $count('?q=хлеб'));
        $I->assertSame(1, $count('?q=' . urlencode('100%')));
        $I->assertSame(1, $count('?q=' . urlencode('%')));
        $I->assertSame(2, $count("?account_id=$card&date_from=2026-11-01"));
    }

    public function invalidFiltersGive400(ApiTester $I): void
    {
        $this->registerAndAuth($I);

        foreach (['date_from=2026-13-01', 'date_to=bad', 'date_from=2026-02-01&date_to=2026-01-01',
                     'account_id=abc', 'category_id=-1', 'type=bad', 'type[]=x', 'q=' . str_repeat('я', 101)] as $query) {
            $I->sendGet(self::API . '/transactions?' . $query);
            $I->seeResponseCodeIs(400);
        }
    }

    public function sortingIsStableAcrossPages(ApiTester $I): void
    {
        $this->registerAndAuth($I);
        $account = $this->createAccount($I);
        $category = $this->createCategory($I);

        for ($i = 0; $i < 5; $i++) {
            $this->createTransaction($I, $account, $category, '10', 'expense', '2026-11-10');
        }

        $seen = [];
        for ($page = 1; $page <= 3; $page++) {
            $I->sendGet(self::API . "/transactions?per-page=2&page=$page");
            $seen = array_merge($seen, $I->grabDataFromResponseByJsonPath('$[*].id'));
        }

        $I->assertCount(5, $seen);
        $I->assertCount(5, array_unique($seen));

        $I->sendGet(self::API . '/transactions?sort=amount');
        $I->seeResponseCodeIs(200);
    }
}
