<?php

namespace app\components;

use app\models\Category;
use app\models\Transaction;
use DateTimeImmutable;

/**
 * Формат CSV для операций: запись и чтение.
 *
 * Файл: UTF-8 с BOM, разделитель «;» (так его без настроек открывает Excel в русской локали),
 * сумма с десятичной запятой. При чтении принимаются также «,» и табуляция, UTF-8 и Windows-1251,
 * сумма с точкой, даты ГГГГ-ММ-ДД и ДД.ММ.ГГГГ, типы «расход/доход» и «expense/income».
 */
final class TransactionCsv
{
    public const HEADER = ['Дата', 'Тип', 'Сумма', 'Валюта', 'Счёт', 'Категория', 'Заметка'];

    /** Названия столбцов при чтении: канонический ключ => допустимые заголовки */
    private const COLUMNS = [
        'date' => ['дата', 'date'],
        'type' => ['тип', 'type'],
        'amount' => ['сумма', 'amount'],
        'currency' => ['валюта', 'currency'],
        'account' => ['счёт', 'счет', 'account'],
        'category' => ['категория', 'category'],
        'note' => ['заметка', 'комментарий', 'note'],
    ];

    private const REQUIRED = ['date', 'type', 'amount', 'account', 'category'];

    private const TYPES = [
        'расход' => Category::TYPE_EXPENSE,
        'expense' => Category::TYPE_EXPENSE,
        'доход' => Category::TYPE_INCOME,
        'income' => Category::TYPE_INCOME,
    ];

