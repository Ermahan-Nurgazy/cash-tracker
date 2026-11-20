<?php

namespace app\modules\v1\controllers;

use OpenApi\Attributes as OA;
use Throwable;
use Yii;
use yii\rest\Controller;

/**
 * Проверка работоспособности API и соединения с БД.
 *
 * GET /api/v1/health
 */
class HealthController extends Controller
{
    /**
     * Поведения контроллера: эндпоинт публичный, поэтому лимитер запросов отключён.
     *
     * @return array<string, mixed> конфигурации поведений
     */
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();

        // Публичный эндпоинт: лимитер требует компонент user (появится на шаге 3.1)
        unset($behaviors['rateLimiter']);

        return $behaviors;
    }

    /**
     * Допустимые HTTP-методы действий.
     *
     * @return array<string, string[]> действие => список методов
     */
    protected function verbs(): array
    {
        return [
            'index' => ['GET'],
        ];
    }

    /**
     * GET /api/v1/health: проверяет соединение с БД.
     *
     * Недоступность БД не приводит к ошибке: она отражается в ответе, а причина пишется в лог.
     *
     * @return array{status: string, database: string, time: string}
     *         status — ok или degraded, database — up или down, time — UTC в формате ATOM
     */
    #[OA\Get(
        path: '/health',
        operationId: 'healthCheck',
        summary: 'Проверка работы API и БД',
        description: 'Публичный адрес. Если БД недоступна, ответ остаётся 200, но status=degraded, database=down.',
        tags: ['Health'],
        security: [],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Состояние API и базы данных',
                content: new OA\JsonContent(ref: '#/components/schemas/Health'),
            ),
        ],
    )]
    public function actionIndex(): array
    {
        $database = 'up';

        try {
            Yii::$app->db->open();
        } catch (Throwable $e) {
            Yii::warning($e->getMessage(), __METHOD__);
            $database = 'down';
        }

        return [
            'status' => $database === 'up' ? 'ok' : 'degraded',
            'database' => $database,
            'time' => gmdate(DATE_ATOM),
        ];
    }
}
