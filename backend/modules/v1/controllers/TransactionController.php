<?php

namespace app\modules\v1\controllers;

use app\models\Category;
use app\models\Transaction;
use yii\db\ActiveQuery;
use yii\web\BadRequestHttpException;

/**
 * Операции (расходы и доходы) пользователя.
 *
 * GET    /api/v1/transactions
 *        фильтры: date_from, date_to (Y-m-d, включительно), account_id, category_id,
 *                 type (expense|income), q (поиск по заметке)
 *        сортировка: ?sort=-occurred_at (по умолчанию), amount, created_at
 *        страницы: page, per-page (по умолчанию 50, максимум 100)
 * POST   /api/v1/transactions
 * GET    /api/v1/transactions/{id}
 * PUT    /api/v1/transactions/{id}  (или PATCH)
 * DELETE /api/v1/transactions/{id}
 *
 * В каждой операции есть вложенные account {id, name, currency}
 * и category {id, name, type, color, icon}.
 */
class TransactionController extends OwnedActiveController
{
    use QueryParamsTrait;

    public $modelClass = Transaction::class;

    protected function indexQuery(): ActiveQuery
    {
        $query = parent::indexQuery()->with(['account', 'category']);

        $dateFrom = $this->dateParam('date_from');
        $dateTo = $this->dateParam('date_to');

        if ($dateFrom !== null && $dateTo !== null && $dateFrom > $dateTo) {
            throw new BadRequestHttpException('date_from не может быть позже date_to.');
        }

        if ($dateFrom !== null) {
            $query->andWhere(['>=', 'occurred_at', $dateFrom]);
        }

        if ($dateTo !== null) {
            $query->andWhere(['<=', 'occurred_at', $dateTo]);
        }

        if (($accountId = $this->intParam('account_id')) !== null) {
            $query->andWhere(['account_id' => $accountId]);
        }

        if (($categoryId = $this->intParam('category_id')) !== null) {
            $query->andWhere(['category_id' => $categoryId]);
        }

        if (($type = $this->stringParam('type')) !== null) {
            if (!in_array($type, Category::TYPES, true)) {
                throw new BadRequestHttpException('Параметр type: expense или income.');
            }

            $query->andWhere(['type' => $type]);
        }

        if (($q = $this->stringParam('q')) !== null) {
            if (mb_strlen($q) > 100) {
                throw new BadRequestHttpException('Параметр q: не длиннее 100 символов.');
            }

            // Спецсимволы LIKE (%, _) экранируются, поиск идёт по вхождению без учёта регистра
            $query->andWhere(['ilike', 'note', $q]);
        }

        return $query;
    }

    /**
     * id добавлен вторым ключом, чтобы порядок при одинаковых значениях был стабильным
     * и записи не терялись и не дублировались между страницами.
     */
    protected function sortConfig(): array
    {
        $attribute = static fn(string $column) => [
            'asc' => [$column => SORT_ASC, 'id' => SORT_ASC],
            'desc' => [$column => SORT_DESC, 'id' => SORT_DESC],
        ];

        return [
            'attributes' => [
                'occurred_at' => $attribute('occurred_at'),
                'amount' => $attribute('amount'),
                'created_at' => $attribute('created_at'),
            ],
            'defaultOrder' => ['occurred_at' => SORT_DESC],
        ];
    }
}
