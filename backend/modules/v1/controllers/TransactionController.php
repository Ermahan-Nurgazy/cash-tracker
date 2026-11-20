<?php

namespace app\modules\v1\controllers;

use app\components\TransactionCsv;
use app\components\TransactionImporter;
use app\models\Category;
use app\models\Transaction;
use OpenApi\Attributes as OA;
use Yii;
use yii\db\ActiveQuery;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;
use yii\web\Response;

/**
 * Операции (расходы и доходы) пользователя.
 *
 * GET    /api/v1/transactions
 *        фильтры: date_from, date_to (Y-m-d, включительно), account_id, category_id,
 *                 type (expense|income), q (поиск по заметке)
 *        сортировка: ?sort=-occurred_at (по умолчанию), amount, created_at
 *        страницы: page, per-page (по умолчанию 50, максимум 100)
 * POST   /api/v1/transactions
 * GET    /api/v1/transactions/{id}
 * PUT    /api/v1/transactions/{id}  (или PATCH)
 * DELETE /api/v1/transactions/{id}
 * GET    /api/v1/transactions/export  (CSV с теми же фильтрами)
 * POST   /api/v1/transactions/import  (тело запроса: CSV)
 *
 * В каждой операции есть вложенные account {id, name, currency}
 * и category {id, name, type, color, icon}.
 */
#[OA\Get(
    path: '/transactions',
    operationId: 'transactionList',
    summary: 'Список операций',
    description: 'Только операции текущего пользователя, с вложенными account и category. Пагинация в заголовках X-Pagination-*.',
    tags: ['Transactions'],
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(name: 'date_from', in: 'query', description: 'Начало периода, включительно', schema: new OA\Schema(type: 'string', format: 'date')),
        new OA\Parameter(name: 'date_to', in: 'query', description: 'Конец периода, включительно', schema: new OA\Schema(type: 'string', format: 'date')),
        new OA\Parameter(name: 'account_id', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'category_id', in: 'query', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'type', in: 'query', schema: new OA\Schema(type: 'string', enum: ['expense', 'income'])),
        new OA\Parameter(name: 'q', in: 'query', description: 'Поиск по заметке без учёта регистра, до 100 символов', schema: new OA\Schema(type: 'string', maxLength: 100)),
        new OA\Parameter(name: 'sort', in: 'query', description: 'Сортировка: occurred_at (по умолчанию -occurred_at), amount, created_at', schema: new OA\Schema(type: 'string', example: '-occurred_at')),
        new OA\Parameter(ref: '#/components/parameters/Page'),
        new OA\Parameter(ref: '#/components/parameters/PerPage'),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Страница операций',
            headers: [
                new OA\Header(header: 'X-Pagination-Total-Count', schema: new OA\Schema(type: 'integer')),
                new OA\Header(header: 'X-Pagination-Current-Page', schema: new OA\Schema(type: 'integer')),
                new OA\Header(header: 'X-Pagination-Page-Count', schema: new OA\Schema(type: 'integer')),
                new OA\Header(header: 'X-Pagination-Per-Page', schema: new OA\Schema(type: 'integer')),
            ],
            content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/Transaction')),
        ),
        new OA\Response(ref: '#/components/responses/BadRequest', response: 400),
        new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
    ],
)]
#[OA\Post(
    path: '/transactions',
    operationId: 'transactionCreate',
    summary: 'Создать операцию',
    description: 'Тип операции должен совпадать с типом категории.',
    tags: ['Transactions'],
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/TransactionInput')),
    responses: [
        new OA\Response(
            response: 201,
            description: 'Операция создана; адрес в заголовке Location',
            headers: [new OA\Header(header: 'Location', schema: new OA\Schema(type: 'string', format: 'uri'))],
            content: new OA\JsonContent(ref: '#/components/schemas/Transaction'),
        ),
        new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
        new OA\Response(ref: '#/components/responses/ValidationFailed', response: 422),
    ],
)]
#[OA\Get(
    path: '/transactions/{id}',
    operationId: 'transactionView',
    summary: 'Операция по id',
    tags: ['Transactions'],
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(ref: '#/components/parameters/RecordId')],
    responses: [
        new OA\Response(response: 200, description: 'Операция', content: new OA\JsonContent(ref: '#/components/schemas/Transaction')),
        new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
        new OA\Response(ref: '#/components/responses/NotFound', response: 404),
    ],
)]
#[OA\Put(
    path: '/transactions/{id}',
    operationId: 'transactionUpdate',
    summary: 'Обновить операцию',
    description: 'PATCH работает так же.',
    tags: ['Transactions'],
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(ref: '#/components/parameters/RecordId')],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/TransactionInput')),
    responses: [
        new OA\Response(response: 200, description: 'Операция обновлена', content: new OA\JsonContent(ref: '#/components/schemas/Transaction')),
        new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
        new OA\Response(ref: '#/components/responses/NotFound', response: 404),
        new OA\Response(ref: '#/components/responses/ValidationFailed', response: 422),
    ],
)]
#[OA\Patch(
    path: '/transactions/{id}',
    operationId: 'transactionPatch',
    summary: 'Частично обновить операцию',
    tags: ['Transactions'],
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(ref: '#/components/parameters/RecordId')],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/TransactionInput')),
    responses: [
        new OA\Response(response: 200, description: 'Операция обновлена', content: new OA\JsonContent(ref: '#/components/schemas/Transaction')),
        new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
        new OA\Response(ref: '#/components/responses/NotFound', response: 404),
        new OA\Response(ref: '#/components/responses/ValidationFailed', response: 422),
    ],
)]
#[OA\Delete(
    path: '/transactions/{id}',
    operationId: 'transactionDelete',
    summary: 'Удалить операцию',
    tags: ['Transactions'],
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(ref: '#/components/parameters/RecordId')],
    responses: [
        new OA\Response(response: 204, description: 'Операция удалена, тела нет'),
        new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
        new OA\Response(ref: '#/components/responses/NotFound', response: 404),
    ],
)]
class TransactionController extends OwnedActiveController
{
    use QueryParamsTrait;

