<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

return [
    'id' => 'cash-tracker-api',
    'name' => 'Cash Tracker API',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'language' => 'ru-RU',
    'sourceLanguage' => 'en-US',
    'timeZone' => 'UTC',
    'aliases' => [
        '@app' => dirname(__DIR__),
    ],
    'modules' => [
        'v1' => [
            'class' => 'app\modules\v1\Module',
        ],
    ],
    'components' => [
        'request' => [
            'cookieValidationKey' => getenv('COOKIE_VALIDATION_KEY') ?: '',
            // API без сессий и cookie: CSRF и подпись cookie не нужны
            'enableCsrfValidation' => false,
            'enableCookieValidation' => false,
            'parsers' => [
                'application/json' => 'yii\web\JsonParser',
            ],
        ],
        'response' => [
            // Все ответы, включая ошибки, отдаём в JSON
            'format' => yii\web\Response::FORMAT_JSON,
        ],
        'user' => [
            'identityClass' => 'app\models\User',
            // Stateless API: идентификация только по Bearer-токену
            'enableSession' => false,
            'enableAutoLogin' => false,
            'loginUrl' => null,
        ],
        'errorHandler' => [
            'class' => 'app\components\ErrorHandler',
        ],
        'db' => $db,
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'enableStrictParsing' => true,
            'rules' => [
                'GET api/v1/health' => 'v1/health/index',
                'api/v1/health' => 'v1/health/index',

                'GET api/v1/docs' => 'v1/docs/index',
                'api/v1/docs' => 'v1/docs/index',
                'GET api/v1/docs/openapi.json' => 'v1/docs/spec',
                'api/v1/docs/openapi.json' => 'v1/docs/spec',

                'POST api/v1/auth/register' => 'v1/auth/register',
                'api/v1/auth/register' => 'v1/auth/register',
                'POST api/v1/auth/login' => 'v1/auth/login',
                'api/v1/auth/login' => 'v1/auth/login',
                'GET api/v1/auth/me' => 'v1/auth/me',
                'api/v1/auth/me' => 'v1/auth/me',
                'POST api/v1/auth/logout' => 'v1/auth/logout',
                'api/v1/auth/logout' => 'v1/auth/logout',

                'GET api/v1/reports/categories' => 'v1/report/categories',
                'api/v1/reports/categories' => 'v1/report/categories',
                'GET api/v1/reports/monthly' => 'v1/report/monthly',
                'api/v1/reports/monthly' => 'v1/report/monthly',
                'GET api/v1/reports/balances' => 'v1/report/balances',
                'api/v1/reports/balances' => 'v1/report/balances',

                'GET api/v1/transactions/export' => 'v1/transaction/export',
                'api/v1/transactions/export' => 'v1/transaction/export',
                'POST api/v1/transactions/import' => 'v1/transaction/import',
                'api/v1/transactions/import' => 'v1/transaction/import',

                [
                    'class' => 'yii\rest\UrlRule',
                    'pluralize' => false,
                    'controller' => [
                        'api/v1/categories' => 'v1/category',
                        'api/v1/accounts' => 'v1/account',
                        'api/v1/transactions' => 'v1/transaction',
                        'api/v1/budgets' => 'v1/budget',
                    ],
                ],
            ],
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
    ],
    'as corsFilter' => [
        'class' => 'yii\filters\Cors',
        'cors' => [
            'Origin' => $params['corsOrigins'],
            'Access-Control-Request-Method' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
            'Access-Control-Request-Headers' => ['Authorization', 'Content-Type', 'Accept'],
            'Access-Control-Max-Age' => 3600,
            // Без этого браузер скроет заголовки пагинации от фронтенда на другом origin
            'Access-Control-Expose-Headers' => [
                'X-Pagination-Total-Count',
                'X-Pagination-Current-Page',
                'X-Pagination-Page-Count',
                'X-Pagination-Per-Page',
            ],
        ],
    ],
    'params' => $params,
];
