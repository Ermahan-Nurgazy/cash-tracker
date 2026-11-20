<?php

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Счёт пользователя.
 *
 * user_id задаётся на сервере из текущего пользователя, а не из тела запроса:
 * в контроллере присваивать его нужно после load().
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $currency Код валюты ISO 4217, например KZT
 * @property string $initial_balance Строка, чтобы не терять точность numeric(14,2)
 * @property int $created_at
 * @property int $updated_at
 *
 * @property User $user
 * @property Transaction[] $transactions
 */
class Account extends ActiveRecord
{
    use OwnedByUserTrait;

    /**
     * Имя таблицы БД.
     *
     * @return string имя таблицы с префиксом подключения
     */
    public static function tableName(): string
    {
        return '{{%account}}';
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
     * Правила валидации: название уникально в пределах пользователя,
     * валюта — три латинские буквы в верхнем регистре.
     *
     * @return array<int, array<int|string, mixed>> правила Yii
     */
    public function rules(): array
    {
        return [
            [['name', 'currency'], 'trim'],
            ['currency', 'filter', 'filter' => static fn($value) => is_string($value) ? strtoupper($value) : $value],
            [['user_id', 'name', 'currency'], 'required'],
            ['user_id', 'integer'],
            ['user_id', 'exist', 'targetClass' => User::class, 'targetAttribute' => 'id'],
            ['name', 'string', 'max' => 100],
            ['name', 'unique', 'targetAttribute' => ['user_id', 'name'], 'message' => 'Счёт с таким названием уже есть.'],
            ['currency', 'match', 'pattern' => '/^[A-Z]{3}$/', 'message' => 'Код валюты: три латинские буквы, например KZT.'],
            ['currency', 'validateCurrencyChange'],
            ['initial_balance', 'number', 'min' => -999999999999.99, 'max' => 999999999999.99],
            ['initial_balance', 'default', 'value' => 0],
        ];
    }

    /**
     * Валюту нельзя менять, если по счёту уже есть операции: суммы потеряли бы смысл.
     *
     * Встроенный валидатор: добавляет ошибку к атрибуту, исключений не бросает.
     *
     * @param string $attribute проверяемый атрибут (currency)
     * @return void
     */
    public function validateCurrencyChange(string $attribute): void
    {
        if (!$this->isNewRecord && $this->isAttributeChanged($attribute) && $this->getTransactions()->exists()) {
            $this->addError($attribute, 'Нельзя изменить валюту счёта, по которому есть операции.');
        }
    }

    /**
     * Подписи атрибутов для сообщений об ошибках.
     *
     * @return array<string, string> атрибут => подпись
     */
    public function attributeLabels(): array
    {
        return [
            'name' => 'Название',
            'currency' => 'Валюта',
            'initial_balance' => 'Начальный баланс',
        ];
    }

    /**
     * Связь с владельцем счёта.
     *
     * @return ActiveQuery запрос к пользователю (один)
     */
    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    /**
     * Связь с операциями по счёту.
     *
     * @return ActiveQuery запрос к операциям (много)
     */
    public function getTransactions(): ActiveQuery
    {
        return $this->hasMany(Transaction::class, ['account_id' => 'id']);
    }
}
