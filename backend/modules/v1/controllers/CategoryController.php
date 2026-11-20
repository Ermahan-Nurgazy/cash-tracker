<?php

namespace app\modules\v1\controllers;

use app\models\Category;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\web\BadRequestHttpException;
use yii\web\ConflictHttpException;

/**
 * Категории пользователя.
 *
 * GET    /api/v1/categories[?type=expense|income&sort=name&page=1&per-page=50]
 * POST   /api/v1/categories
 * GET    /api/v1/categories/{id}
 * PUT    /api/v1/categories/{id}  (или PATCH)
 * DELETE /api/v1/categories/{id}
 */
class CategoryController extends OwnedActiveController
{
    public $modelClass = Category::class;

    /**
     * Список категорий с необязательным фильтром ?type=expense|income.
     *
     * @return ActiveQuery запрос категорий текущего пользователя
     * @throws BadRequestHttpException если type не expense и не income
     */
    protected function indexQuery(): ActiveQuery
    {
        $query = parent::indexQuery();
        $type = \Yii::$app->request->get('type');

        if ($type !== null && $type !== '') {
            if (!in_array($type, Category::TYPES, true)) {
                throw new BadRequestHttpException('Параметр type: expense или income.');
            }

            $query->andWhere(['type' => $type]);
        }

        return $query;
    }

    /**
     * Сортировка списка категорий: по типу и названию (по умолчанию), либо по дате создания.
     *
     * @return array<string, mixed> конфигурация yii\data\Sort
     */
    protected function sortConfig(): array
    {
        return [
            'attributes' => ['name', 'type', 'created_at'],
            'defaultOrder' => ['type' => SORT_ASC, 'name' => SORT_ASC],
        ];
    }

    /**
     * Категорию с операциями удалять нельзя.
     *
     * @param ActiveRecord $model удаляемая категория (Category)
     * @return void
     * @throws ConflictHttpException если по категории есть операции
     */
    protected function assertDeletable(ActiveRecord $model): void
    {
        /** @var Category $model */
        if ($model->getTransactions()->exists()) {
            throw new ConflictHttpException('Нельзя удалить категорию, по которой есть операции.');
        }
    }
}
