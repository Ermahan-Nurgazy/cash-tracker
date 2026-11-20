<?php

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\Query;

/**
 * Бюджет: лимит расходов по категории на месяц в одной валюте.
 *
 * В API месяц передаётся и отдаётся как `month` (YYYY-MM), в БД хранится первым числом
 * месяца (period_month). Расход считается по операциям типа expense со счетов этой валюты.
 *
 * @property int $id
 * @property int $user_id
 * @property int $category_id
 * @property string $period_month Первое число месяца, Y-m-d
 * @property string $currency
 * @property string $limit Строка, чтобы не терять точность numeric(14,2)
 * @property int $created_at
 * @property int $updated_at
 *
 * @property Category $category
 */
class Budget extends ActiveRecord
{
    use OwnedByUserTrait {
        fields as private fieldsWithoutOwner;
    }

    public const STATUS_OK = 'ok';
    public const STATUS_WARNING = 'warning';
    public const STATUS_EXCEEDED = 'exceeded';

    /** С какого процента расхода бюджет считается «на исходе» */
    public const WARNING_PERCENT = 80;

    /** @var string|null Месяц в формате YYYY-MM (вход и выход API) */
    public $month;

    private ?int $spentCents = null;

    /**
     * Имя таблицы БД.
     *
     * @return string имя таблицы с префиксом подключения
     */
    public static function tableName(): string
    {
        return '{{%budget}}';
    }

