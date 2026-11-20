<?php

namespace app\modules\v1\controllers;

use app\models\Category;
use DateTimeImmutable;
use Yii;
use yii\db\Query;
use yii\filters\auth\HttpBearerAuth;
use yii\rest\Controller;
use yii\web\BadRequestHttpException;

/**
 * Отчёты по операциям текущего пользователя.
 *
 * Суммы в разных валютах не складываются и не конвертируются: каждый отчёт
 * разбит на блоки по валюте счёта. Суммы приходят строками с двумя знаками.
 *
 * GET /api/v1/reports/categories  суммы по категориям за период
 *     date_from, date_to (по умолчанию текущий месяц), type (expense по умолчанию), account_id
 * GET /api/v1/reports/monthly     динамика по месяцам: доходы, расходы, итог
 *     date_from, date_to (по умолчанию последние 12 месяцев, не больше 60), account_id
 * GET /api/v1/reports/balances    остатки по счетам: начальный остаток + доходы - расходы
 */
class ReportController extends Controller
{
    public $serializer = 'app\components\Serializer';

    use QueryParamsTrait;

    private const MAX_MONTHS = 60;

    /**
     * Подключает Bearer-аутентификацию ко всем действиям.
     *
     * @return array<string, mixed> конфигурации поведений
     */
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator'] = ['class' => HttpBearerAuth::class];

