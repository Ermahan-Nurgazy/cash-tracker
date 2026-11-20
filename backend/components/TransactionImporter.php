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
     * Импортирует операции пользователя из CSV в одной транзакции БД.
     *
     * Ошибки данных (структура файла, значения, превышение лимитов) не бросают исключений,
     * а возвращаются в errors; в этом случае ничего не сохраняется.
     *
     * @param int $userId владелец импортируемых операций
     * @param string $raw содержимое CSV-файла
     * @return array{imported: int, errors: array<string, string>}
     *         imported — число сохранённых операций, errors — метка («Строка 5») => сообщение
     * @throws \Throwable если сохранение в БД не удалось (транзакция откатывается)
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
     * Проверяет строку CSV и собирает из неё несохранённую операцию.
     *
     * @param int $userId владелец операции
     * @param array<string, mixed> $row строка из TransactionCsv::parse()
     * @param array<string, Account> $accounts счета пользователя по имени в нижнем регистре
     * @param array<string, Category> $categories категории пользователя по ключу «тип|имя в нижнем регистре»
     * @return array{0: ?Transaction, 1: ?string} пара [модель, null] при успехе или [null, текст ошибки]
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
     * Ограничивает список ошибок: сверх MAX_ERRORS добавляется одна итоговая запись.
     *
     * @param array<string, string> $errors все ошибки (метка => сообщение)
     * @return array<string, string> не более MAX_ERRORS ошибок и запись «Ещё», если часть отброшена
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
