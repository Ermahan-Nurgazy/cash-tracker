<?php

namespace app\modules\v1\controllers;

use app\models\LoginForm;
use app\models\RegisterForm;
use app\models\User;
use Yii;
use yii\filters\auth\HttpBearerAuth;
use yii\rest\Controller;
use yii\web\UnauthorizedHttpException;

/**
 * Аутентификация по Bearer-токену.
 *
 * POST /api/v1/auth/register  регистрация, возвращает пользователя и токен
 * POST /api/v1/auth/login     вход, выдаёт новый токен
 * GET  /api/v1/auth/me        текущий пользователь
 * POST /api/v1/auth/logout    сброс токена
 */
class AuthController extends Controller
{
    public $serializer = 'app\components\Serializer';

    /**
     * Подключает Bearer-аутентификацию ко всем действиям, кроме register и login.
     *
     * @return array<string, mixed> конфигурации поведений
     */
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();

        $behaviors['authenticator'] = [
            'class' => HttpBearerAuth::class,
            'except' => ['register', 'login'],
        ];

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
            'register' => ['POST'],
            'login' => ['POST'],
            'me' => ['GET'],
            'logout' => ['POST'],
        ];
    }

    /**
     * POST /api/v1/auth/register: регистрирует пользователя.
     *
     * @return array{user: User, access_token: string}|RegisterForm данные пользователя
     *         и токен (статус 201) либо форма с ошибками валидации (422)
     * @throws \yii\web\UnprocessableEntityHttpException если пользователя не удалось сохранить
     */
    public function actionRegister()
    {
        $form = new RegisterForm();
        $form->load(Yii::$app->request->getBodyParams(), '');

        if (!$form->validate()) {
            return $form; // 422 со списком ошибок
        }

        $user = $form->register();
        Yii::$app->response->setStatusCode(201);

        return $this->authPayload($user);
    }

    /**
     * POST /api/v1/auth/login: проверяет email и пароль и выдаёт новый токен.
     *
     * @return array{user: User, access_token: string}|LoginForm данные пользователя
     *         и токен либо форма с ошибками валидации (422)
     * @throws UnauthorizedHttpException если email или пароль неверны (одно сообщение на оба случая)
     */
    public function actionLogin()
    {
        $form = new LoginForm();
        $form->load(Yii::$app->request->getBodyParams(), '');

        if (!$form->validate()) {
            return $form; // 422 со списком ошибок
        }

        $user = $form->authenticate();

        if ($user === null) {
            // Одно и то же сообщение для неверного email и неверного пароля
            throw new UnauthorizedHttpException('Неверный email или пароль.');
        }

        return $this->authPayload($user);
    }

    /**
     * GET /api/v1/auth/me: текущий пользователь.
     *
     * @return User аутентифицированный пользователь (без секретных полей)
     */
    public function actionMe(): User
    {
        /** @var User $user */
        $user = Yii::$app->user->identity;

        return $user;
    }

    /**
     * POST /api/v1/auth/logout: сбрасывает токен текущего пользователя.
     *
     * @return null пустое тело, статус 204
     */
    public function actionLogout()
    {
        /** @var User $user */
        $user = Yii::$app->user->identity;
        $user->access_token = null;
        $user->save(false, ['access_token', 'updated_at']);

        Yii::$app->response->setStatusCode(204);

        return null;
    }

    /**
     * Тело ответа для регистрации и входа.
     *
     * @param User $user пользователь с актуальным токеном
     * @return array{user: User, access_token: string|null} пользователь и его токен
     */
    private function authPayload(User $user): array
    {
        return [
            'user' => $user,
            'access_token' => $user->access_token,
        ];
    }
}