    public $modelClass = Transaction::class;

    private const EXPORT_LIMIT = 10000;
    private const IMPORT_MAX_BYTES = 1048576;

    /**
     * Список операций с фильтрами date_from, date_to, account_id, category_id, type и q.
     * Поиск q идёт по заметке без учёта регистра, спецсимволы LIKE экранируются.
     *
     * @return ActiveQuery запрос операций текущего пользователя с загруженными account и category
     * @throws BadRequestHttpException если параметр неверного формата, date_from позже date_to,
     *         type не expense и не income или q длиннее 100 символов
     */
    protected function indexQuery(): ActiveQuery
    {
        $query = parent::indexQuery()->with(['account', 'category']);

        $dateFrom = $this->dateParam('date_from');
        $dateTo = $this->dateParam('date_to');

        if ($dateFrom !== null && $dateTo !== null && $dateFrom > $dateTo) {
            throw new BadRequestHttpException('date_from не может быть позже date_to.');
        }

        if ($dateFrom !== null) {
            $query->andWhere(['>=', 'occurred_at', $dateFrom]);
        }

        if ($dateTo !== null) {
            $query->andWhere(['<=', 'occurred_at', $dateTo]);
        }

        if (($accountId = $this->intParam('account_id')) !== null) {
            $query->andWhere(['account_id' => $accountId]);
        }

        if (($categoryId = $this->intParam('category_id')) !== null) {
            $query->andWhere(['category_id' => $categoryId]);
        }

        if (($type = $this->stringParam('type')) !== null) {
            if (!in_array($type, Category::TYPES, true)) {
                throw new BadRequestHttpException('Параметр type: expense или income.');
            }

            $query->andWhere(['type' => $type]);
        }

        if (($q = $this->stringParam('q')) !== null) {
            if (mb_strlen($q) > 100) {
                throw new BadRequestHttpException('Параметр q: не длиннее 100 символов.');
            }

            // Спецсимволы LIKE (%, _) экранируются, поиск идёт по вхождению без учёта регистра
            $query->andWhere(['ilike', 'note', $q]);
        }

        return $query;
    }

    /**
     * Допустимые HTTP-методы: стандартные для CRUD плюс export (GET) и import (POST).
     *
     * @return array<string, string[]> действие => список методов
     */
    protected function verbs(): array
    {
        return array_merge(parent::verbs(), [
            'export' => ['GET'],
            'import' => ['POST'],
        ]);
    }

