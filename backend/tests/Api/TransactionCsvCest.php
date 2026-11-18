<?php

namespace Tests\Api;

use Tests\Support\ApiTester;

class TransactionCsvCest extends ApiBase
{
    private function importCsv(ApiTester $I, string $csv): void
    {
        $I->haveHttpHeader('Content-Type', 'text/csv');
        $I->sendPost(self::API . '/transactions/import', $csv);
        $I->haveHttpHeader('Content-Type', 'application/json');
    }

    private function body(ApiTester $I): string
    {
        return $I->grabResponse();
    }

    public function exportContainsFilteredRowsWithBomAndHeaders(ApiTester $I): void
    {
        $this->registerAndAuth($I);
        $account = $this->createAccount($I, 'Карта', 'KZT');
        $food = $this->createCategory($I, 'Еда');
        $salary = $this->createCategory($I, 'Зарплата', 'income');
        $this->createTransaction($I, $account, $food, '1234.50', 'expense', '2026-11-10', 'Обед; "с коллегами"');
        $this->createTransaction($I, $account, $salary, '5000.00', 'income', '2026-11-01');
        $this->createTransaction($I, $account, $food, '10.00', 'expense', '2026-10-05', '=SUM(A1)');

        $I->sendGet(self::API . '/transactions/export?date_from=2026-11-01&date_to=2026-11-30');
        $I->seeResponseCodeIs(200);
        $I->seeHttpHeader('Content-Type', 'text/csv; charset=UTF-8');
        $csv = $this->body($I);

        $I->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $lines = explode("\n", trim(substr($csv, 3)));
        $I->assertSame('Дата;Тип;Сумма;Валюта;Счёт;Категория;Заметка', $lines[0]);
        $I->assertCount(3, $lines);
        // По возрастанию даты, сумма с запятой, спецсимволы в кавычках
        $I->assertSame('2026-11-01;доход;5000,00;KZT;Карта;Зарплата;', $lines[1]);
        $I->assertSame('2026-11-10;расход;1234,50;KZT;Карта;Еда;"Обед; ""с коллегами"""', $lines[2]);
    }

    public function exportNeutralizesFormulas(ApiTester $I): void
    {
        $this->registerAndAuth($I);
        $account = $this->createAccount($I);
        $food = $this->createCategory($I);
        $this->createTransaction($I, $account, $food, '10.00', 'expense', '2026-10-05', '=SUM(A1)');

        $I->sendGet(self::API . '/transactions/export');
        $I->assertStringContainsString(";'=SUM(A1)", $this->body($I));
    }

    public function exportValidatesFiltersAndIsolatesUsers(ApiTester $I): void
    {
        $this->registerAndAuth($I);
        $account = $this->createAccount($I);
        $food = $this->createCategory($I);
        $this->createTransaction($I, $account, $food, '10.00');

        $I->sendGet(self::API . '/transactions/export?type=bad');
        $I->seeResponseCodeIs(400);

        $this->registerAndAuth($I);
        $I->sendGet(self::API . '/transactions/export');
        $I->seeResponseCodeIs(200);
        $I->assertCount(1, explode("\n", trim(substr($this->body($I), 3))));
    }

    public function importCreatesTransactions(ApiTester $I): void
    {
        $this->registerAndAuth($I);
        $this->createAccount($I, 'Карта', 'KZT');
        $this->createCategory($I, 'Еда');
        $this->createCategory($I, 'Зарплата', 'income');

        $csv = "\xEF\xBB\xBFДата;Тип;Сумма;Валюта;Счёт;Категория;Заметка\r\n"
            . "2026-11-10;расход;1 234,50;KZT;карта;ЕДА;\"Обед; \"\"с коллегами\"\"\"\r\n"
            . "\r\n"
            . "01.11.2026;Доход;5000;;Карта;Зарплата;\r\n";

        $this->importCsv($I, $csv);
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['imported' => 2]);

