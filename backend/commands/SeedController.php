<?php

namespace app\commands;

use app\models\Account;
use app\models\Category;
use app\models\User;
use Throwable;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Заполняет БД демо-данными: пользователь, счёт и стандартные категории.
 *
 * Команда идемпотентна: существующие записи не меняются и не дублируются.
 *
 * Примеры:
 *   php yii seed
 *   php yii seed --email=me@example.com --password=secret123 --currency=USD
 */
class SeedController extends Controller
{
    /** @var string Email демо-пользователя */
    public $email = 'demo@example.com';

    /** @var string Пароль демо-пользователя (минимум 8 символов) */
    public $password = 'demo12345';

    /** @var string Валюта демо-счёта */
    public $currency = 'KZT';

    /** @var bool Разрешить запуск в окружении prod */
    public $force = false;

    private const ACCOUNT_NAME = 'Наличные';

    /** @var array<string, array<string, string>> тип => [название => цвет] */
    private const CATEGORIES = [
        Category::TYPE_EXPENSE => [
            'Продукты' => '#4CAF50',
            'Транспорт' => '#2196F3',
            'Жильё' => '#795548',
            'Коммунальные услуги' => '#607D8B',
            'Здоровье' => '#F44336',
            'Одежда' => '#9C27B0',
            'Кафе и рестораны' => '#FF9800',
            'Развлечения' => '#E91E63',
            'Связь и интернет' => '#00BCD4',
            'Образование' => '#3F51B5',
            'Подарки' => '#FF5722',
            'Прочее' => '#9E9E9E',
        ],
        Category::TYPE_INCOME => [
            'Зарплата' => '#2E7D32',
            'Подработка' => '#43A047',
            'Подарки' => '#66BB6A',
            'Прочее' => '#9E9E9E',
        ],
    ];

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['email', 'password', 'currency', 'force']);
    }

    public function actionIndex(): int
    {
        if (YII_ENV_PROD && !$this->force) {
            $this->stderr("Отказ: seed в окружении prod запрещён. Используйте --force, если это намеренно.\n", Console::FG_RED);

            return ExitCode::UNSPECIFIED_ERROR;
        }

        if (mb_strlen((string) $this->password) < 8) {
            $this->stderr("Пароль должен быть не короче 8 символов.\n", Console::FG_RED);

            return ExitCode::USAGE;
        }

        $transaction = Yii::$app->db->beginTransaction();

        try {
            $user = $this->seedUser();
            $this->seedAccount($user);
            $this->seedCategories($user);
            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();
            $this->stderr('Ошибка: ' . $e->getMessage() . "\n", Console::FG_RED);

            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("Готово.\n", Console::FG_GREEN);

        return ExitCode::OK;
    }

    private function seedUser(): User
    {
        $email = mb_strtolower(trim((string) $this->email));
        $user = User::findOne(['email' => $email]);

        if ($user !== null) {
            $this->stdout("Пользователь {$email}: уже есть\n");

            return $user;
        }

        $user = new User(['email' => $email]);
        $user->setPassword((string) $this->password);
        $user->generateAuthKey();
        $user->generateAccessToken();
        $this->saveOrFail($user);

        $this->stdout("Пользователь {$email}: создан (пароль: {$this->password})\n");
        $this->stdout("  access_token: {$user->access_token}\n");

        return $user;
    }

    private function seedAccount(User $user): void
    {
        $exists = Account::find()->where(['user_id' => $user->id, 'name' => self::ACCOUNT_NAME])->exists();

        if ($exists) {
            $this->stdout('Счёт «' . self::ACCOUNT_NAME . "»: уже есть\n");

            return;
        }

        $account = new Account(['name' => self::ACCOUNT_NAME, 'currency' => $this->currency]);
        $account->user_id = $user->id;
        $this->saveOrFail($account);

        $this->stdout('Счёт «' . self::ACCOUNT_NAME . "» ({$account->currency}): создан\n");
    }

    private function seedCategories(User $user): void
    {
        $created = 0;
        $skipped = 0;

        foreach (self::CATEGORIES as $type => $categories) {
            foreach ($categories as $name => $color) {
                $exists = Category::find()
                    ->where(['user_id' => $user->id, 'name' => $name, 'type' => $type])
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                $category = new Category(['name' => $name, 'type' => $type, 'color' => $color]);
                $category->user_id = $user->id;
                $this->saveOrFail($category);
                $created++;
            }
        }

        $this->stdout("Категории: создано {$created}, уже было {$skipped}\n");
    }

    private function saveOrFail(\yii\db\ActiveRecord $model): void
    {
        if (!$model->save()) {
            throw new \RuntimeException(static::class . ': ' . json_encode($model->errors, JSON_UNESCAPED_UNICODE));
        }
    }
}
