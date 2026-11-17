<?php

namespace app\modules\v1;

use yii\base\Module as BaseModule;

/**
 * Версия 1 публичного API (префикс /api/v1).
 */
class Module extends BaseModule
{
    public $controllerNamespace = 'app\modules\v1\controllers';
}
