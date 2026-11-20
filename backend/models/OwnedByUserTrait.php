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
    /**
     * Помечает user_id как «небезопасный» (`!user_id`) во всех сценариях: он проверяется,
     * но не присваивается из массового присваивания.
     *
     * @return array<string, array<int, string>> сценарий => список атрибутов
     */
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
     *
     * @param bool $insert true при вставке, false при обновлении
     * @param array<string, mixed> $changedAttributes прежние значения изменённых атрибутов
     * @return void
     * @throws \yii\db\StaleObjectException если запись исчезла из БД до перечитывания
     */
    public function afterSave($insert, $changedAttributes): void
    {
        parent::afterSave($insert, $changedAttributes);
        $this->refresh();
    }

    /**
     * Поля JSON-ответа без user_id.
     *
     * @return array<string, mixed> поля ответа
     */
    public function fields(): array
    {
        $fields = parent::fields();
        unset($fields['user_id']);

        return $fields;
    }
}
