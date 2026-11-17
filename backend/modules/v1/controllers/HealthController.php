<?php

namespace app\modules\v1\controllers;

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
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();

        // Публичный эндпоинт: лимитер требует компонент user (появится на шаге 3.1)
        unset($behaviors['rateLimiter']);

        return $behaviors;
    }

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
