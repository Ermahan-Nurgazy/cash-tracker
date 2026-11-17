<?php

namespace Tests\Api;

use Tests\Support\ApiTester;

class HealthCest
{
    public function healthIsOk(ApiTester $I): void
    {
        $I->sendGet('/api/v1/health');
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['status' => 'ok', 'database' => 'up']);
    }
}