    /**
     * Формирует CSV-файл с операциями.
     *
     * Текстовые ячейки защищены от CSV-инъекции (см. safe()).
     *
     * @param iterable<Transaction> $transactions операции с загруженными account и category
     * @return string содержимое файла: UTF-8 с BOM, разделитель «;»
     */
    public static function export(iterable $transactions): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, self::HEADER, ';', '"', '');

        foreach ($transactions as $transaction) {
            fputcsv($handle, [
                $transaction->occurred_at,
                $transaction->type === Category::TYPE_INCOME ? 'доход' : 'расход',
                str_replace('.', ',', (string) $transaction->amount),
                self::safe($transaction->account->currency ?? ''),
                self::safe($transaction->account->name ?? ''),
                self::safe($transaction->category->name ?? ''),
                self::safe((string) $transaction->note),
            ], ';', '"', '');
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return "\xEF\xBB\xBF" . $csv;
    }

    /**
     * Читает CSV в список строк. Ошибки структуры (нет столбцов, пустой файл) возвращаются
     * в errors, ошибки значений проверяет импортёр, у него есть доступ к счетам и категориям.
     * Пустые строки пропускаются. Кодировка и разделитель определяются автоматически.
     *
     * @param string $raw содержимое файла как есть (UTF-8 с BOM или без, либо Windows-1251)
     * @return array{rows: list<array<string, mixed>>, errors: array<string, string>}
     *         rows — строки данных с ключами столбцов и номером строки line,
     *         errors — ошибки структуры файла (метка => сообщение)
     */
    public static function parse(string $raw): array
    {
        $raw = self::toUtf8($raw);
        $delimiter = self::detectDelimiter($raw);

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $raw);
        rewind($handle);

        $rows = [];
        $map = null;
        $number = 0;

        while (($record = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            $number++;

            if ($record === [null] || self::isBlank($record)) {
                continue;
            }

            if ($map === null) {
                $map = self::mapHeader($record);
                $missing = array_diff(self::REQUIRED, array_keys($map));

                if ($missing !== []) {
                    fclose($handle);

                    return ['rows' => [], 'errors' => [
                        'Заголовок' => 'Нет столбцов: ' . implode(', ', array_map(
                            static fn(string $key) => self::COLUMNS[$key][0],
                            $missing
                        )) . '.',
                    ]];
                }

                continue;
            }

            $row = ['line' => $number];
            foreach ($map as $key => $index) {
                $row[$key] = trim((string) ($record[$index] ?? ''));
            }
            $rows[] = $row;
        }

        fclose($handle);

        if ($map === null) {
            return ['rows' => [], 'errors' => ['Файл' => 'Файл пустой.']];
        }

        return ['rows' => $rows, 'errors' => []];
    }

    /**
     * Приводит дату к формату ГГГГ-ММ-ДД.
     *
     * @param string $value дата в формате ДД.ММ.ГГГГ или ГГГГ-ММ-ДД
     * @return string|null дата ГГГГ-ММ-ДД или null, если формат не подходит или такой даты не существует
     */
    public static function parseDate(string $value): ?string
    {
        foreach (['!Y-m-d', '!d.m.Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);

            if ($date !== false && $date->format(substr($format, 1)) === $value) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    /**
     * Определяет тип операции по тексту ячейки без учёта регистра.
     *
     * @param string $value «расход», «доход», «expense» или «income»
     * @return string|null Category::TYPE_EXPENSE, Category::TYPE_INCOME или null для неизвестного значения
     */
    public static function parseType(string $value): ?string
    {
        return self::TYPES[mb_strtolower($value)] ?? null;
    }

    /**
     * Нормализует сумму: убирает пробелы (в том числе неразрывные), запятую меняет на точку.
     * Например, «1 234,5» → «1234.5».
     *
     * @param string $value сумма из ячейки
     * @return string|null сумма строкой или null, если это не неотрицательное число
     *         до 12 цифр с не более чем двумя знаками после запятой
     */
    public static function parseAmount(string $value): ?string
    {
        $value = str_replace([' ', "\u{00A0}", "\u{202F}"], '', $value);
        $value = str_replace(',', '.', $value);

        return preg_match('/^\d{1,12}(\.\d{1,2})?$/', $value) ? $value : null;
    }

    /**
     * Защита от CSV-инъекции: ячейка, которая начинается с символа формулы, в Excel
     * была бы выполнена. Апостроф делает её обычным текстом.
     *
     * @param string $value текст ячейки
     * @return string исходный текст или текст с апострофом в начале
     */
    private static function safe(string $value): string
    {
        return $value !== '' && strpbrk($value[0], "=+-@\t\r") !== false ? "'" . $value : $value;
    }

    /**
     * Приводит содержимое файла к UTF-8: отбрасывает BOM, а текст не в UTF-8
     * считает Windows-1251.
     *
     * @param string $raw содержимое файла
     * @return string текст в UTF-8 без BOM
     */
    private static function toUtf8(string $raw): string
    {
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }

        // Excel при «Сохранить как CSV» в русской Windows пишет Windows-1251
        return mb_check_encoding($raw, 'UTF-8') ? $raw : mb_convert_encoding($raw, 'UTF-8', 'Windows-1251');
    }

    /**
     * Определяет разделитель по первой строке: берётся тот из «;», «,» и табуляции,
     * который встречается чаще; при отсутствии всех — «;».
     *
     * @param string $raw содержимое файла в UTF-8
     * @return string один символ-разделитель
     */
    private static function detectDelimiter(string $raw): string
    {
        $firstLine = strtok($raw, "\n") ?: '';
        $best = ';';
        $max = 0;

        foreach ([';', ',', "\t"] as $candidate) {
            $count = substr_count($firstLine, $candidate);

            if ($count > $max) {
                $best = $candidate;
                $max = $count;
            }
        }

        return $best;
    }

    /**
     * Проверяет, что все ячейки строки пустые.
     *
     * @param array<int, string|null> $record ячейки строки
     * @return bool true, если в строке нет данных
     */
    private static function isBlank(array $record): bool
    {
        foreach ($record as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Сопоставляет заголовки файла с каноническими ключами столбцов без учёта регистра.
     * Лишние столбцы игнорируются, при повторе заголовка берётся первый.
     *
     * @param array<int, string|null> $header ячейки строки заголовка
     * @return array<string, int> ключ столбца => индекс в строке
     */
    private static function mapHeader(array $header): array
    {
        $map = [];

        foreach ($header as $index => $title) {
            $title = mb_strtolower(trim((string) $title));

            foreach (self::COLUMNS as $key => $names) {
                if (!isset($map[$key]) && in_array($title, $names, true)) {
                    $map[$key] = $index;
                }
            }
        }

        return $map;
    }
}
