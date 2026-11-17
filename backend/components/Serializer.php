<?php

namespace app\components;

use yii\base\Model;
use yii\rest\Serializer as BaseSerializer;

/**
 * Ошибки валидации в том же формате, что и остальные ошибки API:
 *
 *   {"status": 422, "message": "Ошибка валидации.", "errors": {"email": "Сообщение"}}
 *
 * По каждому полю отдаётся первое сообщение.
 */
class Serializer extends BaseSerializer
{
    protected function serializeModelErrors($model): array
    {
        $this->response->setStatusCode(422);

        /** @var Model $model */
        return [
            'status' => 422,
            'message' => 'Ошибка валидации.',
            'errors' => $model->getFirstErrors(),
        ];
    }
}