    /**
     * Поведения модели: автоматические created_at и updated_at.
     *
     * @return array<int, mixed> конфигурации поведений
     */
    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }

    /**
     * Правила валидации: категория только расходная и своя, месяц в формате ГГГГ-ММ,
     * бюджет уникален для категории, месяца и валюты.
     *
     * @return array<int, array<int|string, mixed>> правила Yii
     */
    public function rules(): array
    {
        return [
            [['month', 'currency'], 'trim'],
            ['currency', 'filter', 'filter' => static fn($value) => is_string($value) ? strtoupper($value) : $value],
            [['user_id', 'category_id', 'month', 'currency', 'limit'], 'required'],
            [['user_id', 'category_id'], 'integer'],
            ['user_id', 'exist', 'targetClass' => User::class, 'targetAttribute' => 'id'],
            [
                'category_id',
                'exist',
                'targetClass' => Category::class,
                'targetAttribute' => ['category_id' => 'id', 'user_id' => 'user_id'],
                'filter' => ['type' => Category::TYPE_EXPENSE],
                'message' => 'Расходная категория не найдена.',
            ],
            ['month', 'match', 'pattern' => '/^\d{4}-(0[1-9]|1[0-2])$/', 'message' => 'Месяц в формате ГГГГ-ММ.'],
            ['currency', 'match', 'pattern' => '/^[A-Z]{3}$/', 'message' => 'Код валюты: три латинские буквы, например KZT.'],
            ['limit', 'number', 'min' => 0.01, 'max' => 999999999999.99],
            [
                'category_id',
                'unique',
                'targetAttribute' => ['user_id', 'category_id', 'period_month', 'currency'],
                'message' => 'Бюджет на эту категорию, месяц и валюту уже есть.',
                'skipOnError' => true,
            ],
        ];
    }

    /**
     * Переносит month (ГГГГ-ММ) в period_month (первое число месяца) до валидации,
     * чтобы проверка уникальности работала по колонке БД.
     *
     * @return bool можно ли продолжать валидацию
     */
    public function beforeValidate(): bool
    {
        if (is_string($this->month) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', trim($this->month))) {
            $this->period_month = trim($this->month) . '-01';
        }

        return parent::beforeValidate();
    }

    /**
     * После загрузки из БД заполняет month и сбрасывает кэш расхода.
     *
     * @return void
     */
    public function afterFind(): void
    {
        parent::afterFind();
        $this->syncDerived();
    }

    /**
     * После сохранения запись перечитывается (OwnedByUserTrait): пересчитываем производные значения.
     *
     * @return void
     */
    public function afterRefresh(): void
    {
        parent::afterRefresh();
        $this->syncDerived();
    }

    /**
     * Вычисляет month из period_month и сбрасывает закэшированную сумму расхода.
     *
     * @return void
     */
    private function syncDerived(): void
    {
        $this->month = substr((string) $this->period_month, 0, 7);
        $this->spentCents = null;
    }

    /**
     * Поля JSON-ответа: month вместо period_month, краткая category и расчётные
     * spent, remaining (может быть отрицательным), percent и status.
     *
     * @return array<string, mixed> поля ответа (user_id скрыт)
     */
    public function fields(): array
    {
        $fields = $this->fieldsWithoutOwner();
        unset($fields['period_month']);

        $fields['month'] = 'month';
        $fields['category'] = static fn(self $model) => $model->category === null ? null : [
            'id' => $model->category->id,
            'name' => $model->category->name,
            'color' => $model->category->color,
            'icon' => $model->category->icon,
        ];
        $fields['spent'] = fn() => self::money($this->getSpentCents());
        $fields['remaining'] = fn() => self::money($this->limitCents() - $this->getSpentCents());
        $fields['percent'] = fn() => $this->percent();
        $fields['status'] = fn() => $this->status();

        return $fields;
    }

    /**
     * Потрачено по категории за месяц в валюте бюджета (в копейках).
     *
     * Считается суммой операций типа expense по счетам этой валюты; результат кэшируется
     * до следующей загрузки или перечитывания записи.
     *
     * @return int сумма расходов в копейках
     */
    public function getSpentCents(): int
    {
        if ($this->spentCents !== null) {
            return $this->spentCents;
        }

        $month = substr((string) $this->period_month, 0, 7);
        $from = $month . '-01';
        $to = date('Y-m-t', strtotime($from));

        $sum = (new Query())
            ->from(['t' => '{{%transaction}}'])
            ->innerJoin(['a' => '{{%account}}'], 'a.id = t.account_id')
            ->where([
                't.user_id' => $this->user_id,
                't.category_id' => $this->category_id,
                't.type' => Transaction::TYPE_EXPENSE,
                'a.currency' => $this->currency,
            ])
            ->andWhere(['between', 't.occurred_at', $from, $to])
            ->sum('t.amount');

        return $this->spentCents = self::cents($sum);
    }

    /**
     * Лимит бюджета в копейках.
     *
     * @return int лимит в копейках
     */
    public function limitCents(): int
    {
        return self::cents($this->limit);
    }

    /**
     * Доля лимита в процентах, округлённая до целого; может быть больше 100.
     *
     * @return int процент израсходованного лимита (0, если лимит нулевой)
     */
    public function percent(): int
    {
        $limit = $this->limitCents();

        return $limit > 0 ? (int) round($this->getSpentCents() * 100 / $limit) : 0;
    }

    /**
     * Состояние бюджета: превышен, на исходе (от WARNING_PERCENT %) или в норме.
     *
     * @return string одна из констант STATUS_EXCEEDED, STATUS_WARNING, STATUS_OK
     */
    public function status(): string
    {
        $spent = $this->getSpentCents();
        $limit = $this->limitCents();

        if ($spent > $limit) {
            return self::STATUS_EXCEEDED;
        }

        return $spent * 100 >= $limit * self::WARNING_PERCENT ? self::STATUS_WARNING : self::STATUS_OK;
    }

    /**
     * Подписи атрибутов для сообщений об ошибках.
     *
     * @return array<string, string> атрибут => подпись
     */
    public function attributeLabels(): array
    {
        return [
            'category_id' => 'Категория',
            'month' => 'Месяц',
            'currency' => 'Валюта',
            'limit' => 'Лимит',
        ];
    }

    /**
     * Связь с категорией бюджета.
     *
     * @return ActiveQuery запрос к категории (один)
     */
    public function getCategory(): ActiveQuery
    {
        return $this->hasOne(Category::class, ['id' => 'category_id']);
    }

    /**
     * Переводит сумму в копейки с округлением.
     *
     * @param string|int|float|null $value сумма в основных единицах (null считается нулём)
     * @return int сумма в копейках
     */
    private static function cents(string|int|float|null $value): int
    {
        return $value === null ? 0 : (int) round(((float) $value) * 100);
    }

    /**
     * Форматирует копейки как строку с двумя знаками («1234.50», «-5.00»).
     *
     * @param int $cents сумма в копейках, может быть отрицательной
     * @return string сумма строкой, без превращения в float
     */
    private static function money(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);

        return sprintf('%s%d.%02d', $sign, intdiv($cents, 100), $cents % 100);
    }
}