        return $behaviors;
    }

    /**
     * Допустимые HTTP-методы действий.
     *
     * @return array<string, string[]> действие => список методов
     */
    protected function verbs(): array
    {
        return [
            'categories' => ['GET'],
            'monthly' => ['GET'],
            'balances' => ['GET'],
        ];
    }

    /**
     * GET /api/v1/reports/categories: суммы по категориям за период, по блокам валют.
     *
     * Параметры: date_from и date_to (по умолчанию текущий месяц; если задан один — нужен и второй),
     * type (expense по умолчанию), account_id.
     *
     * @return array{type: string, period: array{date_from: string, date_to: string}, currencies: list<array<string, mixed>>}
     *         по каждой валюте: итог, число операций и категории с долей в процентах
     * @throws BadRequestHttpException если параметры неверны, указан только один край периода,
     *         date_from позже date_to или type не expense и не income
     */
    public function actionCategories(): array
    {
        $today = new DateTimeImmutable('today');
        $from = $this->dateParam('date_from');
        $to = $this->dateParam('date_to');

        if ($from === null && $to === null) {
            $from = $today->modify('first day of this month')->format('Y-m-d');
            $to = $today->modify('last day of this month')->format('Y-m-d');
        }

        $this->assertRange($from, $to);

        $type = $this->stringParam('type') ?? Category::TYPE_EXPENSE;

        if (!in_array($type, Category::TYPES, true)) {
            throw new BadRequestHttpException('Параметр type: expense или income.');
        }

        $query = $this->baseQuery($from, $to)
            ->select([
                'currency' => 'a.currency',
                'category_id' => 'c.id',
                'name' => 'c.name',
                'color' => 'c.color',
                'icon' => 'c.icon',
                'total' => 'SUM(t.amount)',
                'count' => 'COUNT(*)',
            ])
            ->innerJoin(['c' => '{{%category}}'], 'c.id = t.category_id')
            ->andWhere(['t.type' => $type])
            ->groupBy(['a.currency', 'c.id', 'c.name', 'c.color', 'c.icon'])
            ->orderBy(['a.currency' => SORT_ASC, 'total' => SORT_DESC, 'c.id' => SORT_ASC]);

        $blocks = [];

        foreach ($query->all() as $row) {
            $blocks[$row['currency']][] = [
                'category_id' => (int) $row['category_id'],
                'name' => $row['name'],
                'color' => $row['color'],
                'icon' => $row['icon'],
                'total' => $this->money($this->cents($row['total'])),
                'count' => (int) $row['count'],
                'cents' => $this->cents($row['total']),
            ];
        }

        $currencies = [];

        foreach ($blocks as $currency => $categories) {
            $sum = array_sum(array_column($categories, 'cents'));
            $count = array_sum(array_column($categories, 'count'));

            foreach ($categories as &$category) {
                $category['share'] = $sum > 0 ? round($category['cents'] * 100 / $sum, 1) : 0.0;
                unset($category['cents']);
            }
            unset($category);

            $currencies[] = [
                'currency' => $currency,
                'total' => $this->money($sum),
                'count' => $count,
                'categories' => $categories,
            ];
        }

        return [
            'type' => $type,
            'period' => ['date_from' => $from, 'date_to' => $to],
            'currencies' => $currencies,
        ];
    }

    /**
     * GET /api/v1/reports/monthly: доходы, расходы и итог по месяцам, по блокам валют.
     *
     * Параметры: date_from и date_to (по умолчанию последние 12 месяцев по сегодня),
     * account_id. Месяцы без операций заполняются нулями.
     *
     * @return array{period: array{date_from: string, date_to: string}, currencies: list<array<string, mixed>>}
     * @throws BadRequestHttpException если параметры неверны, date_from позже date_to
     *         или период длиннее 60 месяцев
     */
    public function actionMonthly(): array
    {
        $from = $this->dateParam('date_from');
        $to = $this->dateParam('date_to');

        if ($to === null) {
            $to = (new DateTimeImmutable('today'))->format('Y-m-d');
        }

        if ($from === null) {
            $from = (new DateTimeImmutable($to))
                ->modify('first day of this month')
                ->modify('-11 months')
                ->format('Y-m-d');
        }

        $this->assertRange($from, $to);

        $months = $this->monthList($from, $to);

        if (count($months) > self::MAX_MONTHS) {
            throw new BadRequestHttpException('Период не должен превышать ' . self::MAX_MONTHS . ' месяцев.');
        }

        $query = $this->baseQuery($from, $to)
            ->select([
                'currency' => 'a.currency',
                'month' => "to_char(t.occurred_at, 'YYYY-MM')",
                'income' => "COALESCE(SUM(t.amount) FILTER (WHERE t.type = 'income'), 0)",
                'expense' => "COALESCE(SUM(t.amount) FILTER (WHERE t.type = 'expense'), 0)",
            ])
            ->groupBy(['a.currency', 'month'])
            ->orderBy(['a.currency' => SORT_ASC, 'month' => SORT_ASC]);

        $byCurrency = [];

        foreach ($query->all() as $row) {
            $byCurrency[$row['currency']][$row['month']] = [
                $this->cents($row['income']),
                $this->cents($row['expense']),
            ];
        }

        $currencies = [];

        foreach ($byCurrency as $currency => $data) {
            $rows = [];
            $totalIncome = 0;
            $totalExpense = 0;

            // Месяцы без операций заполняются нулями, чтобы график не имел разрывов
            foreach ($months as $month) {
                [$income, $expense] = $data[$month] ?? [0, 0];
                $totalIncome += $income;
                $totalExpense += $expense;
                $rows[] = [
                    'month' => $month,
                    'income' => $this->money($income),
                    'expense' => $this->money($expense),
                    'balance' => $this->money($income - $expense),
                ];
            }

            $currencies[] = [
                'currency' => $currency,
                'total_income' => $this->money($totalIncome),
                'total_expense' => $this->money($totalExpense),
                'balance' => $this->money($totalIncome - $totalExpense),
                'months' => $rows,
            ];
        }

        return [
            'period' => ['date_from' => $from, 'date_to' => $to],
            'currencies' => $currencies,
        ];
    }

    /**
     * GET /api/v1/reports/balances: остатки по счетам (начальный баланс + доходы − расходы)
     * и итог по каждой валюте.
     *
     * @return array{accounts: list<array<string, mixed>>, totals: list<array{currency: string, balance: string}>}
     */
    public function actionBalances(): array
    {
        $userId = Yii::$app->user->id;

        $sums = (new Query())
            ->select([
                'account_id',
                'income' => "SUM(amount) FILTER (WHERE type = 'income')",
                'expense' => "SUM(amount) FILTER (WHERE type = 'expense')",
            ])
            ->from('{{%transaction}}')
            ->where(['user_id' => $userId])
            ->groupBy('account_id');

        $rows = (new Query())
            ->select([
                'a.id', 'a.name', 'a.currency', 'a.initial_balance',
                'income' => 'COALESCE(s.income, 0)',
                'expense' => 'COALESCE(s.expense, 0)',
            ])
            ->from(['a' => '{{%account}}'])
            ->leftJoin(['s' => $sums], 's.account_id = a.id')
            ->where(['a.user_id' => $userId])
            ->orderBy(['a.currency' => SORT_ASC, 'a.name' => SORT_ASC, 'a.id' => SORT_ASC])
            ->all();

        $accounts = [];
        $totals = [];

        foreach ($rows as $row) {
            $initial = $this->cents($row['initial_balance']);
            $balance = $initial + $this->cents($row['income']) - $this->cents($row['expense']);
            $totals[$row['currency']] = ($totals[$row['currency']] ?? 0) + $balance;

            $accounts[] = [
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'currency' => $row['currency'],
                'initial_balance' => $this->money($initial),
                'income' => $this->money($this->cents($row['income'])),
                'expense' => $this->money($this->cents($row['expense'])),
                'balance' => $this->money($balance),
            ];
        }

        $currencies = [];

        foreach ($totals as $currency => $sum) {
            $currencies[] = ['currency' => $currency, 'balance' => $this->money($sum)];
        }

        return ['accounts' => $accounts, 'totals' => $currencies];
    }

    /**
     * Операции текущего пользователя за период; account_id необязателен.
     * Счёт соединяется всегда: он нужен для валюты.
     *
     * @param string $from начало периода, ГГГГ-ММ-ДД (включительно)
     * @param string $to конец периода, ГГГГ-ММ-ДД (включительно)
     * @return Query запрос с алиасами t (операция) и a (счёт)
     * @throws BadRequestHttpException если account_id не целое число
     */
    private function baseQuery(string $from, string $to): Query
    {
        $query = (new Query())
            ->from(['t' => '{{%transaction}}'])
            ->innerJoin(['a' => '{{%account}}'], 'a.id = t.account_id')
            ->where(['t.user_id' => Yii::$app->user->id])
            ->andWhere(['>=', 't.occurred_at', $from])
            ->andWhere(['<=', 't.occurred_at', $to]);

        if (($accountId = $this->intParam('account_id')) !== null) {
            $query->andWhere(['t.account_id' => $accountId]);
        }

        return $query;
    }

    /**
     * Проверяет, что период задан полностью и границы идут по порядку.
     *
     * @param string|null $from начало периода
     * @param string|null $to конец периода
     * @return void
     * @throws BadRequestHttpException если не указана одна из границ или $from позже $to
     */
    private function assertRange(?string $from, ?string $to): void
    {
        if ($from === null || $to === null) {
            throw new BadRequestHttpException('Укажите оба параметра: date_from и date_to.');
        }

        if ($from > $to) {
            throw new BadRequestHttpException('date_from не может быть позже date_to.');
        }
    }

    /**
     * Перечисляет месяцы периода. Список обрывается на MAX_MONTHS + 1 элементе,
     * чтобы вызывающий мог отклонить слишком длинный период, не строя огромный массив.
     *
     * @param string $from начало периода, ГГГГ-ММ-ДД
     * @param string $to конец периода, ГГГГ-ММ-ДД
     * @return string[] месяцы в формате Y-m от месяца date_from до месяца date_to
     */
    private function monthList(string $from, string $to): array
    {
        $cursor = (new DateTimeImmutable($from))->modify('first day of this month');
        $end = (new DateTimeImmutable($to))->modify('first day of this month');
        $months = [];

        while ($cursor <= $end && count($months) <= self::MAX_MONTHS) {
            $months[] = $cursor->format('Y-m');
            $cursor = $cursor->modify('+1 month');
        }

        return $months;
    }

    /**
     * Сумма в копейках целым числом: складывать так надёжнее, чем float.
     *
     * @param string|int|float|null $value значение numeric из БД (null считается нулём)
     * @return int сумма в копейках, может быть отрицательной
     */
    private function cents(string|int|float|null $value): int
    {
        if ($value === null) {
            return 0;
        }

        // Разбор строки numeric без float, чтобы не терять точность
        $value = (string) $value;
        $negative = str_starts_with($value, '-');
        [$whole, $fraction] = array_pad(explode('.', ltrim($value, '-'), 2), 2, '0');
        $cents = (int) $whole * 100 + (int) str_pad(substr($fraction, 0, 2), 2, '0');

        return $negative ? -$cents : $cents;
    }

    /**
     * Форматирует копейки как строку с двумя знаками («1234.50», «-5.00»).
     *
     * @param int $cents сумма в копейках, может быть отрицательной
     * @return string сумма строкой, без превращения в float
     */
    private function money(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);

        return sprintf('%s%d.%02d', $sign, intdiv($cents, 100), $cents % 100);
    }
}
