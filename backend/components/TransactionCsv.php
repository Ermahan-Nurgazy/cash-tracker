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
     * @param iterable<Transaction> $transactions операции с загруженными account и category
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
     *
     * @return array{rows: list<array<string, mixed>>, errors: array<string, string>}
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

    /** ДД.ММ.ГГГГ или ГГГГ-ММ-ДД → ГГГГ-ММ-ДД; null, если даты не существует. */
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

    public static function parseType(string $value): ?string
    {
        return self::TYPES[mb_strtolower($value)] ?? null;
    }

    /** «1 234,5» → «1234.5»; null, если это не сумма с точностью до копеек. */
    public static function parseAmount(string $value): ?string
    {
        $value = str_replace([' ', "\u{00A0}", "\u{202F}"], '', $value);
        $value = str_replace(',', '.', $value);

        return preg_match('/^\d{1,12}(\.\d{1,2})?$/', $value) ? $value : null;
    }

    /**
     * Защита от CSV-инъекции: ячейка, которая начинается с символа формулы, в Excel
     * была бы выполнена. Апостроф делает её обычным текстом.
     */
    private static function safe(string $value): string
    {
        return $value !== '' && strpbrk($value[0], "=+-@\t\r") !== false ? "'" . $value : $value;
    }

    private static function toUtf8(string $raw): string
    {
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }

        // Excel при «Сохранить как CSV» в русской Windows пишет Windows-1251
        return mb_check_encoding($raw, 'UTF-8') ? $raw : mb_convert_encoding($raw, 'UTF-8', 'Windows-1251');
    }

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

    /** @param array<int, string|null> $record */
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
     * @param array<int, string|null> $header
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
