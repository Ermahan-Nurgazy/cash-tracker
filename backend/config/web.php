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
        // Компонент user (identityClass) добавим на шаге 3.1 вместе с аутентификацией
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
        ],
    ],
    'params' => $params,
];
