<?php

namespace app\modules\v1\controllers;

use app\models\Category;
use OpenApi\Attributes as OA;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\web\BadRequestHttpException;
use yii\web\ConflictHttpException;

/**
 * Категории пользователя.
 *
 * GET    /api/v1/categories[?type=expense|income&sort=name&page=1&per-page=50]
 * POST   /api/v1/categories
 * GET    /api/v1/categories/{id}
 * PUT    /api/v1/categories/{id}  (или PATCH)
 * DELETE /api/v1/categories/{id}
 */
#[OA\Get(
    path: '/categories',
    operationId: 'categoryList',
    summary: 'Список категорий',
    description: 'Только категории текущего пользователя. Пагинация в заголовках X-Pagination-*.',
    tags: ['Categories'],
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(name: 'type', in: 'query', description: 'Фильтр по типу', schema: new OA\Schema(type: 'string', enum: ['expense', 'income'])),
        new OA\Parameter(name: 'sort', in: 'query', description: 'Сортировка: type и name (по умолчанию), created_at; «-» перед именем — по убыванию', schema: new OA\Schema(type: 'string', example: 'name')),
        new OA\Parameter(ref: '#/components/parameters/Page'),
        new OA\Parameter(ref: '#/components/parameters/PerPage'),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Страница категорий',
            headers: [
                new OA\Header(header: 'X-Pagination-Total-Count', schema: new OA\Schema(type: 'integer')),
                new OA\Header(header: 'X-Pagination-Current-Page', schema: new OA\Schema(type: 'integer')),
                new OA\Header(header: 'X-Pagination-Page-Count', schema: new OA\Schema(type: 'integer')),
                new OA\Header(header: 'X-Pagination-Per-Page', schema: new OA\Schema(type: 'integer')),
            ],
            content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/Category')),
        ),
        new OA\Response(ref: '#/components/responses/BadRequest', response: 400),
        new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
    ],
)]
#[OA\Post(
    path: '/categories',
    operationId: 'categoryCreate',
    summary: 'Создать категорию',
    tags: ['Categories'],
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/CategoryInput')),
    responses: [
        new OA\Response(
            response: 201,
            description: 'Категория создана; адрес в заголовке Location',
            headers: [new OA\Header(header: 'Location', schema: new OA\Schema(type: 'string', format: 'uri'))],
            content: new OA\JsonContent(ref: '#/components/schemas/Category'),
        ),
        new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
        new OA\Response(ref: '#/components/responses/ValidationFailed', response: 422),
    ],
)]
#[OA\Get(
    path: '/categories/{id}',
    operationId: 'categoryView',
    summary: 'Категория по id',
    tags: ['Categories'],
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(ref: '#/components/parameters/RecordId')],
    responses: [
        new OA\Response(response: 200, description: 'Категория', content: new OA\JsonContent(ref: '#/components/schemas/Category')),
        new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
        new OA\Response(ref: '#/components/responses/NotFound', response: 404),
    ],
)]
#[OA\Put(
    path: '/categories/{id}',
    operationId: 'categoryUpdate',
    summary: 'Обновить категорию',
    description: 'Тип категории с операциями менять нельзя. PATCH работает так же.',
    tags: ['Categories'],
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(ref: '#/components/parameters/RecordId')],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/CategoryInput')),
    responses: [
        new OA\Response(response: 200, description: 'Категория обновлена', content: new OA\JsonContent(ref: '#/components/schemas/Category')),
        new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
        new OA\Response(ref: '#/components/responses/NotFound', response: 404),
        new OA\Response(ref: '#/components/responses/ValidationFailed', response: 422),
    ],
)]
#[OA\Patch(
    path: '/categories/{id}',
    operationId: 'categoryPatch',
    summary: 'Частично обновить категорию',
    tags: ['Categories'],
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(ref: '#/components/parameters/RecordId')],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/CategoryInput')),
    responses: [
        new OA\Response(response: 200, description: 'Категория обновлена', content: new OA\JsonContent(ref: '#/components/schemas/Category')),
        new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
        new OA\Response(ref: '#/components/responses/NotFound', response: 404),
        new OA\Response(ref: '#/components/responses/ValidationFailed', response: 422),
    ],
)]
#[OA\Delete(
    path: '/categories/{id}',
    operationId: 'categoryDelete',
    summary: 'Удалить категорию',
    description: 'Категорию с операциями удалить нельзя; бюджеты категории удаляются вместе с ней.',
    tags: ['Categories'],
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(ref: '#/components/parameters/RecordId')],
    responses: [
        new OA\Response(response: 204, description: 'Категория удалена, тела нет'),
        new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
        new OA\Response(ref: '#/components/responses/NotFound', response: 404),
        new OA\Response(ref: '#/components/responses/Conflict', response: 409),
    ],
)]
class CategoryController extends OwnedActiveController
{
    public $modelClass = Category::class;

    /**
     * Список категорий с необязательным фильтром ?type=expense|income.
     *
     * @return ActiveQuery запрос категорий текущего пользователя
     * @throws BadRequestHttpException если type не expense и не income
     */
    protected function indexQuery(): ActiveQuery
    {
        $query = parent::indexQuery();
        $type = \Yii::$app->request->get('type');

        if ($type !== null && $type !== '') {
            if (!in_array($type, Category::TYPES, true)) {
                throw new BadRequestHttpException('Параметр type: expense или income.');
            }

            $query->andWhere(['type' => $type]);
        }

        return $query;
    }

    /**
     * Сортировка списка категорий: по типу и названию (по умолчанию), либо по дате создания.
     *
     * @return array<string, mixed> конфигурация yii\data\Sort
     */
    protected function sortConfig(): array
    {
        return [
            'attributes' => ['name', 'type', 'created_at'],
            'defaultOrder' => ['type' => SORT_ASC, 'name' => SORT_ASC],
        ];
    }

    /**
     * Категорию с операциями удалять нельзя.
     *
     * @param ActiveRecord $model удаляемая категория (Category)
     * @return void
     * @throws ConflictHttpException если по категории есть операции
     */
    protected function assertDeletable(ActiveRecord $model): void
    {
        /** @var Category $model */
        if ($model->getTransactions()->exists()) {
            throw new ConflictHttpException('Нельзя удалить категорию, по которой есть операции.');
        }
    }
}
