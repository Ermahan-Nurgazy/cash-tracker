<?php

namespace app\models;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;

/**
 * Пользователь.
 *
 * @property int $id
 * @property string $email
 * @property string $password_hash
 * @property string $auth_key
 * @property string|null $access_token
 * @property int $created_at
 * @property int $updated_at
 *
 * @property Account[] $accounts
 * @property Category[] $categories
 * @property Transaction[] $transactions
 */
class User extends ActiveRecord implements IdentityInterface
{
    /**
     * Имя таблицы БД.
     *
     * @return string имя таблицы с префиксом подключения
     */
    public static function tableName(): string
    {
        return '{{%user}}';
    }

    /**
     * Находит пользователя по id (для сессии и IdentityInterface).
     *
     * @param int|string $id идентификатор пользователя
     * @return static|null пользователь или null, если не найден
     */
    public static function findIdentity($id): ?static
    {
        return static::findOne(['id' => $id]);
    }

    /**
     * Находит пользователя по Bearer-токену.
     *
     * @param mixed $token токен из заголовка Authorization
     * @param string|null $type тип аутентификатора (не используется)
     * @return static|null пользователь или null, если токен пустой, не строка или неизвестен
     */
    public static function findIdentityByAccessToken($token, $type = null): ?static
    {
        if (!is_string($token) || $token === '') {
            return null;
        }

        return static::findOne(['access_token' => $token]);
    }

    /**
     * Идентификатор пользователя.
     *
     * @return int значение id
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Ключ аутентификации пользователя.
     *
     * @return string значение auth_key
     */
    public function getAuthKey(): string
    {
        return $this->auth_key;
    }

    /**
     * Сравнивает ключ аутентификации за постоянное время.
     *
     * @param string $authKey проверяемый ключ
     * @return bool true, если ключ совпадает
     */
    public function validateAuthKey($authKey): bool
    {
        return hash_equals($this->auth_key, (string) $authKey);
    }

    /**
     * Секреты никогда не попадают в JSON-ответы.
     *
     * @return array<string, mixed> поля ответа без password_hash, auth_key и access_token
     */
    public function fields(): array
    {
        $fields = parent::fields();
        unset($fields['password_hash'], $fields['auth_key'], $fields['access_token']);

        return $fields;
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
     * Правила валидации: email в нижнем регистре, корректный и уникальный.
     *
     * @return array<int, array<int|string, mixed>> правила Yii
     */
    public function rules(): array
    {
        return [
            ['email', 'filter', 'filter' => static fn($value) => is_string($value) ? mb_strtolower(trim($value)) : $value],
            ['email', 'required'],
            ['email', 'string', 'max' => 255],
            ['email', 'email'],
            ['email', 'unique'],
        ];
    }

    /**
     * Подписи атрибутов для сообщений об ошибках.
     *
     * @return array<string, string> атрибут => подпись
     */
    public function attributeLabels(): array
    {
        return [
            'email' => 'Email',
        ];
    }

    /**
     * Сохраняет в password_hash хеш пароля (сам пароль не хранится).
     *
     * @param string $password пароль в открытом виде
     * @return void
     * @throws \yii\base\Exception если не удалось получить случайные данные для соли
     */
    public function setPassword(string $password): void
    {
        $this->password_hash = Yii::$app->security->generatePasswordHash($password);
    }

    /**
     * Проверяет пароль по сохранённому хешу.
     *
     * @param string $password пароль в открытом виде
     * @return bool true, если пароль верный
     * @throws \yii\base\InvalidArgumentException если password_hash пуст или повреждён
     */
    public function validatePassword(string $password): bool
    {
        return Yii::$app->security->validatePassword($password, $this->password_hash);
    }

    /**
     * Генерирует новый случайный auth_key (32 символа).
     *
     * @return void
     * @throws \yii\base\Exception если не удалось получить случайные данные
     */
    public function generateAuthKey(): void
    {
        $this->auth_key = Yii::$app->security->generateRandomString(32);
    }

    /**
     * Генерирует новый случайный access_token (64 символа); прежний перестаёт действовать
     * после сохранения.
     *
     * @return void
     * @throws \yii\base\Exception если не удалось получить случайные данные
     */
    public function generateAccessToken(): void
    {
        $this->access_token = Yii::$app->security->generateRandomString(64);
    }

    /**
     * Связь со счетами пользователя.
     *
     * @return ActiveQuery запрос к счетам (много)
     */
    public function getAccounts(): ActiveQuery
    {
        return $this->hasMany(Account::class, ['user_id' => 'id']);
    }

    /**
     * Связь с категориями пользователя.
     *
     * @return ActiveQuery запрос к категориям (много)
     */
    public function getCategories(): ActiveQuery
    {
        return $this->hasMany(Category::class, ['user_id' => 'id']);
    }

    /**
     * Связь с операциями пользователя.
     *
     * @return ActiveQuery запрос к операциям (много)
     */
    public function getTransactions(): ActiveQuery
    {
        return $this->hasMany(Transaction::class, ['user_id' => 'id']);
    }
}
