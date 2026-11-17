<?php

namespace app\models;

use yii\base\Model;

/**
 * Форма регистрации: POST /api/v1/auth/register
 */
class RegisterForm extends Model
{
    public $email;
    public $password;

    public function rules(): array
    {
        return [
            ['email', 'filter', 'filter' => static fn($value) => is_string($value) ? mb_strtolower(trim($value)) : $value],
            [['email', 'password'], 'required'],
            ['email', 'string', 'max' => 255],
            ['email', 'email'],
            ['email', 'unique', 'targetClass' => User::class, 'targetAttribute' => 'email', 'message' => 'Этот email уже зарегистрирован.'],
            // 72 байта: предел bcrypt, дальше пароль молча обрезается
            ['password', 'string', 'min' => 8, 'max' => 72, 'tooShort' => 'Пароль должен быть не короче 8 символов.'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'email' => 'Email',
            'password' => 'Пароль',
        ];
    }

    /**
     * Создаёт пользователя вместе с токеном. Вызывать после успешного validate().
     */
    public function register(): User
    {
        $user = new User(['email' => $this->email]);
        $user->setPassword($this->password);
        $user->generateAuthKey();
        $user->generateAccessToken();

        if (!$user->save()) {
            // Гонка за уникальный email между validate() и save()
            $this->addErrors($user->getErrors());
            throw new \yii\web\UnprocessableEntityHttpException('Не удалось создать пользователя.');
        }

        return $user;
    }
}
