<?php

namespace app\modules\v1\controllers;

use app\models\Budget;
use OpenApi\Attributes as OA;
use yii\db\ActiveQuery;
use yii\web\BadRequestHttpException;

/**
 * Бюджеты: лимиты расходов по категориям на месяц.
 *
 * GET    /api/v1/budgets[?month=2026-11&category_id=1&currency=KZT&sort=month&page=1&per-page=50]
 * POST   /api/v1/budgets            {category_id, month: "2026-11", currency, limit}
 * GET    /api/v1/budgets/{id}
 * PUT    /api/v1/budgets/{id}  (или PATCH)
 * DELETE /api/v1/budgets/{id}
 *
 * В ответе кроме полей бюджета: spent (потрачено в валюте бюджета), remaining (лимит минус
 * потрачено, может быть отрицательным), percent (целые проценты, может быть больше 100)
 * и status: ok | warning (от 80 %) | exceeded (больше лимита).
 */
#[OA\Get(
    path: '/budgets',
    operationId: 'budgetList',
    summary: 'Список бюджетов',
    description: 'Только бюджеты текущего пользователя с расчётными spent, remaining, percent и status. Пагинация в заголовках X-Pagination-*.',
    tags: ['Budgets'],
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(name: 'month', in: 'query', description: 'Месяц, ГГГГ-ММ', schema: new OA\Schema(type: 'string', example: '2026-11')),
        new OA\Parameter(name: 'category_id', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'currency', in: 'query', description: 'Три латинские буквы', schema: new OA\Schema(type: 'string', example: 'KZT')),
        new OA\Parameter(name: 'sort', in: 'query', description: 'Сортировка: month (по умолчанию -month), limit, created_at, id', schema: new OA\Schema(type: 'string', example: '-month')),
        new OA\Parameter(ref: '#/components/parameters/Page'),
        new OA\Parameter(ref: '#/components/parameters/PerPage'),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Страница бюджетов',
            headers: [
                new OA\Header(header: 'X-Pagination-Total-Count', schema: new OA\Schema(type: 'integer')),
                new OA\Header(header: 'X-Pagination-Current-Page', schema: new OA\Schema(type: 'integer')),
                new OA\Header(header: 'X-Pagination-Page-Count', schema: new OA\Schema(type: 'integer')),
                new OA\Header(header: 'X-Pagination-Per-Page', schema: new OA\Schema(type: 'integer')),
            ],
            content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/Budget')),
        ),
        new OA\Response(ref: '#/components/responses/BadRequest', response: 400),
        new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
    ],
)]
#[OA\Post(
    path: '/budgets',
    operationId: 'budgetCreate',
    summary: 'Создать бюджет',
    description: 'Категория должна быть расходной. Сочетание категория + месяц + валюта уникально.',
    tags: ['Budgets'],
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/BudgetInput')),
    responses: [
        new OA\Response(
            response: 201,
            description: 'Бюджет создан; адрес в заголовке Location',
            headers: [new OA\Header(header: 'Location', schema: new OA\Schema(type: 'string', format: 'uri'))],
            content: new OA\JsonContent(ref: '#/components/schemas/Budget'),
        ),
        new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
        new OA\Response(ref: '#/components/responses/ValidationFailed', response: 422),
    ],
)]
#[OA\Get(
    path: '/budgets/{id}',
    operationId: 'budgetView',
    summary: 'Бюджет по id',
    tags: ['Budgets'],
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(ref: '#/components/parameters/RecordId')],
    responses: [
        new OA\Response(response: 200, description: 'Бюджет', content: new OA\JsonContent(ref: '#/components/schemas/Budget')),
        new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
        new OA\Response(ref: '#/components/responses/NotFound', response: 404),
    ],
)]
#[OA\Put(
    path: '/budgets/{id}',
    operationId: 'budgetUpdate',
    summary: 'Обновить бюджет',
    description: 'PATCH работает так же.',
    tags: ['Budgets'],
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(ref: '#/components/parameters/RecordId')],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/BudgetInput')),
    responses: [
        new OA\Response(response: 200, description: 'Бюджет обновлён', content: new OA\JsonContent(ref: '#/components/schemas/Budget')),
        new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
        new OA\Response(ref: '#/components/responses/NotFound', response: 404),
        new OA\Response(ref: '#/components/responses/ValidationFailed', response: 422),
    ],
)]
#[OA\Patch(
    path: '/budgets/{id}',
    operationId: 'budgetPatch',
    summary: 'Частично обновить бюджет',
    tags: ['Budgets'],
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(ref: '#/components/parameters/RecordId')],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/BudgetInput')),
    responses: [
        new OA\Response(response: 200, description: 'Бюджет обновлён', content: new OA\JsonContent(ref: '#/components/schemas/Budget')),
        new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
        new OA\Response(ref: '#/components/responses/NotFound', response: 404),
        new OA\Response(ref: '#/components/responses/ValidationFailed', response: 422),
    ],
)]
#[OA\Delete(
    path: '/budgets/{id}',
    operationId: 'budgetDelete',
    summary: 'Удалить бюджет',
    tags: ['Budgets'],
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(ref: '#/components/parameters/RecordId')],
    responses: [
        new OA\Response(response: 204, description: 'Бюджет удалён, тела нет'),
        new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
        new OA\Response(ref: '#/components/responses/NotFound', response: 404),
    ],
)]
class BudgetController extends OwnedActiveController
{
    public $modelClass = Budget::class;

    /**
     * Список бюджетов с фильтрами ?month=ГГГГ-ММ, ?category_id и ?currency.
     *
     * @return ActiveQuery запрос бюджетов текущего пользователя с загруженной категорией
     * @throws BadRequestHttpException если month, category_id или currency имеют неверный формат
     */
    protected function indexQuery(): ActiveQuery
    {
        $query = parent::indexQuery()->with('category');
        $request = \Yii::$app->request;

        $month = $request->get('month');
        if ($month !== null && $month !== '') {
            if (!is_string($month) || !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
                throw new BadRequestHttpException('Параметр month: ГГГГ-ММ.');
            }
            $query->andWhere(['period_month' => $month . '-01']);
        }

        $categoryId = $request->get('category_id');
        if ($categoryId !== null && $categoryId !== '') {
            if (!is_string($categoryId) || !ctype_digit($categoryId)) {
                throw new BadRequestHttpException('Параметр category_id: целое число.');
            }
            $query->andWhere(['category_id' => (int) $categoryId]);
        }

        $currency = $request->get('currency');
        if ($currency !== null && $currency !== '') {
            if (!is_string($currency) || !preg_match('/^[A-Za-z]{3}$/', $currency)) {
                throw new BadRequestHttpException('Параметр currency: три латинские буквы.');
            }
            $query->andWhere(['currency' => strtoupper($currency)]);
        }

        return $query;
    }

    /**
     * Сортировка списка бюджетов: по месяцу (по умолчанию, новые первыми), лимиту,
     * дате создания или id.
     *
     * @return array<string, mixed> конфигурация yii\data\Sort
     */
    protected function sortConfig(): array
    {
        return [
            'attributes' => [
                'month' => ['asc' => ['period_month' => SORT_ASC], 'desc' => ['period_month' => SORT_DESC]],
                'limit',
                'created_at',
                'id',
            ],
            'defaultOrder' => ['month' => SORT_DESC, 'id' => SORT_ASC],
        ];
    }
}
