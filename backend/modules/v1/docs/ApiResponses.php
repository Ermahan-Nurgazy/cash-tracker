<?php

namespace app\modules\v1\docs;

use OpenApi\Attributes as OA;

/**
 * Повторяющиеся ответы об ошибках, на которые ссылаются операции контроллеров.
 *
 * Класс только несёт атрибуты Swagger и в коде не используется.
 */
#[OA\Response(
    response: 'BadRequest',
    description: 'Неверный параметр запроса (400)',
    content: new OA\JsonContent(ref: '#/components/schemas/Error'),
)]
#[OA\Response(
    response: 'Unauthorized',
    description: 'Нет токена, он неверен или сброшен (401)',
    content: new OA\JsonContent(ref: '#/components/schemas/Error'),
)]
#[OA\Response(
    response: 'NotFound',
    description: 'Запись не найдена или принадлежит другому пользователю (404)',
    content: new OA\JsonContent(ref: '#/components/schemas/Error'),
)]
#[OA\Response(
    response: 'Conflict',
    description: 'Удалить нельзя: есть связанные данные (409)',
    content: new OA\JsonContent(ref: '#/components/schemas/Error'),
)]
#[OA\Response(
    response: 'ValidationFailed',
    description: 'Ошибка валидации (422): errors содержит первое сообщение по каждому полю',
    content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'),
)]
final class ApiResponses
{
}