        $I->sendGet(self::API . '/transactions?sort=occurred_at');
        $I->assertSame(['5000.00', '1234.50'], $I->grabDataFromResponseByJsonPath('$[*].amount'));
        $I->assertSame(['2026-11-01', '2026-11-10'], $I->grabDataFromResponseByJsonPath('$[*].occurred_at'));
        $I->assertSame('Обед; "с коллегами"', $I->grabDataFromResponseByJsonPath('$[1].note')[0]);
    }

    public function importAcceptsCommaDelimiterAndEnglishNames(ApiTester $I): void
    {
        $this->registerAndAuth($I);
        $this->createAccount($I, 'Cash', 'USD');
        $this->createCategory($I, 'Food');

        $this->importCsv($I, "date,type,amount,account,category\n2026-11-10,expense,12.5,Cash,Food\n");

        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['imported' => 1]);
    }

    public function importReadsWindows1251(ApiTester $I): void
    {
        $this->registerAndAuth($I);
        $this->createAccount($I, 'Наличные', 'KZT');
        $this->createCategory($I, 'Еда');

        $csv = mb_convert_encoding("Дата;Тип;Сумма;Счёт;Категория\n2026-11-10;расход;100;Наличные;Еда\n", 'Windows-1251', 'UTF-8');
        $this->importCsv($I, $csv);

        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['imported' => 1]);
    }

    public function importIsAllOrNothingAndReportsRows(ApiTester $I): void
    {
        $this->registerAndAuth($I);
        $this->createAccount($I, 'Карта', 'KZT');
        $this->createCategory($I, 'Еда');
        $this->createCategory($I, 'Зарплата', 'income');

        $csv = "Дата;Тип;Сумма;Валюта;Счёт;Категория\n"
            . "2026-11-10;расход;100;KZT;Карта;Еда\n"      // верная
            . "2026-13-10;расход;100;KZT;Карта;Еда\n"      // строка 3: дата
            . "2026-11-10;перевод;100;KZT;Карта;Еда\n"     // строка 4: тип
            . "2026-11-10;расход;-5;KZT;Карта;Еда\n"       // строка 5: сумма
            . "2026-11-10;расход;1,234;KZT;Карта;Еда\n"    // строка 6: три знака
            . "2026-11-10;расход;10;KZT;Нет такого;Еда\n"  // строка 7: счёт
            . "2026-11-10;расход;10;USD;Карта;Еда\n"       // строка 8: валюта
            . "2026-11-10;расход;10;KZT;Карта;Зарплата\n"; // строка 9: категория дохода у расхода

        $this->importCsv($I, $csv);

        $I->seeResponseCodeIs(422);
        $errors = $I->grabDataFromResponseByJsonPath('$.errors')[0];
        $I->assertSame(['Строка 3', 'Строка 4', 'Строка 5', 'Строка 6', 'Строка 7', 'Строка 8', 'Строка 9'], array_keys($errors));
        $I->assertStringContainsString('Дата', $errors['Строка 3']);
        $I->assertStringContainsString('не найден', $errors['Строка 7']);
        $I->assertStringContainsString('не совпадает', $errors['Строка 8']);

        // Верная строка тоже не сохранена
        $I->sendGet(self::API . '/transactions');
        $I->assertCount(0, $I->grabDataFromResponseByJsonPath('$[*]'));
    }

    public function importRejectsBadFiles(ApiTester $I): void
    {
        $this->registerAndAuth($I);

        $this->importCsv($I, '');
        $I->seeResponseCodeIs(400);

        $this->importCsv($I, "Дата;Тип\n2026-11-10;расход\n");
        $I->seeResponseCodeIs(422);
        $I->seeResponseContainsJson(['errors' => ['Заголовок' => 'Нет столбцов: сумма, счёт, категория.']]);

        $this->importCsv($I, "Дата;Тип;Сумма;Счёт;Категория\n");
        $I->seeResponseCodeIs(422);
        $I->seeResponseContainsJson(['errors' => ['Файл' => 'В файле нет операций.']]);

        $this->importCsv($I, str_repeat('x', 1048577));
        $I->seeResponseCodeIs(413);
    }

    public function importCannotUseOtherUsersAccounts(ApiTester $I): void
    {
        $this->registerAndAuth($I);
        $this->createAccount($I, 'Чужой', 'KZT');
        $this->createCategory($I, 'Еда');

        $this->registerAndAuth($I);
        $this->createCategory($I, 'Еда');
        $this->importCsv($I, "Дата;Тип;Сумма;Счёт;Категория\n2026-11-10;расход;10;Чужой;Еда\n");

        $I->seeResponseCodeIs(422);
        $I->assertStringContainsString('не найден', $I->grabDataFromResponseByJsonPath('$.errors["Строка 2"]')[0]);
    }

    public function exportedFileCanBeImportedBack(ApiTester $I): void
    {
        $this->registerAndAuth($I);
        $account = $this->createAccount($I, 'Карта', 'KZT');
        $food = $this->createCategory($I, 'Еда');
        $this->createTransaction($I, $account, $food, '99.90', 'expense', '2026-11-10', 'Заметка');

        $I->sendGet(self::API . '/transactions/export');
        $csv = $this->body($I);

        $this->importCsv($I, $csv);
        $I->seeResponseCodeIs(200);

        $I->sendGet(self::API . '/transactions');
        $I->assertSame(['99.90', '99.90'], $I->grabDataFromResponseByJsonPath('$[*].amount'));
    }

    public function requiresAuthAndCorrectMethods(ApiTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendGet(self::API . '/transactions/export');
        $I->seeResponseCodeIs(401);

        $this->registerAndAuth($I);
        $I->sendPost(self::API . '/transactions/export');
        $I->seeResponseCodeIs(405);
        $I->sendGet(self::API . '/transactions/import');
        $I->seeResponseCodeIs(405);
    }
}
