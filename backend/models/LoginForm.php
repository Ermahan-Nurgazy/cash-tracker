<?php

namespace app\models;

use Yii;
use yii\base\Model;

/**
 * Форма входа: POST /api/v1/auth/login
 */
class LoginForm extends Model
{
    public $email;
    public $password;

    /**
     * Правила валидации: email приводится к нижнему регистру, оба поля обязательны.
     *
     * @return array<int, array<int|string, mixed>> правила Yii
     */
    public function rules(): array
    {
        return [
            ['email', 'filter', 'filter' => static fn($value) => is_string($value) ? mb_strtolower(trim($value)) : $value],
            [['email', 'password'], 'required'],
            [['email', 'password'], 'string', 'max' => 255],
        ];
    }

    /**
     * Проверяет учётные данные и выдаёт новый токен (прежний перестаёт действовать).
     * Возвращает null при неверных данных. Вызывать после успешного validate().
     *
     * @return User|null пользователь с новым access_token или null, если email или пароль неверны
     * @throws \yii\base\Exception если не удалось сгенерировать токен или хеш
     */
    public function authenticate(): ?User
    {
        $user = User::findOne(['email' => $this->email]);

        if ($user === null) {
            // Тратим столько же времени, как при проверке реального пароля,
            // чтобы по скорости ответа нельзя было определить, есть ли такой email
            Yii::$app->security->generatePasswordHash((string) $this->password);

            return null;
        }

        if (!$user->validatePassword((string) $this->password)) {
            return null;
        }

        $user->generateAccessToken();
        $user->save(false, ['access_token', 'updated_at']);

        return $user;
    }
}
