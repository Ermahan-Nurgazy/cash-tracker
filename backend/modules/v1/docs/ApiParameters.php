<?php

namespace app\modules\v1\docs;

use OpenApi\Attributes as OA;

/**
 * Общие параметры запроса: пагинация и идентификатор записи.
 *
 * Класс только несёт атрибуты Swagger и в коде не используется.
 */
#[OA\Parameter(
    parameter: 'Page',
    name: 'page',
    in: 'query',
    description: 'Номер страницы',
    schema: new OA\Schema(type: 'integer', minimum: 1, default: 1),
)]
#[OA\Parameter(
    parameter: 'PerPage',
    name: 'per-page',
    in: 'query',
    description: 'Записей на странице (по умолчанию 50, максимум 100)',
    schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 50),
)]
#[OA\Parameter(
    parameter: 'RecordId',
    name: 'id',
    in: 'path',
    required: true,
    description: 'Идентификатор записи',
    schema: new OA\Schema(type: 'integer', minimum: 1),
)]
final class ApiParameters
{
}
