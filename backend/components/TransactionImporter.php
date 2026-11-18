<?php

namespace app\components;

use app\models\Account;
use app\models\Category;
use app\models\Transaction;
use Yii;

/**
 * Импорт операций из CSV (формат см. TransactionCsv).
 *
 * Всё или ничего: при любой ошибке в строках не сохраняется ни одна операция,
 * а пользователь получает список ошибок с номерами строк (как в Excel, заголовок — строка 1).
 * Счета и категории ищутся по названию без учёта регистра, новые не создаются.
 * Повторный импорт того же файла создаст дубликаты: операции не сверяются с существующими.
 */
final class TransactionImporter
{
    public const MAX_ROWS = 2000;
    private const MAX_ERRORS = 50;

    /**
     * @return array{imported: int, errors: array<string, string>}
     */
    public function import(int $userId, string $raw): array
    {
        $parsed = TransactionCsv::parse($raw);

        if ($parsed['errors'] !== []) {
            return ['imported' => 0, 'errors' => $parsed['errors']];
        }

        $rows = $parsed['rows'];

        if ($rows === []) {
            return ['imported' => 0, 'errors' => ['Файл' => 'В файле нет операций.']];
        }

        if (count($rows) > self::MAX_ROWS) {
            return ['imported' => 0, 'errors' => ['Файл' => 'Слишком много строк: максимум ' . self::MAX_ROWS . '.']];
        }

        $accounts = [];
        foreach (Account::findAll(['user_id' => $userId]) as $account) {
            $accounts[mb_strtolower($account->name)] = $account;
        }

        $categories = [];
        foreach (Category::findAll(['user_id' => $userId]) as $category) {
            $categories[$category->type . '|' . mb_strtolower($category->name)] = $category;
        }

        $models = [];
        $errors = [];

        foreach ($rows as $row) {
            [$model, $error] = $this->buildModel($userId, $row, $accounts, $categories);

            if ($error !== null) {
                $errors['Строка ' . $row['line']] = $error;
            } else {
                $models[] = $model;
            }
        }

        if ($errors !== []) {
            return ['imported' => 0, 'errors' => $this->limit($errors)];
        }

        $db = Yii::$app->db->beginTransaction();

        try {
            foreach ($models as $model) {
                // Проверка уже выполнена при разборе строки
                $model->save(false);
            }

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();

            throw $e;
        }

        return ['imported' => count($models), 'errors' => []];
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, Account> $accounts
     * @param array<string, Category> $categories
     * @return array{0: ?Transaction, 1: ?string}
     */
    private function buildModel(int $userId, array $row, array $accounts, array $categories): array
    {
        $date = TransactionCsv::parseDate($row['date']);
        if ($date === null) {
            return [null, 'Дата «' . $row['date'] . '»: нужен формат ГГГГ-ММ-ДД или ДД.ММ.ГГГГ.'];
        }

        $type = TransactionCsv::parseType($row['type']);
        if ($type === null) {
            return [null, 'Тип «' . $row['type'] . '»: нужно «расход» или «доход».'];
        }

        $amount = TransactionCsv::parseAmount($row['amount']);
        if ($amount === null) {
            return [null, 'Сумма «' . $row['amount'] . '»: нужно положительное число не больше чем с двумя знаками после запятой.'];
        }

        $account = $accounts[mb_strtolower($row['account'])] ?? null;
        if ($account === null) {
            return [null, 'Счёт «' . $row['account'] . '» не найден.'];
        }

        $currency = strtoupper($row['currency'] ?? '');
        if ($currency !== '' && $currency !== $account->currency) {
            return [null, "Валюта {$currency} не совпадает с валютой счёта «{$account->name}» ({$account->currency})."];
        }

        $category = $categories[$type . '|' . mb_strtolower($row['category'])] ?? null;
        if ($category === null) {
            $label = $type === Category::TYPE_INCOME ? 'доходов' : 'расходов';

            return [null, 'Категория «' . $row['category'] . '» среди категорий ' . $label . ' не найдена.'];
        }

        $model = new Transaction([
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => $amount,
            'type' => $type,
            'occurred_at' => $date,
            'note' => ($row['note'] ?? '') === '' ? null : $row['note'],
        ]);
        $model->user_id = $userId;

        if (!$model->validate()) {
            return [null, implode(' ', $model->getFirstErrors())];
        }

        return [$model, null];
    }

    /**
     * @param array<string, string> $errors
     * @return array<string, string>
     */
    private function limit(array $errors): array
    {
        if (count($errors) <= self::MAX_ERRORS) {
            return $errors;
        }

        $rest = count($errors) - self::MAX_ERRORS;
        $errors = array_slice($errors, 0, self::MAX_ERRORS, true);
        $errors['Ещё'] = "и ещё {$rest} строк с ошибками.";

        return $errors;
    }
}
