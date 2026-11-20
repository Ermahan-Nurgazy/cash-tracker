<?php

namespace app\modules\v1\controllers;

use app\models\Account;
use yii\db\ActiveRecord;
use yii\web\ConflictHttpException;

/**
 * Счета пользователя.
 *
 * GET    /api/v1/accounts[?sort=name&page=1&per-page=50]
 * POST   /api/v1/accounts
 * GET    /api/v1/accounts/{id}
 * PUT    /api/v1/accounts/{id}  (или PATCH)
 * DELETE /api/v1/accounts/{id}
 */
class AccountController extends OwnedActiveController
{
    public $modelClass = Account::class;

    /**
     * Сортировка списка счетов: по названию (по умолчанию), валюте или дате создания.
     *
     * @return array<string, mixed> конфигурация yii\data\Sort
     */
    protected function sortConfig(): array
    {
        return [
            'attributes' => ['name', 'currency', 'created_at'],
            'defaultOrder' => ['name' => SORT_ASC],
        ];
    }

    /**
     * Счёт с операциями удалять нельзя.
     *
     * @param ActiveRecord $model удаляемый счёт (Account)
     * @return void
     * @throws ConflictHttpException если по счёту есть операции
     */
    protected function assertDeletable(ActiveRecord $model): void
    {
        /** @var Account $model */
        if ($model->getTransactions()->exists()) {
            throw new ConflictHttpException('Нельзя удалить счёт, по которому есть операции.');
        }
    }
}
