<?php

namespace app\modules\v1\controllers;

use app\models\Account;
use OpenApi\Attributes as OA;
use yii\db\ActiveRecord;
use yii\web\ConflictHttpException;

/**
 * Счета пользователя.
 *
 * GET    /api/v1/accounts[?sort=name&page=1&per-page=50]
 * POST   /api/v1/accounts
 * GET    /api/v1/accounts/{id}
 * PUT    /api/v1/accounts/{id}  (или PATCH)
 * DELETE /api/v1/accounts/{id}
 */
#[OA\Get(
    path: '/accounts',
    operationId: 'accountList',
    summary: 'Список счетов',
    description: 'Только счета текущего пользователя. Пагинация в заголовках X-Pagination-*.',
    tags: ['Accounts'],
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(name: 'sort', in: 'query', description: 'Сортировка: name (по умолчанию), currency, created_at; «-» перед именем — по убыванию', schema: new OA\Schema(type: 'string', example: 'name')),
        new OA\Parameter(ref: '#/components/parameters/Page'),
        new OA\Parameter(ref: '#/components/parameters/PerPage'),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Страница счетов',
            headers: [
                new OA\Header(header: 'X-Pagination-Total-Count', schema: new OA\Schema(type: 'integer')),
                new OA\Header(header: 'X-Pagination-Current-Page', schema: new OA\Schema(type: 'integer')),
                new OA\Header(header: 'X-Pagination-Page-Count', schema: new OA\Schema(type: 'integer')),
                new OA\Header(header: 'X-Pagination-Per-Page', schema: new OA\Schema(type: 'integer')),
            ],
            content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/Account')),
        ),
        new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
    ],
)]
#[OA\Post(
    path: '/accounts',
    operationId: 'accountCreate',
    summary: 'Создать счёт',
    tags: ['Accounts'],
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/AccountInput')),
    responses: [
        new OA\Response(
            response: 201,
            description: 'Счёт создан; адрес в заголовке Location',
            headers: [new OA\Header(header: 'Location', schema: new OA\Schema(type: 'string', format: 'uri'))],
            content: new OA\JsonContent(ref: '#/components/schemas/Account'),
        ),
        new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
        new OA\Response(ref: '#/components/responses/ValidationFailed', response: 422),
    ],
)]
#[OA\Get(
    path: '/accounts/{id}',
    operationId: 'accountView',
    summary: 'Счёт по id',
    tags: ['Accounts'],
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(ref: '#/components/parameters/RecordId')],
    responses: [
        new OA\Response(response: 200, description: 'Счёт', content: new OA\JsonContent(ref: '#/components/schemas/Account')),
        new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
        new OA\Response(ref: '#/components/responses/NotFound', response: 404),
    ],
)]
#[OA\Put(
    path: '/accounts/{id}',
    operationId: 'accountUpdate',
    summary: 'Обновить счёт',
    description: 'Валюту счёта с операциями менять нельзя. PATCH работает так же.',
    tags: ['Accounts'],
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(ref: '#/components/parameters/RecordId')],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/AccountInput')),
    responses: [
        new OA\Response(response: 200, description: 'Счёт обновлён', content: new OA\JsonContent(ref: '#/components/schemas/Account')),
        new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
        new OA\Response(ref: '#/components/responses/NotFound', response: 404),
        new OA\Response(ref: '#/components/responses/ValidationFailed', response: 422),
    ],
)]
#[OA\Patch(
    path: '/accounts/{id}',
    operationId: 'accountPatch',
    summary: 'Частично обновить счёт',
    tags: ['Accounts'],
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(ref: '#/components/parameters/RecordId')],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/AccountInput')),
    responses: [
        new OA\Response(response: 200, description: 'Счёт обновлён', content: new OA\JsonContent(ref: '#/components/schemas/Account')),
        new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
        new OA\Response(ref: '#/components/responses/NotFound', response: 404),
        new OA\Response(ref: '#/components/responses/ValidationFailed', response: 422),
    ],
)]
#[OA\Delete(
    path: '/accounts/{id}',
    operationId: 'accountDelete',
    summary: 'Удалить счёт',
    description: 'Счёт, по которому есть операции, удалить нельзя.',
    tags: ['Accounts'],
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(ref: '#/components/parameters/RecordId')],
    responses: [
        new OA\Response(response: 204, description: 'Счёт удалён, тела нет'),
        new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
        new OA\Response(ref: '#/components/responses/NotFound', response: 404),
        new OA\Response(ref: '#/components/responses/Conflict', response: 409),
    ],
)]
class AccountController extends OwnedActiveController
{
    public $modelClass = Account::class;

    /**
     * Сортировка списка счетов: по названию (по умолчанию), валюте или дате создания.
     *
     * @return array<string, mixed> конфигурация yii\data\Sort
     */
    protected function sortConfig(): array
    {
        return [
            'attributes' => ['name', 'currency', 'created_at'],
            'defaultOrder' => ['name' => SORT_ASC],
        ];
    }

    /**
     * Счёт с операциями удалять нельзя.
     *
     * @param ActiveRecord $model удаляемый счёт (Account)
     * @return void
     * @throws ConflictHttpException если по счёту есть операции
     */
    protected function assertDeletable(ActiveRecord $model): void
    {
        /** @var Account $model */
        if ($model->getTransactions()->exists()) {
            throw new ConflictHttpException('Нельзя удалить счёт, по которому есть операции.');
        }
    }
}
