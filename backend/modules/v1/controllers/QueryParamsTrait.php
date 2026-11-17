<?php

namespace app\modules\v1\controllers;

use DateTimeImmutable;
use Yii;
use yii\web\BadRequestHttpException;

/**
 * Разбор и проверка GET-параметров. Неверное значение даёт 400.
 */
trait QueryParamsTrait
{
    private function stringParam(string $name): ?string
    {
        $value = Yii::$app->request->get($name);

        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value)) {
            throw new BadRequestHttpException("Параметр {$name} должен быть строкой.");
        }

        return trim($value);
    }

    private function intParam(string $name): ?int
    {
        $value = $this->stringParam($name);

        if ($value === null) {
            return null;
        }

        if (!ctype_digit($value) || strlen($value) > 9) {
            throw new BadRequestHttpException("Параметр {$name} должен быть положительным целым числом.");
        }

        return (int) $value;
    }

    private function dateParam(string $name): ?string
    {
        $value = $this->stringParam($name);

        if ($value === null) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new BadRequestHttpException("Параметр {$name}: дата в формате ГГГГ-ММ-ДД.");
        }

        return $value;
    }
}
