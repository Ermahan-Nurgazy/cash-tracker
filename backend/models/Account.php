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
    public static function tableName(): string
    {
        return '{{%account}}';
    }

    public function behaviors(): array
    {
        return [
            TimestampBehavior::class,
        ];
    }

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
            ['initial_balance', 'number', 'min' => -999999999999.99, 'max' => 999999999999.99],
            ['initial_balance', 'default', 'value' => 0],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'name' => 'Название',
            'currency' => 'Валюта',
            'initial_balance' => 'Начальный баланс',
        ];
    }

    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getTransactions(): ActiveQuery
    {
        return $this->hasMany(Transaction::class, ['account_id' => 'id']);
    }
}
