<?php

namespace app\modules\v1\controllers;

use Yii;
use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\filters\auth\HttpBearerAuth;
use yii\rest\ActiveController;
use yii\web\NotFoundHttpException;
use yii\web\ServerErrorHttpException;

/**
 * Базовый REST-контроллер для сущностей, принадлежащих пользователю (колонка user_id).
 *
 * - все действия требуют Bearer-токен;
 * - список содержит только записи текущего пользователя;
 * - чужая запись для view/update/delete неотличима от несуществующей (404);
 * - владельца при создании задаёт сервер.
 */
abstract class OwnedActiveController extends ActiveController
{
    public $serializer = 'app\components\Serializer';

    public function behaviors(): array
    {
        $behaviors = parent::behaviors();

        $behaviors['authenticator'] = [
            'class' => HttpBearerAuth::class,
            'except' => ['options'],
        ];

        return $behaviors;
    }

    public function actions(): array
    {
        $actions = parent::actions();

        // create и delete реализованы ниже: владелец и проверка связанных данных
        unset($actions['create'], $actions['delete']);

        $actions['index']['prepareDataProvider'] = [$this, 'prepareDataProvider'];

        return $actions;
    }

    /**
     * Чужие записи скрываем под видом несуществующих.
     */
    public function checkAccess($action, $model = null, $params = [])
    {
        if ($model !== null && (int) $model->user_id !== (int) Yii::$app->user->id) {
            throw new NotFoundHttpException('Запись не найдена.');
        }
    }

    public function prepareDataProvider(): ActiveDataProvider
    {
        return new ActiveDataProvider([
            'query' => $this->indexQuery(),
            'pagination' => [
                'defaultPageSize' => 50,
                'pageSizeLimit' => [1, 100],
            ],
            'sort' => $this->sortConfig(),
        ]);
    }

    public function actionCreate()
    {
        /** @var ActiveRecord $model */
        $model = new $this->modelClass();
        $model->load(Yii::$app->request->getBodyParams(), '');
        $model->user_id = Yii::$app->user->id;

        if ($model->save()) {
            $response = Yii::$app->response;
            $response->setStatusCode(201);
            $response->getHeaders()->set('Location', \yii\helpers\Url::toRoute(['view', 'id' => $model->id], true));
        } elseif (!$model->hasErrors()) {
            throw new ServerErrorHttpException('Не удалось сохранить запись.');
        }

        return $model;
    }

    public function actionDelete($id)
    {
        $model = $this->findOwnedModel($id);
        $this->assertDeletable($model);

        if ($model->delete() === false) {
            throw new ServerErrorHttpException('Не удалось удалить запись.');
        }

        Yii::$app->response->setStatusCode(204);

        return null;
    }

    /**
     * Запрос списка: только записи текущего пользователя. Фильтры добавляют наследники.
     */
    protected function indexQuery(): ActiveQuery
    {
        /** @var ActiveRecord $class */
        $class = $this->modelClass;

        return $class::find()->andWhere(['user_id' => Yii::$app->user->id]);
    }

    /**
     * Настройки сортировки списка (параметр ?sort=name или ?sort=-name).
     */
    abstract protected function sortConfig(): array;

    /**
     * Бросает исключение, если запись нельзя удалять (например, на неё ссылаются операции).
     */
    protected function assertDeletable(ActiveRecord $model): void
    {
    }

    protected function findOwnedModel($id): ActiveRecord
    {
        /** @var ActiveRecord $class */
        $class = $this->modelClass;
        $model = $class::findOne($id);

        if ($model === null) {
            throw new NotFoundHttpException('Запись не найдена.');
        }

        $this->checkAccess($this->action->id, $model);

        return $model;
    }
}
