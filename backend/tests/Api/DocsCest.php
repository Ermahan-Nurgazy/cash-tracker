<?php

namespace Tests\Api;

use Tests\Support\ApiTester;

class DocsCest
{
    public function specIsPublicAndComplete(ApiTester $I): void
    {
        $I->sendGet('/api/v1/docs/openapi.json');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['info' => ['title' => 'Cash Tracker API']]);

        $paths = array_keys(json_decode($I->grabResponse(), true)['paths']);

        foreach (['/auth/login', '/accounts/{id}', '/transactions/import', '/reports/monthly', '/health', '/docs'] as $path) {
            $I->assertContains($path, $paths);
        }
    }

    public function uiPageIsPublic(ApiTester $I): void
    {
        $I->sendGet('/api/v1/docs');
        $I->seeResponseCodeIs(200);
        $I->seeHttpHeader('Content-Type', 'text/html; charset=UTF-8');
        $I->seeResponseContains('swagger-ui');
        $I->seeResponseContains('/api/v1/docs/openapi.json');
    }

    public function postIsNotAllowed(ApiTester $I): void
    {
        $I->sendPost('/api/v1/docs');
        $I->seeResponseCodeIs(405);
    }
}
