<?php

namespace Tests\Api;

use Tests\Support\ApiTester;

/**
 * Единый формат ошибок, 405 и CORS.
 */
class ErrorsCest extends ApiBase
{
    public function unknownRouteGives404InCommonFormat(ApiTester $I): void
    {
        $I->sendGet(self::API . '/nope');
        $I->seeResponseCodeIs(404);
        $I->seeResponseContainsJson(['status' => 404]);
        $I->seeResponseJsonMatchesJsonPath('$.message');
    }

    public function wrongMethodGives405WithAllowHeader(ApiTester $I): void
    {
        $I->sendGet(self::API . '/auth/login');
        $I->seeResponseCodeIs(405);
        $I->seeHttpHeader('Allow', 'POST');
        $I->seeResponseContainsJson(['status' => 405, 'message' => 'Метод не поддерживается для этого адреса.']);

        $I->sendPost(self::API . '/health');
        $I->seeResponseCodeIs(405);

        $this->registerAndAuth($I);
        $I->sendDelete(self::API . '/accounts');
        $I->seeResponseCodeIs(405);
        $I->sendPost(self::API . '/reports/balances');
        $I->seeResponseCodeIs(405);
    }

    public function malformedJsonGives400InRussian(ApiTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost(self::API . '/auth/login', '{bad');
        $I->seeResponseCodeIs(400);
        $I->seeResponseContainsJson(['status' => 400, 'message' => 'Тело запроса содержит некорректный JSON.']);
    }

    public function unauthorizedMessageIsRussian(ApiTester $I): void
    {
        $I->sendGet(self::API . '/accounts');
        $I->seeResponseCodeIs(401);
        $I->seeResponseContainsJson(['status' => 401]);
        $I->seeHttpHeader('Www-Authenticate');
        $I->seeResponseContainsJson(['message' => 'Требуется авторизация: токен отсутствует или недействителен.']);
    }

    public function validationErrorsAreMappedByField(ApiTester $I): void
    {
        $this->registerAndAuth($I);
        $I->sendPost(self::API . '/categories', ['name' => '', 'type' => 'x']);
        $I->seeResponseCodeIs(422);
        $I->seeResponseContainsJson(['status' => 422, 'message' => 'Ошибка валидации.']);
        $I->seeResponseJsonMatchesJsonPath('$.errors.name');
        $I->seeResponseJsonMatchesJsonPath('$.errors.type');
    }

    public function preflightAllowsConfiguredOrigin(ApiTester $I): void
    {
        $I->haveHttpHeader('Origin', 'http://localhost:5173');
        $I->haveHttpHeader('Access-Control-Request-Method', 'POST');
        $I->haveHttpHeader('Access-Control-Request-Headers', 'authorization,content-type');
        $I->sendOptions(self::API . '/auth/login');
        $I->seeResponseCodeIs(200);
        $I->seeHttpHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
        $I->seeHttpHeader('Access-Control-Allow-Methods');
    }

    public function preflightWorksForRestRoutes(ApiTester $I): void
    {
        $I->haveHttpHeader('Origin', 'http://localhost:5173');
        $I->haveHttpHeader('Access-Control-Request-Method', 'DELETE');
        $I->sendOptions(self::API . '/transactions/5');
        $I->seeResponseCodeIs(200);
        $I->seeHttpHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
    }

    public function foreignOriginIsNotAllowed(ApiTester $I): void
    {
        $I->haveHttpHeader('Origin', 'http://evil.example');
        $I->haveHttpHeader('Access-Control-Request-Method', 'POST');
        $I->sendOptions(self::API . '/auth/login');
        $I->dontSeeHttpHeader('Access-Control-Allow-Origin');
    }
}
