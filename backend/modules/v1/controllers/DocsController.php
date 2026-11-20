<?php

namespace app\modules\v1\controllers;

use OpenApi\Attributes as OA;
use OpenApi\Generator;
use Yii;
use yii\rest\Controller;
use yii\web\Response;

/**
 * Документация API: OpenAPI-спецификация и страница Swagger UI. Адреса публичные.
 *
 * GET /api/v1/docs               страница Swagger UI
 * GET /api/v1/docs/openapi.json  спецификация, собранная из атрибутов контроллеров
 */
class DocsController extends Controller
{
    private const SWAGGER_UI_VERSION = '5.17.14';

    /**
     * Поведения контроллера: адреса публичные, поэтому лимитер запросов отключён.
     *
     * @return array<string, mixed> конфигурации поведений
     */
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
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
            'spec' => ['GET'],
        ];
    }

    /**
     * GET /api/v1/docs: HTML-страница Swagger UI, которая загружает спецификацию с этого же сервера.
     * Скрипты и стили Swagger UI берутся с cdnjs, поэтому для страницы нужен доступ в интернет.
     *
     * @return string HTML-страница
     */
    #[OA\Get(
        path: '/docs',
        operationId: 'docsUi',
        summary: 'Страница документации (Swagger UI)',
        tags: ['Docs'],
        security: [],
        responses: [
            new OA\Response(
                response: 200,
                description: 'HTML-страница Swagger UI',
                content: new OA\MediaType(mediaType: 'text/html', schema: new OA\Schema(type: 'string')),
            ),
        ],
    )]
    public function actionIndex(): string
    {
        $response = Yii::$app->response;
        $response->format = Response::FORMAT_RAW;
        $response->getHeaders()->set('Content-Type', 'text/html; charset=UTF-8');

        $version = self::SWAGGER_UI_VERSION;
        $specUrl = json_encode(
            \yii\helpers\Url::to(['/v1/docs/spec']),
            JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP
        );

        return <<<HTML
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cash Tracker API: документация</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/swagger-ui/{$version}/swagger-ui.min.css">
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/swagger-ui/{$version}/swagger-ui-bundle.min.js"></script>
    <script>
        window.ui = SwaggerUIBundle({
            url: {$specUrl},
            dom_id: '#swagger-ui',
            persistAuthorization: true,
        });
    </script>
</body>
</html>
HTML;
    }

    /**
     * GET /api/v1/docs/openapi.json: OpenAPI-спецификация, собранная из атрибутов Swagger.
     * В режиме prod результат кэшируется на час, иначе собирается при каждом запросе.
     *
     * @return array<string, mixed> спецификация OpenAPI 3
     */
    #[OA\Get(
        path: '/docs/openapi.json',
        operationId: 'docsSpec',
        summary: 'OpenAPI-спецификация',
        tags: ['Docs'],
        security: [],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Спецификация OpenAPI 3 в формате JSON',
                content: new OA\JsonContent(type: 'object'),
            ),
        ],
    )]
    public function actionSpec(): array
    {
        $build = static fn(): array => json_decode(
            (new Generator())->generate([Yii::getAlias('@app/modules')])->toJson(),
            true
        );

        if (!YII_ENV_PROD) {
            return $build();
        }

        return Yii::$app->cache->getOrSet('openapi-spec', $build, 3600);
    }
}
