<?php

$host = getenv('DB_HOST') ?: 'postgres';
$port = getenv('DB_PORT') ?: '5432';
$name = getenv('DB_NAME') ?: 'cash_tracker';

return [
    'class' => 'yii\db\Connection',
    'dsn' => "pgsql:host={$host};port={$port};dbname={$name}",
    'username' => getenv('DB_USER') ?: 'cash',
    'password' => getenv('DB_PASSWORD') ?: '',
    'charset' => 'utf8',

    // Кэш схемы включаем вне dev-окружения
    'enableSchemaCache' => !YII_ENV_DEV,
    'schemaCacheDuration' => 3600,
    'schemaCache' => 'cache',
];
