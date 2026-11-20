<?php

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Операция: расход или доход.
 *
 * Сумма всегда положительная, направление задаёт type.
 * user_id задаётся на сервере из текущего пользователя, а не из тела запроса:
 * в контроллере присваивать его нужно после load().
 *
 * @property int $id
 * @property int $user_id
 * @property int $account_id
 * @property int $category_id
 * @property string $amount Строка, чтобы не терять точность numeric(14,2)
 * @property string $type expense|income
 * @property string $occurred_at Дата в формате Y-m-d
 * @property string|null $note
 * @property int $created_at
 * @property int $updated_at
 *
 * @property User $user
 * @property Account $account
 * @property Category $category
 */
class Transaction extends ActiveRecord
{
    use OwnedByUserTrait {
        fields as private fieldsWithoutOwner;
    }

    public const TYPE_EXPENSE = Category::TYPE_EXPENSE;
    public const TYPE_INCOME = Category::TYPE_INCOME;

    public const TYPES = Category::TYPES;

    /**
     * Имя таблицы БД.
     *
     * @return string имя таблицы с префиксом подключения
     */
    public static function tableName(): string
    {
        return '{{%transaction}}';
    }

    /**
     * Поведения модели: автоматические created_at и updated_at.
     *
     * @return array<int, mixed> конфигурации поведений
     */
    public function behaviors(): array
    {
        return [
            TimestampBehavior::class,
        ];
    }

    /**
     * Правила валидации: счёт и категория должны принадлежать тому же пользователю,
     * сумма положительная, дата в формате ГГГГ-ММ-ДД.
     *
     * @return array<int, array<int|string, mixed>> правила Yii
     */
    public function rules(): array
    {
        return [
            [['type', 'occurred_at', 'note'], 'trim'],
            ['note', 'default', 'value' => null],
            [['user_id', 'account_id', 'category_id', 'amount', 'type', 'occurred_at'], 'required'],
            [['user_id', 'account_id', 'category_id'], 'integer'],
            ['user_id', 'exist', 'targetClass' => User::class, 'targetAttribute' => 'id'],
            [
                'account_id',
                'exist',
                'targetClass' => Account::class,
                'targetAttribute' => ['account_id' => 'id', 'user_id' => 'user_id'],
                'message' => 'Счёт не найден.',
            ],
            [
                'category_id',
                'exist',
                'targetClass' => Category::class,
                'targetAttribute' => ['category_id' => 'id', 'user_id' => 'user_id'],
                'message' => 'Категория не найдена.',
            ],
            ['amount', 'number', 'min' => 0.01, 'max' => 999999999999.99],
            ['type', 'in', 'range' => self::TYPES],
            ['type', 'validateTypeMatchesCategory'],
            ['occurred_at', 'date', 'format' => 'php:Y-m-d'],
            ['note', 'string', 'max' => 2000],
        ];
    }

    /**
     * Тип операции должен совпадать с типом выбранной категории.
     *
     * Встроенный валидатор: добавляет ошибку к атрибуту, исключений не бросает.
     * Если категория не найдена или уже есть ошибка в category_id, проверка пропускается.
     *
     * @param string $attribute проверяемый атрибут (type)
     * @return void
     */
    public function validateTypeMatchesCategory(string $attribute): void
    {
        if ($this->hasErrors('category_id') || $this->category_id === null) {
            return;
        }

        $categoryType = Category::find()
            ->select('type')
            ->where(['id' => $this->category_id, 'user_id' => $this->user_id])
            ->scalar();

        if ($categoryType !== false && $categoryType !== $this->type) {
            $this->addError($attribute, 'Тип операции не совпадает с типом категории.');
        }
    }

    /**
     * Вместе с id в ответ всегда входят краткие account и category: фронтенду
     * не нужны дополнительные запросы, чтобы показать названия, цвета и валюту.
     *
     * @return array<string, mixed> поля JSON-ответа (user_id скрыт)
     */
    public function fields(): array
    {
        $fields = $this->fieldsWithoutOwner();

        $fields['account'] = static fn(self $model) => $model->account === null ? null : [
            'id' => $model->account->id,
            'name' => $model->account->name,
            'currency' => $model->account->currency,
        ];

        $fields['category'] = static fn(self $model) => $model->category === null ? null : [
            'id' => $model->category->id,
            'name' => $model->category->name,
            'type' => $model->category->type,
            'color' => $model->category->color,
            'icon' => $model->category->icon,
        ];

        return $fields;
    }

    /**
     * Подписи атрибутов для сообщений об ошибках.
     *
     * @return array<string, string> атрибут => подпись
     */
    public function attributeLabels(): array
    {
        return [
            'account_id' => 'Счёт',
            'category_id' => 'Категория',
            'amount' => 'Сумма',
            'type' => 'Тип',
            'occurred_at' => 'Дата',
            'note' => 'Заметка',
        ];
    }

    /**
     * Связь с владельцем операции.
     *
     * @return ActiveQuery запрос к пользователю (один)
     */
    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    /**
     * Связь со счётом операции.
     *
     * @return ActiveQuery запрос к счёту (один)
     */
    public function getAccount(): ActiveQuery
    {
        return $this->hasOne(Account::class, ['id' => 'account_id']);
    }

    /**
     * Связь с категорией операции.
     *
     * @return ActiveQuery запрос к категории (один)
     */
    public function getCategory(): ActiveQuery
    {
        return $this->hasOne(Category::class, ['id' => 'category_id']);
    }
}
