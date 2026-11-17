<?php

namespace Tests\Api;

use Tests\Support\ApiTester;

class ReportCest extends ApiBase
{
    private int $kzt;
    private int $usd;
    private int $food;
    private int $taxi;
    private int $salary;

    private function seed(ApiTester $I): void
    {
        $this->kzt = $this->createAccount($I, 'K', 'KZT', '1000.50');
        $this->usd = $this->createAccount($I, 'U', 'USD', '100');
        $this->food = $this->createCategory($I, 'Food');
        $this->taxi = $this->createCategory($I, 'Taxi');
        $this->salary = $this->createCategory($I, 'Salary', 'income');

        $this->createTransaction($I, $this->kzt, $this->food, '300.10', 'expense', '2026-10-05');
        $this->createTransaction($I, $this->kzt, $this->food, '200.20', 'expense', '2026-10-20');
        $this->createTransaction($I, $this->kzt, $this->taxi, '100', 'expense', '2026-10-21');
        $this->createTransaction($I, $this->kzt, $this->salary, '5000', 'income', '2026-11-01');
        $this->createTransaction($I, $this->kzt, $this->food, '50', 'expense', '2026-11-03');
        $this->createTransaction($I, $this->usd, $this->food, '10', 'expense', '2026-11-02');
    }

