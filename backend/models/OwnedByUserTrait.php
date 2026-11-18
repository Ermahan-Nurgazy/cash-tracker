<?php

namespace app\models;

/**
 * Для моделей с владельцем (колонка user_id).
 *
 * - user_id участвует в валидации, но не присваивается из тела запроса (load() его игнорирует):
 *   владельца задаёт сервер, иначе через PUT/POST запись можно передать другому пользователю;
 * - user_id не попадает в JSON-ответ, API всегда отдаёт только данные текущего пользователя.
 */
trait OwnedByUserTrait
{
    public function scenarios(): array
    {
        $scenarios = parent::scenarios();

        foreach ($scenarios as $name => $attributes) {
            $scenarios[$name] = array_map(
                static fn(string $attribute) => $attribute === 'user_id' ? '!user_id' : $attribute,
                $attributes
            );
        }

        return $scenarios;
    }

    /**
     * Ответ на POST/PATCH должен совпадать с последующим GET: база приводит числа к формату
     * колонки (numeric(14,2): «1234.5» становится «1234.50»), поэтому запись перечитывается.
     */
    public function afterSave($insert, $changedAttributes): void
    {
        parent::afterSave($insert, $changedAttributes);
        $this->refresh();
    }

    public function fields(): array
    {
        $fields = parent::fields();
        unset($fields['user_id']);

        return $fields;
    }
}
