<?php

namespace Tests\Api;

use Tests\Support\ApiTester;

class AccountCest extends ApiBase
{
    public function createNormalizesCurrencyAndKeepsPrecision(ApiTester $I): void
    {
        $this->registerAndAuth($I);
        $id = $this->createAccount($I, 'Карта', 'usd', '1500.50');

        $I->sendGet(self::API . "/accounts/$id");
        $I->seeResponseContainsJson(['currency' => 'USD', 'initial_balance' => '1500.50']);
    }

    public function validation(ApiTester $I): void
    {
        $this->registerAndAuth($I);
        $this->createAccount($I, 'Карта');

        $I->sendPost(self::API . '/accounts', ['name' => 'Карта', 'currency' => 'KZT']);
        $I->seeResponseCodeIs(422);
        $I->sendPost(self::API . '/accounts', ['name' => 'Z', 'currency' => 'TENGE']);
        $I->seeResponseCodeIs(422);
    }

    public function isolationBetweenUsers(ApiTester $I): void
    {
        $this->registerAndAuth($I);
        $id = $this->createAccount($I);

        $this->registerAndAuth($I);
        $I->sendGet(self::API . "/accounts/$id");
        $I->seeResponseCodeIs(404);
        $I->sendDelete(self::API . "/accounts/$id");
        $I->seeResponseCodeIs(404);
        $I->sendGet(self::API . '/accounts');
        $I->assertCount(0, $I->grabDataFromResponseByJsonPath('$[*]'));
    }

    public function usedAccountProtected(ApiTester $I): void
    {
        $this->registerAndAuth($I);
        $account = $this->createAccount($I);
        $this->createTransaction($I, $account, $this->createCategory($I));

        $I->sendDelete(self::API . "/accounts/$account");
        $I->seeResponseCodeIs(409);
        $I->sendPatch(self::API . "/accounts/$account", ['currency' => 'USD']);
        $I->seeResponseCodeIs(422);
        $I->sendPatch(self::API . "/accounts/$account", ['name' => 'Касса']);
        $I->seeResponseCodeIs(200);
    }

    public function deleteUnused(ApiTester $I): void
    {
        $this->registerAndAuth($I);
        $id = $this->createAccount($I);

        $I->sendDelete(self::API . "/accounts/$id");
        $I->seeResponseCodeIs(204);
        $I->sendDelete(self::API . "/accounts/$id");
        $I->seeResponseCodeIs(404);
    }

    public function paginationHeaders(ApiTester $I): void
    {
        $this->registerAndAuth($I);
        $this->createAccount($I, 'A');
        $this->createAccount($I, 'B');

        $I->sendGet(self::API . '/accounts?per-page=1');
        $I->seeHttpHeader('X-Pagination-Total-Count', '2');
        $I->seeHttpHeader('X-Pagination-Per-Page', '1');
        $I->assertCount(1, $I->grabDataFromResponseByJsonPath('$[*]'));

        $I->sendGet(self::API . '/accounts?per-page=1000');
        $I->seeHttpHeader('X-Pagination-Per-Page', '100');
    }
}
