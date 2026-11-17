<?php

// Тесты работают с отдельной базой cash_tracker_test, данные разработки не затрагиваются.
// Файл может подключаться несколько раз, поэтому константы объявляются с проверкой.
defined('YII_ENV') || define('YII_ENV', 'test');
defined('YII_DEBUG') || define('YII_DEBUG', true);
defined('YII_ENV_TEST') || define('YII_ENV_TEST', true);

require_once __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';
require_once __DIR__ . '/../vendor/autoload.php';
