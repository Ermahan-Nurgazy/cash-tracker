<?php

namespace Tests\Api;

use Tests\Support\ApiTester;

/**
 * Общие шаги API-тестов: регистрация, заголовки и создание данных через API.
 */
abstract class ApiBase
{
    protected const API = '/api/v1';

    private static int $counter = 0;

    /**
     * Регистрирует нового пользователя и подставляет его токен во все следующие запросы.
     */
    protected function registerAndAuth(ApiTester $I, ?string $email = null): string
    {
        $email ??= 'user' . (++self::$counter) . '-' . uniqid() . '@example.com';

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost(self::API . '/auth/register', ['email' => $email, 'password' => 'secret123']);
        $I->seeResponseCodeIs(201);
        $token = $I->grabDataFromResponseByJsonPath('$.access_token')[0];

        $this->useToken($I, $token);

        return $token;
    }

    protected function useToken(ApiTester $I, string $token): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $token);
    }

    protected function create(ApiTester $I, string $resource, array $data): int
    {
        $I->sendPost(self::API . '/' . $resource, $data);
        $I->seeResponseCodeIs(201);

        return (int) $I->grabDataFromResponseByJsonPath('$.id')[0];
    }

    protected function createAccount(ApiTester $I, string $name = 'Наличные', string $currency = 'KZT', string $initial = '0'): int
    {
        return $this->create($I, 'accounts', ['name' => $name, 'currency' => $currency, 'initial_balance' => $initial]);
    }

    protected function createCategory(ApiTester $I, string $name = 'Продукты', string $type = 'expense'): int
    {
        return $this->create($I, 'categories', ['name' => $name, 'type' => $type]);
    }

    protected function createTransaction(
        ApiTester $I,
        int $accountId,
        int $categoryId,
        string $amount = '100.00',
        string $type = 'expense',
        string $date = '2026-11-10',
        ?string $note = null
    ): int {
        return $this->create($I, 'transactions', [
            'account_id' => $accountId,
            'category_id' => $categoryId,
            'amount' => $amount,
            'type' => $type,
            'occurred_at' => $date,
            'note' => $note,
        ]);
    }
}
