<?php

namespace Tests\Api;

use Tests\Support\ApiTester;

class AuthCest extends ApiBase
{
    public function registerReturnsUserAndToken(ApiTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost(self::API . '/auth/register', ['email' => 'New@Example.com', 'password' => 'secret123']);
        $I->seeResponseCodeIs(201);
        $I->seeResponseContainsJson(['user' => ['email' => 'new@example.com']]);
        $I->seeResponseJsonMatchesJsonPath('$.access_token');
        $I->dontSeeResponseJsonMatchesJsonPath('$.user.password_hash');
        $I->dontSeeResponseJsonMatchesJsonPath('$.user.access_token');
    }

    public function registerRejectsInvalidAndDuplicate(ApiTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost(self::API . '/auth/register', ['email' => 'bad', 'password' => '1']);
        $I->seeResponseCodeIs(422);
        $I->seeResponseContainsJson(['status' => 422, 'message' => 'Ошибка валидации.']);
        $I->seeResponseJsonMatchesJsonPath('$.errors.email');
        $I->seeResponseJsonMatchesJsonPath('$.errors.password');

        $this->registerAndAuth($I, 'dup@example.com');
        $I->sendPost(self::API . '/auth/register', ['email' => 'DUP@example.com', 'password' => 'secret123']);
        $I->seeResponseCodeIs(422);
        $I->seeResponseJsonMatchesJsonPath('$.errors.email');
    }

    public function loginIssuesNewTokenAndInvalidatesOld(ApiTester $I): void
    {
        $old = $this->registerAndAuth($I, 'login@example.com');

        $I->sendPost(self::API . '/auth/login', ['email' => 'login@example.com', 'password' => 'secret123']);
        $I->seeResponseCodeIs(200);
        $new = $I->grabDataFromResponseByJsonPath('$.access_token')[0];
        $I->assertNotSame($old, $new);

        $this->useToken($I, $old);
        $I->sendGet(self::API . '/auth/me');
        $I->seeResponseCodeIs(401);

        $this->useToken($I, $new);
        $I->sendGet(self::API . '/auth/me');
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['email' => 'login@example.com']);
    }

    public function loginFailsWithSameMessage(ApiTester $I): void
    {
        $this->registerAndAuth($I, 'known@example.com');

        $I->sendPost(self::API . '/auth/login', ['email' => 'known@example.com', 'password' => 'wrong-pass']);
        $I->seeResponseCodeIs(401);
        $I->seeResponseContainsJson(['message' => 'Неверный email или пароль.']);

        $I->sendPost(self::API . '/auth/login', ['email' => 'nobody@example.com', 'password' => 'wrong-pass']);
        $I->seeResponseCodeIs(401);
        $I->seeResponseContainsJson(['message' => 'Неверный email или пароль.']);
    }

    public function logoutResetsToken(ApiTester $I): void
    {
        $this->registerAndAuth($I);

        $I->sendPost(self::API . '/auth/logout');
        $I->seeResponseCodeIs(204);

        $I->sendGet(self::API . '/auth/me');
        $I->seeResponseCodeIs(401);
    }

    public function protectedRoutesRequireToken(ApiTester $I): void
    {
        foreach (['/auth/me', '/categories', '/accounts', '/transactions', '/reports/balances'] as $path) {
            $I->sendGet(self::API . $path);
            $I->seeResponseCodeIs(401);
            $I->seeResponseContainsJson(['status' => 401]);
        }
    }
}
