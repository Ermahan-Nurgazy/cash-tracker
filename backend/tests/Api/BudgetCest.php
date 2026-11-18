<?php

namespace Tests\Api;

use Tests\Support\ApiTester;

class BudgetCest extends ApiBase
{
    private function createBudget(ApiTester $I, int $category, string $limit = '1000', string $month = '2026-11', string $currency = 'KZT'): int
    {
        return $this->create($I, 'budgets', [
            'category_id' => $category,
            'month' => $month,
            'currency' => $currency,
            'limit' => $limit,
        ]);
    }

    public function createReturnsSpentAndStatus(ApiTester $I): void
    {
        $this->registerAndAuth($I);
        $kzt = $this->createAccount($I, 'K', 'KZT');
        $usd = $this->createAccount($I, 'U', 'USD');
        $food = $this->createCategory($I, 'Еда');

        $this->createTransaction($I, $kzt, $food, '300.25', 'expense', '2026-11-05');
        $this->createTransaction($I, $kzt, $food, '200.00', 'expense', '2026-11-30');
        // Не попадает: другой месяц и другая валюта
        $this->createTransaction($I, $kzt, $food, '999.00', 'expense', '2026-10-31');
        $this->createTransaction($I, $usd, $food, '50.00', 'expense', '2026-11-10');

        $id = $this->createBudget($I, $food, '1000');

        $I->sendGet(self::API . "/budgets/$id");
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson([
            'month' => '2026-11',
            'currency' => 'KZT',
            'limit' => '1000.00',
            'spent' => '500.25',
            'remaining' => '499.75',
            'percent' => 50,
            'status' => 'ok',
            'category' => ['id' => $food, 'name' => 'Еда'],
        ]);
        $I->dontSeeResponseJsonMatchesJsonPath('$.user_id');
        $I->dontSeeResponseJsonMatchesJsonPath('$.period_month');
    }

    public function statusesFollowSpending(ApiTester $I): void
    {
        $this->registerAndAuth($I);
        $account = $this->createAccount($I);
        $warn = $this->createCategory($I, 'Почти');
        $over = $this->createCategory($I, 'Перебор');
        $exact = $this->createCategory($I, 'Ровно');

        $this->createTransaction($I, $account, $warn, '80.00', 'expense', '2026-11-02');
        $this->createTransaction($I, $account, $over, '150.50', 'expense', '2026-11-02');
        $this->createTransaction($I, $account, $exact, '100.00', 'expense', '2026-11-02');
        $this->createBudget($I, $warn, '100');
        $this->createBudget($I, $over, '100');
        $this->createBudget($I, $exact, '100');

        $I->sendGet(self::API . '/budgets?month=2026-11&sort=id');
        $I->assertSame(['warning', 'exceeded', 'warning'], $I->grabDataFromResponseByJsonPath('$[*].status'));
        $I->assertSame(['80.00', '150.50', '100.00'], $I->grabDataFromResponseByJsonPath('$[*].spent'));
        $I->assertSame(['-50.50'], [$I->grabDataFromResponseByJsonPath('$[1].remaining')[0]]);
        $I->assertSame([151], [$I->grabDataFromResponseByJsonPath('$[1].percent')[0]]);
    }

    public function incomeAndIncomeCategoriesDoNotCount(ApiTester $I): void
    {
        $this->registerAndAuth($I);
        $account = $this->createAccount($I);
        $income = $this->createCategory($I, 'Зарплата', 'income');

        $I->sendPost(self::API . '/budgets', ['category_id' => $income, 'month' => '2026-11', 'currency' => 'KZT', 'limit' => '10']);
        $I->seeResponseCodeIs(422);
        $I->seeResponseContainsJson(['errors' => ['category_id' => 'Расходная категория не найдена.']]);
        unset($account);
    }

