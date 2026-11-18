<?php

namespace app\commands;

use app\models\Account;
use app\models\Category;
use app\models\Transaction;
use app\models\User;
use DateTimeImmutable;
use RuntimeException;
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
 *   php yii seed --transactions   (добавить демо-операции за 6 месяцев, если у пользователя их нет)
 */
class SeedController extends Controller
{
    /** @var string Email демо-пользователя */
    public $email = 'demo@example.com';

    /** @var string Пароль демо-пользователя (минимум 8 символов) */
    public $password = 'demo12345';

    /** @var string Валюта демо-счёта */
    public $currency = 'KZT';

    /** @var bool Добавить демо-операции за последние 6 месяцев (только если у пользователя их ещё нет) */
    public $transactions = false;

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
        return array_merge(parent::options($actionID), ['email', 'password', 'currency', 'transactions', 'force']);
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

            if ($this->transactions) {
                $this->seedTransactions($user);
            }

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

    /**
     * Демо-операции для дашборда: зарплата и типичные расходы за последние 6 месяцев.
     * Суммы псевдослучайные, но с фиксированным зерном, поэтому результат повторяемый.
     */
    private function seedTransactions(User $user): void
    {
        if (Transaction::find()->where(['user_id' => $user->id])->exists()) {
            $this->stdout("Операции: у пользователя уже есть, демо-операции не добавлены
");

            return;
        }

        $account = Account::findOne(['user_id' => $user->id, 'name' => self::ACCOUNT_NAME]);
        if ($account === null) {
            throw new RuntimeException('Не найден демо-счёт');
        }

        $categories = [];
        foreach (Category::findAll(['user_id' => $user->id]) as $category) {
            $categories[$category->type][$category->name] = $category->id;
        }

        // [категория, тип, минимум, максимум, сколько раз в месяц]
        $plan = [
            ['Зарплата', Category::TYPE_INCOME, 450000, 450000, 1],
            ['Подработка', Category::TYPE_INCOME, 30000, 90000, 1],
            ['Жильё', Category::TYPE_EXPENSE, 150000, 150000, 1],
            ['Коммунальные услуги', Category::TYPE_EXPENSE, 18000, 32000, 1],
            ['Продукты', Category::TYPE_EXPENSE, 8000, 28000, 4],
            ['Транспорт', Category::TYPE_EXPENSE, 2000, 9000, 3],
            ['Кафе и рестораны', Category::TYPE_EXPENSE, 3000, 15000, 3],
            ['Развлечения', Category::TYPE_EXPENSE, 5000, 25000, 2],
            ['Связь и интернет', Category::TYPE_EXPENSE, 5000, 5000, 1],
        ];

        mt_srand(42);
        $today = new DateTimeImmutable('today');
        $count = 0;

        for ($back = 5; $back >= 0; $back--) {
            $month = $today->modify('first day of this month')->modify("-{$back} months");
            $lastDay = $back === 0 ? (int) $today->format('j') : (int) $month->format('t');

            foreach ($plan as [$name, $type, $min, $max, $times]) {
                for ($i = 0; $i < $times; $i++) {
                    $tx = new Transaction([
                        'account_id' => $account->id,
                        'category_id' => $categories[$type][$name],
                        'amount' => (string) (mt_rand($min, $max) . '.00'),
                        'type' => $type,
                        'occurred_at' => $month->setDate((int) $month->format('Y'), (int) $month->format('n'), mt_rand(1, $lastDay))->format('Y-m-d'),
                    ]);
                    $tx->user_id = $user->id;
                    $this->saveOrFail($tx);
                    $count++;
                }
            }
        }

        $this->stdout("Операции: добавлено {$count} демо-операций за 6 месяцев
");
    }

    private function saveOrFail(\yii\db\ActiveRecord $model): void
    {
        if (!$model->save()) {
            throw new \RuntimeException(static::class . ': ' . json_encode($model->errors, JSON_UNESCAPED_UNICODE));
        }
    }
}