    /**
     * Экспорт в CSV с теми же фильтрами, что и у списка (кроме сортировки и страниц).
     * Файл: UTF-8 с BOM, разделитель «;», по возрастанию даты.
     *
     * @return string содержимое CSV (ответ отдаётся как есть, с заголовками вложения)
     * @throws BadRequestHttpException если фильтры неверны или операций больше EXPORT_LIMIT
     */
    #[OA\Get(
        path: '/transactions/export',
        operationId: 'transactionExport',
        summary: 'Экспорт операций в CSV',
        description: 'Те же фильтры, что у списка (без сортировки и страниц). UTF-8 с BOM, разделитель «;», '
            . 'по возрастанию даты. Не больше 10 000 операций за раз.',
        tags: ['Transactions'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'date_from', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'date_to', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'account_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'category_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'type', in: 'query', schema: new OA\Schema(type: 'string', enum: ['expense', 'income'])),
            new OA\Parameter(name: 'q', in: 'query', schema: new OA\Schema(type: 'string', maxLength: 100)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'CSV-файл (вложение)',
                headers: [new OA\Header(header: 'Content-Disposition', schema: new OA\Schema(type: 'string', example: 'attachment; filename="transactions-2026-11-20.csv"'))],
                content: new OA\MediaType(mediaType: 'text/csv', schema: new OA\Schema(type: 'string', format: 'binary')),
            ),
            new OA\Response(ref: '#/components/responses/BadRequest', response: 400),
            new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
        ],
    )]
    public function actionExport(): string
    {
        $query = $this->indexQuery()->orderBy(['occurred_at' => SORT_ASC, 'id' => SORT_ASC]);

        if ((clone $query)->count() > self::EXPORT_LIMIT) {
            throw new BadRequestHttpException(
                'Слишком много операций для экспорта (больше ' . self::EXPORT_LIMIT . '): сузьте период.'
            );
        }

        $response = Yii::$app->response;
        $response->format = Response::FORMAT_RAW;
        $response->getHeaders()
            ->set('Content-Type', 'text/csv; charset=UTF-8')
            ->set('Content-Disposition', 'attachment; filename="transactions-' . date('Y-m-d') . '.csv"');

        return TransactionCsv::export($query->each(500));
    }

    /**
     * Импорт из CSV: тело запроса — сам файл (Content-Type: text/csv).
     * Всё или ничего: при ошибках в строках 422 с перечнем ошибок и ничего не сохраняется.
     *
     * @return array{imported: int}|array{status: int, message: string, errors: array<string, string>}
     *         число сохранённых операций либо тело ответа 422 с ошибками
     * @throws BadRequestHttpException если тело запроса пустое
     * @throws HttpException со статусом 413, если файл больше 1 МБ
     * @throws \Throwable если сохранение в БД не удалось
     */
    #[OA\Post(
        path: '/transactions/import',
        operationId: 'transactionImport',
        summary: 'Импорт операций из CSV',
        description: 'Тело запроса — сам CSV-файл (до 1 МБ и 2000 строк). Всё или ничего: при ошибках в строках '
            . 'ничего не сохраняется, в ответе 422 — перечень ошибок по строкам.',
        tags: ['Transactions'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'text/csv',
                schema: new OA\Schema(type: 'string', format: 'binary', description: 'Столбцы: Дата;Тип;Сумма;Валюта;Счёт;Категория;Заметка'),
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Операции импортированы',
                content: new OA\JsonContent(ref: '#/components/schemas/ImportResult'),
            ),
            new OA\Response(ref: '#/components/responses/BadRequest', response: 400),
            new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
            new OA\Response(
                response: 413,
                description: 'Файл больше 1 МБ',
                content: new OA\JsonContent(ref: '#/components/schemas/Error'),
            ),
            new OA\Response(
                response: 422,
                description: 'В файле есть ошибки, ничего не сохранено',
                content: new OA\JsonContent(ref: '#/components/schemas/ImportError'),
            ),
        ],
    )]
    public function actionImport(): array
    {
        $raw = Yii::$app->request->getRawBody();

        if (trim($raw) === '') {
            throw new BadRequestHttpException('Передайте CSV-файл в теле запроса.');
        }

        if (strlen($raw) > self::IMPORT_MAX_BYTES) {
            throw new HttpException(413, 'Файл слишком большой: максимум 1 МБ.');
        }

        $result = (new TransactionImporter())->import((int) Yii::$app->user->id, $raw);

        if ($result['errors'] !== []) {
            Yii::$app->response->setStatusCode(422);

            return [
                'status' => 422,
                'message' => 'Импорт отменён: в файле есть ошибки, ничего не сохранено.',
                'errors' => $result['errors'],
            ];
        }

        return ['imported' => $result['imported']];
    }

    /**
     * id добавлен вторым ключом, чтобы порядок при одинаковых значениях был стабильным
     * и записи не терялись и не дублировались между страницами.
     *
     * @return array<string, mixed> конфигурация yii\data\Sort: сортировка по дате (по умолчанию,
     *         новые первыми), сумме или дате создания
     */
    protected function sortConfig(): array
    {
        $attribute = static fn(string $column) => [
            'asc' => [$column => SORT_ASC, 'id' => SORT_ASC],
            'desc' => [$column => SORT_DESC, 'id' => SORT_DESC],
        ];

        return [
            'attributes' => [
                'occurred_at' => $attribute('occurred_at'),
                'amount' => $attribute('amount'),
                'created_at' => $attribute('created_at'),
            ],
            'defaultOrder' => ['occurred_at' => SORT_DESC],
        ];
    }
}
