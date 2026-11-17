<?php

namespace Tests\Api;

use Tests\Support\ApiTester;

class CategoryCest extends ApiBase
{
    public function createListAndFilter(ApiTester $I): void
    {
        $this->registerAndAuth($I);
        $this->createCategory($I, 'Продукты', 'expense');
        $this->createCategory($I, 'Зарплата', 'income');

        $I->sendGet(self::API . '/categories');
        $I->seeResponseCodeIs(200);
        $I->assertCount(2, $I->grabDataFromResponseByJsonPath('$[*]'));

        $I->sendGet(self::API . '/categories?type=income');
        $I->assertSame(['Зарплата'], $I->grabDataFromResponseByJsonPath('$[*].name'));

        $I->sendGet(self::API . '/categories?type=bad');
        $I->seeResponseCodeIs(400);
    }

    public function createIgnoresUserIdAndHidesIt(ApiTester $I): void
    {
        $this->registerAndAuth($I);
        $I->sendPost(self::API . '/categories', ['name' => 'Х', 'type' => 'expense', 'user_id' => 999999]);
        $I->seeResponseCodeIs(201);
        $I->dontSeeResponseJsonMatchesJsonPath('$.user_id');
        $I->seeHttpHeader('Location');
    }

    public function validation(ApiTester $I): void
    {
        $this->registerAndAuth($I);
        $this->createCategory($I, 'Дубль');

        $I->sendPost(self::API . '/categories', ['name' => 'Дубль', 'type' => 'expense']);
        $I->seeResponseCodeIs(422);
        $I->sendPost(self::API . '/categories', ['name' => 'Ц', 'type' => 'expense', 'color' => 'red']);
        $I->seeResponseCodeIs(422);
        $I->sendPost(self::API . '/categories', ['name' => 'Т', 'type' => 'transfer']);
        $I->seeResponseCodeIs(422);
        $I->sendPost(self::API . '/categories', []);
        $I->seeResponseCodeIs(422);
    }

    public function otherUsersCannotTouchCategory(ApiTester $I): void
    {
        $owner = $this->registerAndAuth($I);
        $id = $this->createCategory($I);

        $this->registerAndAuth($I);
        $I->sendGet(self::API . "/categories/$id");
        $I->seeResponseCodeIs(404);
        $I->sendPatch(self::API . "/categories/$id", ['name' => 'Взлом']);
        $I->seeResponseCodeIs(404);
        $I->sendDelete(self::API . "/categories/$id");
        $I->seeResponseCodeIs(404);
        $I->sendGet(self::API . '/categories');
        $I->assertCount(0, $I->grabDataFromResponseByJsonPath('$[*]'));

        $this->useToken($I, $owner);
        $I->sendGet(self::API . "/categories/$id");
        $I->seeResponseContainsJson(['name' => 'Продукты']);
    }

    public function patchCannotTransferOwnership(ApiTester $I): void
    {
        $this->registerAndAuth($I);
        $id = $this->createCategory($I);

        $I->sendPatch(self::API . "/categories/$id", ['name' => 'Новое', 'user_id' => 1]);
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['name' => 'Новое']);
        $I->sendGet(self::API . "/categories/$id");
        $I->seeResponseCodeIs(200);
    }

    public function usedCategoryProtected(ApiTester $I): void
    {
        $this->registerAndAuth($I);
        $account = $this->createAccount($I);
        $category = $this->createCategory($I);
        $this->createTransaction($I, $account, $category);

        $I->sendDelete(self::API . "/categories/$category");
        $I->seeResponseCodeIs(409);
        $I->sendPatch(self::API . "/categories/$category", ['type' => 'income']);
        $I->seeResponseCodeIs(422);
        $I->sendPatch(self::API . "/categories/$category", ['name' => 'Еда']);
        $I->seeResponseCodeIs(200);
    }

    public function deleteUnused(ApiTester $I): void
    {
        $this->registerAndAuth($I);
        $id = $this->createCategory($I);

        $I->sendDelete(self::API . "/categories/$id");
        $I->seeResponseCodeIs(204);
        $I->sendGet(self::API . "/categories/$id");
        $I->seeResponseCodeIs(404);
    }
}
