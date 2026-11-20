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
    /**
     * Читает строковый GET-параметр без пробелов по краям.
     *
     * @param string $name имя параметра
     * @return string|null значение или null, если параметра нет или он пустой
     * @throws BadRequestHttpException если передан не строка (например, массив)
     */
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

    /**
     * Читает GET-параметр как неотрицательное целое (не длиннее 9 цифр).
     *
     * @param string $name имя параметра
     * @return int|null значение или null, если параметра нет
     * @throws BadRequestHttpException если значение не целое число
     */
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

    /**
     * Читает GET-параметр как существующую дату ГГГГ-ММ-ДД.
     *
     * @param string $name имя параметра
     * @return string|null дата как передана или null, если параметра нет
     * @throws BadRequestHttpException если формат неверный или такой даты не существует
     */
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