    public function validationAndUniqueness(ApiTester $I): void
    {
        $this->registerAndAuth($I);
        $food = $this->createCategory($I, 'Еда');
        $this->createBudget($I, $food);

        $base = ['category_id' => $food, 'month' => '2026-11', 'currency' => 'KZT', 'limit' => '10'];
        $cases = [
            'дубль' => ['month' => '2026-11'],
            'месяц 13' => ['month' => '2026-13'],
            'месяц с днём' => ['month' => '2026-11-15'],
            'нулевой лимит' => ['limit' => '0'],
            'отрицательный лимит' => ['limit' => '-1'],
            'валюта' => ['currency' => 'тенге'],
            'нет категории' => ['category_id' => 999999],
        ];

        foreach ($cases as $name => $override) {
            $I->sendPost(self::API . '/budgets', array_merge($base, $override));
            $I->seeResponseCodeIs(422, $name);
        }

        $I->sendPost(self::API . '/budgets', $base);
        $I->seeResponseContainsJson(['errors' => ['category_id' => 'Бюджет на эту категорию, месяц и валюту уже есть.']]);

        // Другой месяц и другая валюта допустимы
        $I->sendPost(self::API . '/budgets', array_merge($base, ['month' => '2026-12']));
        $I->seeResponseCodeIs(201);
        $I->sendPost(self::API . '/budgets', array_merge($base, ['currency' => 'usd']));
        $I->seeResponseCodeIs(201);
        $I->seeResponseContainsJson(['currency' => 'USD']);

        $I->sendPost(self::API . '/budgets', []);
        $I->seeResponseCodeIs(422);
    }

    public function updateAndDelete(ApiTester $I): void
    {
        $this->registerAndAuth($I);
        $food = $this->createCategory($I, 'Еда');
        $id = $this->createBudget($I, $food, '1000');

        $I->sendPatch(self::API . "/budgets/$id", ['limit' => '2500.5']);
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['limit' => '2500.50', 'month' => '2026-11']);

        $I->sendPatch(self::API . "/budgets/$id", ['month' => '2027-01']);
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['month' => '2027-01']);

        $I->sendDelete(self::API . "/budgets/$id");
        $I->seeResponseCodeIs(204);
        $I->sendGet(self::API . "/budgets/$id");
        $I->seeResponseCodeIs(404);
    }

    public function listFilters(ApiTester $I): void
    {
        $this->registerAndAuth($I);
        $food = $this->createCategory($I, 'Еда');
        $taxi = $this->createCategory($I, 'Такси');
        $this->createBudget($I, $food, '100', '2026-11');
        $this->createBudget($I, $food, '100', '2026-12');
        $this->createBudget($I, $taxi, '100', '2026-11', 'USD');

        $I->sendGet(self::API . '/budgets?month=2026-11');
        $I->assertCount(2, $I->grabDataFromResponseByJsonPath('$[*]'));
        $I->sendGet(self::API . "/budgets?category_id=$food");
        $I->assertSame(['2026-12', '2026-11'], $I->grabDataFromResponseByJsonPath('$[*].month'));
        $I->sendGet(self::API . '/budgets?currency=usd');
        $I->assertCount(1, $I->grabDataFromResponseByJsonPath('$[*]'));

        foreach (['month=bad', 'category_id=x', 'currency=1'] as $query) {
            $I->sendGet(self::API . '/budgets?' . $query);
            $I->seeResponseCodeIs(400);
        }
    }

    public function budgetsAreIsolatedPerUser(ApiTester $I): void
    {
        $this->registerAndAuth($I);
        $food = $this->createCategory($I, 'Еда');
        $id = $this->createBudget($I, $food);

        $this->registerAndAuth($I);
        $I->sendGet(self::API . "/budgets/$id");
        $I->seeResponseCodeIs(404);
        $I->sendPatch(self::API . "/budgets/$id", ['limit' => '1']);
        $I->seeResponseCodeIs(404);
        $I->sendDelete(self::API . "/budgets/$id");
        $I->seeResponseCodeIs(404);
        $I->sendGet(self::API . '/budgets');
        $I->assertCount(0, $I->grabDataFromResponseByJsonPath('$[*]'));

        // Чужую категорию использовать нельзя
        $I->sendPost(self::API . '/budgets', ['category_id' => $food, 'month' => '2026-11', 'currency' => 'KZT', 'limit' => '1']);
        $I->seeResponseCodeIs(422);
    }

    public function requiresAuthAndDeletesWithCategory(ApiTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendGet(self::API . '/budgets');
        $I->seeResponseCodeIs(401);

        $this->registerAndAuth($I);
        $food = $this->createCategory($I, 'Еда');
        $this->createBudget($I, $food);

        $I->sendDelete(self::API . "/categories/$food");
        $I->seeResponseCodeIs(204);
        $I->sendGet(self::API . '/budgets');
        $I->assertCount(0, $I->grabDataFromResponseByJsonPath('$[*]'));
    }
}
