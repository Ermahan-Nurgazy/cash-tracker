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

    public function behaviors(): array
    {
        $behaviors = parent::behaviors();

        $behaviors['authenticator'] = [
            'class' => HttpBearerAuth::class,
            'except' => ['register', 'login'],
        ];

        return $behaviors;
    }

    protected function verbs(): array
    {
        return [
            'register' => ['POST'],
            'login' => ['POST'],
            'me' => ['GET'],
            'logout' => ['POST'],
        ];
    }

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

    public function actionMe(): User
    {
        /** @var User $user */
        $user = Yii::$app->user->identity;

        return $user;
    }

    public function actionLogout()
    {
        /** @var User $user */
        $user = Yii::$app->user->identity;
        $user->access_token = null;
        $user->save(false, ['access_token', 'updated_at']);

        Yii::$app->response->setStatusCode(204);

        return null;
    }

    private function authPayload(User $user): array
    {
        return [
            'user' => $user,
            'access_token' => $user->access_token,
        ];
    }
}
