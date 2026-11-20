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

    /**
     * Подключает аутентификацию по Bearer-токену ко всем действиям, кроме options.
     *
     * @return array<string, mixed> конфигурации поведений
     */
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();

        $behaviors['authenticator'] = [
            'class' => HttpBearerAuth::class,
            'except' => ['options'],
        ];

        return $behaviors;
    }

    /**
     * Стандартные действия ActiveController, кроме create и delete (они реализованы здесь),
     * со своим провайдером данных для списка.
     *
     * @return array<string, mixed> конфигурации действий
     */
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
     *
     * @param string $action идентификатор действия
     * @param \yii\base\Model|null $model проверяемая запись (null — проверка не нужна)
     * @param array<string, mixed> $params дополнительные параметры
     * @return void
     * @throws NotFoundHttpException если запись принадлежит другому пользователю
     */
    public function checkAccess($action, $model = null, $params = [])
    {
        if ($model !== null && (int) $model->user_id !== (int) Yii::$app->user->id) {
            throw new NotFoundHttpException('Запись не найдена.');
        }
    }

    /**
     * Провайдер данных для списка: записи пользователя, пагинация (по умолчанию 50,
     * не более 100 на страницу) и сортировка из sortConfig().
     *
     * @return ActiveDataProvider провайдер данных
     * @throws \yii\web\BadRequestHttpException если наследник отклонил параметры фильтра
     */
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

    /**
     * POST: создаёт запись; владельцем становится текущий пользователь.
     *
     * @return ActiveRecord созданная запись (201 и заголовок Location) либо запись
     *         с ошибками валидации (будет отдана как 422)
     * @throws ServerErrorHttpException если сохранить не удалось без ошибок валидации
     */
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

    /**
     * DELETE: удаляет запись пользователя.
     *
     * @param int|string $id идентификатор записи
     * @return null пустое тело, статус 204
     * @throws NotFoundHttpException если записи нет или она чужая
     * @throws \yii\web\ConflictHttpException если запись удалять нельзя (см. assertDeletable())
     * @throws ServerErrorHttpException если удаление не удалось
     */
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
     *
     * @return ActiveQuery запрос, ограниченный текущим пользователем
     * @throws \yii\web\BadRequestHttpException в наследниках — при неверных параметрах фильтра
     */
    protected function indexQuery(): ActiveQuery
    {
        /** @var ActiveRecord $class */
        $class = $this->modelClass;

        return $class::find()->andWhere(['user_id' => Yii::$app->user->id]);
    }

    /**
     * Настройки сортировки списка (параметр ?sort=name или ?sort=-name).
     *
     * @return array<string, mixed> конфигурация yii\data\Sort: белый список attributes и defaultOrder
     */
    abstract protected function sortConfig(): array;

    /**
     * Бросает исключение, если запись нельзя удалять (например, на неё ссылаются операции).
     * В базовой реализации ограничений нет.
     *
     * @param ActiveRecord $model удаляемая запись
     * @return void
     * @throws \yii\web\ConflictHttpException в наследниках — если запись удалять нельзя
     */
    protected function assertDeletable(ActiveRecord $model): void
    {
    }

    /**
     * Находит запись по id и проверяет, что она принадлежит текущему пользователю.
     *
     * @param int|string $id идентификатор записи
     * @return ActiveRecord найденная запись
     * @throws NotFoundHttpException если записи нет или она чужая
     */
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
