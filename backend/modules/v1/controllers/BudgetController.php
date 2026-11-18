<?php

namespace app\modules\v1\controllers;

use app\models\Budget;
use yii\db\ActiveQuery;
use yii\web\BadRequestHttpException;

/**
 * Бюджеты: лимиты расходов по категориям на месяц.
 *
 * GET    /api/v1/budgets[?month=2026-11&category_id=1&currency=KZT&sort=month&page=1&per-page=50]
 * POST   /api/v1/budgets            {category_id, month: "2026-11", currency, limit}
 * GET    /api/v1/budgets/{id}
 * PUT    /api/v1/budgets/{id}  (или PATCH)
 * DELETE /api/v1/budgets/{id}
 *
 * В ответе кроме полей бюджета: spent (потрачено в валюте бюджета), remaining (лимит минус
 * потрачено, может быть отрицательным), percent (целые проценты, может быть больше 100)
 * и status: ok | warning (от 80 %) | exceeded (больше лимита).
 */
class BudgetController extends OwnedActiveController
{
    public $modelClass = Budget::class;

    protected function indexQuery(): ActiveQuery
    {
        $query = parent::indexQuery()->with('category');
        $request = \Yii::$app->request;

        $month = $request->get('month');
        if ($month !== null && $month !== '') {
            if (!is_string($month) || !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
                throw new BadRequestHttpException('Параметр month: ГГГГ-ММ.');
            }
            $query->andWhere(['period_month' => $month . '-01']);
        }

        $categoryId = $request->get('category_id');
        if ($categoryId !== null && $categoryId !== '') {
            if (!is_string($categoryId) || !ctype_digit($categoryId)) {
                throw new BadRequestHttpException('Параметр category_id: целое число.');
            }
            $query->andWhere(['category_id' => (int) $categoryId]);
        }

        $currency = $request->get('currency');
        if ($currency !== null && $currency !== '') {
            if (!is_string($currency) || !preg_match('/^[A-Za-z]{3}$/', $currency)) {
                throw new BadRequestHttpException('Параметр currency: три латинские буквы.');
            }
            $query->andWhere(['currency' => strtoupper($currency)]);
        }

        return $query;
    }

    protected function sortConfig(): array
    {
        return [
            'attributes' => [
                'month' => ['asc' => ['period_month' => SORT_ASC], 'desc' => ['period_month' => SORT_DESC]],
                'limit',
                'created_at',
                'id',
            ],
            'defaultOrder' => ['month' => SORT_DESC, 'id' => SORT_ASC],
        ];
    }
}