    public function categoriesReportGroupsByCurrencyAndCategory(ApiTester $I): void
    {
        $this->registerAndAuth($I);
        $this->seed($I);

        $I->sendGet(self::API . '/reports/categories?date_from=2026-10-01&date_to=2026-10-31');
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['type' => 'expense', 'period' => ['date_from' => '2026-10-01', 'date_to' => '2026-10-31']]);
        $I->assertCount(1, $I->grabDataFromResponseByJsonPath('$.currencies[*]'));
        $I->seeResponseContainsJson(['currency' => 'KZT', 'total' => '600.30', 'count' => 3]);
        $I->assertSame(['Food', 'Taxi'], $I->grabDataFromResponseByJsonPath('$.currencies[0].categories[*].name'));
        $I->assertSame(['500.30', '100.00'], $I->grabDataFromResponseByJsonPath('$.currencies[0].categories[*].total'));
        $I->assertSame([2, 1], $I->grabDataFromResponseByJsonPath('$.currencies[0].categories[*].count'));
        $I->assertEqualsWithDelta([83.3, 16.7], $I->grabDataFromResponseByJsonPath('$.currencies[0].categories[*].share'), 0.001);
    }

    public function categoriesReportKeepsCurrenciesApart(ApiTester $I): void
    {
        $this->registerAndAuth($I);
        $this->seed($I);

        $I->sendGet(self::API . '/reports/categories?date_from=2026-11-01&date_to=2026-11-30');
        $I->assertSame(['KZT', 'USD'], $I->grabDataFromResponseByJsonPath('$.currencies[*].currency'));
        $I->assertSame(['50.00', '10.00'], $I->grabDataFromResponseByJsonPath('$.currencies[*].total'));

        $I->sendGet(self::API . "/reports/categories?date_from=2026-11-01&date_to=2026-11-30&account_id={$this->usd}");
        $I->assertSame(['USD'], $I->grabDataFromResponseByJsonPath('$.currencies[*].currency'));

        $I->sendGet(self::API . '/reports/categories?type=income&date_from=2026-11-01&date_to=2026-11-30');
        $I->assertSame(['Salary'], $I->grabDataFromResponseByJsonPath('$.currencies[0].categories[*].name'));
        $I->assertSame(['5000.00'], $I->grabDataFromResponseByJsonPath('$.currencies[0].categories[*].total'));

        $I->sendGet(self::API . '/reports/categories?date_from=2020-01-01&date_to=2020-01-31');
        $I->assertCount(0, $I->grabDataFromResponseByJsonPath('$.currencies[*]'));
    }

    public function categoriesReportValidatesParams(ApiTester $I): void
    {
        $this->registerAndAuth($I);

        foreach (['type=bad', 'date_from=2026-01-01', 'date_from=2026-02-01&date_to=2026-01-01',
                     'date_from=x&date_to=2026-01-01', 'account_id=abc'] as $query) {
            $I->sendGet(self::API . '/reports/categories?' . $query);
            $I->seeResponseCodeIs(400);
        }
    }

    public function monthlyReportFillsEmptyMonths(ApiTester $I): void
    {
        $this->registerAndAuth($I);
        $this->seed($I);

        $I->sendGet(self::API . '/reports/monthly?date_from=2026-09-15&date_to=2026-11-30');
        $I->seeResponseCodeIs(200);
        $I->assertSame(['KZT', 'USD'], $I->grabDataFromResponseByJsonPath('$.currencies[*].currency'));
        $I->assertSame(['2026-09', '2026-10', '2026-11'], $I->grabDataFromResponseByJsonPath('$.currencies[0].months[*].month'));
        $I->assertSame(['0.00', '600.30', '50.00'], $I->grabDataFromResponseByJsonPath('$.currencies[0].months[*].expense'));
        $I->assertSame(['0.00', '0.00', '5000.00'], $I->grabDataFromResponseByJsonPath('$.currencies[0].months[*].income'));
        $I->assertSame(['0.00', '-600.30', '4950.00'], $I->grabDataFromResponseByJsonPath('$.currencies[0].months[*].balance'));
        $I->seeResponseContainsJson(['total_income' => '5000.00', 'total_expense' => '650.30', 'balance' => '4349.70']);
    }

    public function monthlyReportLimitsPeriod(ApiTester $I): void
    {
        $this->registerAndAuth($I);

        $I->sendGet(self::API . '/reports/monthly?date_from=2015-01-01&date_to=2026-01-01');
        $I->seeResponseCodeIs(400);
        $I->sendGet(self::API . '/reports/monthly?date_from=2026-02-01&date_to=2026-01-01');
        $I->seeResponseCodeIs(400);
        $I->sendGet(self::API . '/reports/monthly');
        $I->seeResponseCodeIs(200);
        $I->assertCount(0, $I->grabDataFromResponseByJsonPath('$.currencies[*]'));
    }

    public function balancesReport(ApiTester $I): void
    {
        $this->registerAndAuth($I);
        $this->seed($I);

        $I->sendGet(self::API . '/reports/balances');
        $I->seeResponseCodeIs(200);
        $I->assertSame(['K', 'U'], $I->grabDataFromResponseByJsonPath('$.accounts[*].name'));
        $I->assertSame(['5350.20', '90.00'], $I->grabDataFromResponseByJsonPath('$.accounts[*].balance'));
        $I->assertSame(['1000.50', '100.00'], $I->grabDataFromResponseByJsonPath('$.accounts[*].initial_balance'));
        $I->assertSame(['5350.20', '90.00'], $I->grabDataFromResponseByJsonPath('$.totals[*].balance'));
    }

    public function reportsAreIsolatedPerUser(ApiTester $I): void
    {
        $this->registerAndAuth($I);
        $this->seed($I);

        $this->registerAndAuth($I);
        $account = $this->createAccount($I, 'Своя');

        $I->sendGet(self::API . '/reports/balances');
        $I->assertSame(['0.00'], $I->grabDataFromResponseByJsonPath('$.accounts[*].balance'));

        $I->sendGet(self::API . '/reports/categories?date_from=2026-01-01&date_to=2026-12-31');
        $I->assertCount(0, $I->grabDataFromResponseByJsonPath('$.currencies[*]'));

        // Чужой account_id просто не даёт данных
        $I->sendGet(self::API . "/reports/categories?date_from=2026-01-01&date_to=2026-12-31&account_id={$this->kzt}");
        $I->seeResponseCodeIs(200);
        $I->assertCount(0, $I->grabDataFromResponseByJsonPath('$.currencies[*]'));
        $I->assertGreaterThan(0, $account);
    }
}
