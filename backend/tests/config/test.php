<?php

$config = require __DIR__ . '/../../config/web.php';

$host = getenv('DB_HOST') ?: 'postgres';
$port = getenv('DB_PORT') ?: '5432';
$name = getenv('TEST_DB_NAME') ?: 'cash_tracker_test';

$config['id'] = 'cash-tracker-api-test';
$config['components']['db']['dsn'] = "pgsql:host={$host};port={$port};dbname={$name}";
$config['components']['db']['enableSchemaCache'] = false;
$config['components']['request']['cookieValidationKey'] = 'test';
$config['components']['cache'] = ['class' => 'yii\caching\DummyCache'];

// Быстрое хеширование паролей: в тестах стойкость не нужна
$config['components']['security'] = ['passwordHashCost' => 4];

return $config;
