<?php

namespace app\modules\v1\docs;

use OpenApi\Attributes as OA;

/**
 * Схемы данных запросов и ответов API v1.
 *
 * Класс только несёт атрибуты Swagger и в коде не используется.
 */
// --- Схемы: ошибки ---
#[OA\Schema(
    schema: 'Error',
    required: ['status', 'message'],
    properties: [
        new OA\Property(property: 'status', type: 'integer', example: 404),
        new OA\Property(property: 'message', type: 'string', example: 'Запись не найдена.'),
    ],
)]
#[OA\Schema(
    schema: 'ValidationError',
    required: ['status', 'message', 'errors'],
    properties: [
        new OA\Property(property: 'status', type: 'integer', example: 422),
        new OA\Property(property: 'message', type: 'string', example: 'Ошибка валидации.'),
        new OA\Property(
            property: 'errors',
            type: 'object',
            description: 'Поле => первое сообщение об ошибке',
            example: ['amount' => 'Сумма должна быть больше нуля.'],
            additionalProperties: new OA\AdditionalProperties(type: 'string'),
        ),
    ],
)]
// --- Схемы: аутентификация ---
#[OA\Schema(
    schema: 'User',
    required: ['id', 'email', 'created_at', 'updated_at'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'demo@example.com'),
        new OA\Property(property: 'created_at', type: 'integer', description: 'Unix-время', example: 1762732800),
        new OA\Property(property: 'updated_at', type: 'integer', description: 'Unix-время', example: 1762732800),
    ],
)]
#[OA\Schema(
    schema: 'Credentials',
    required: ['email', 'password'],
    properties: [
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'demo@example.com'),
        new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8, maxLength: 72),
    ],
)]
#[OA\Schema(
    schema: 'AuthPayload',
    required: ['user', 'access_token'],
    properties: [
        new OA\Property(property: 'user', ref: '#/components/schemas/User'),
        new OA\Property(property: 'access_token', type: 'string', description: 'Новый токен; предыдущий перестаёт действовать'),
    ],
)]
// --- Схемы: счета ---
#[OA\Schema(
    schema: 'Account',
    required: ['id', 'name', 'currency', 'initial_balance', 'created_at', 'updated_at'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', maxLength: 100, example: 'Kaspi Gold'),
        new OA\Property(property: 'currency', type: 'string', description: 'Три латинские буквы', example: 'KZT'),
        new OA\Property(property: 'initial_balance', type: 'string', example: '0.00'),
        new OA\Property(property: 'created_at', type: 'integer', example: 1762732800),
        new OA\Property(property: 'updated_at', type: 'integer', example: 1762732800),
    ],
)]
#[OA\Schema(
    schema: 'AccountInput',
    required: ['name', 'currency'],
    properties: [
        new OA\Property(property: 'name', type: 'string', maxLength: 100, description: 'Уникально у пользователя'),
        new OA\Property(property: 'currency', type: 'string', description: 'Три латинские буквы; у счёта с операциями не меняется', example: 'KZT'),
        new OA\Property(property: 'initial_balance', type: 'string', default: '0.00', example: '1000.00'),
    ],
)]
// --- Схемы: категории ---
#[OA\Schema(
    schema: 'Category',
    required: ['id', 'name', 'type', 'created_at'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 6),
        new OA\Property(property: 'name', type: 'string', example: 'Продукты'),
        new OA\Property(property: 'type', type: 'string', enum: ['expense', 'income']),
        new OA\Property(property: 'color', type: 'string', nullable: true, description: '#RRGGBB', example: '#4caf50'),
        new OA\Property(property: 'icon', type: 'string', nullable: true, example: 'pi-shopping-cart'),
        new OA\Property(property: 'created_at', type: 'integer', example: 1762732800),
    ],
)]
#[OA\Schema(
    schema: 'CategoryInput',
    required: ['name', 'type'],
    properties: [
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'type', type: 'string', enum: ['expense', 'income'], description: 'Не меняется, если есть операции'),
        new OA\Property(property: 'color', type: 'string', nullable: true, description: '#RRGGBB', example: '#4caf50'),
        new OA\Property(property: 'icon', type: 'string', nullable: true),
    ],
)]
// --- Схемы: операции ---
#[OA\Schema(
    schema: 'Transaction',
    required: ['id', 'account_id', 'category_id', 'amount', 'type', 'occurred_at', 'created_at', 'updated_at'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 15),
        new OA\Property(property: 'account_id', type: 'integer', example: 1),
        new OA\Property(property: 'category_id', type: 'integer', example: 6),
        new OA\Property(property: 'amount', type: 'string', example: '1500.50'),
        new OA\Property(property: 'type', type: 'string', enum: ['expense', 'income']),
        new OA\Property(property: 'occurred_at', type: 'string', format: 'date', example: '2026-11-10'),
        new OA\Property(property: 'note', type: 'string', nullable: true, maxLength: 2000, example: 'Продукты'),
        new OA\Property(
            property: 'account',
            type: 'object',
            nullable: true,
            properties: [
                new OA\Property(property: 'id', type: 'integer'),
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'currency', type: 'string', example: 'KZT'),
            ],
        ),
        new OA\Property(
            property: 'category',
            type: 'object',
            nullable: true,
            properties: [
                new OA\Property(property: 'id', type: 'integer'),
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'type', type: 'string', enum: ['expense', 'income']),
                new OA\Property(property: 'color', type: 'string', nullable: true),
                new OA\Property(property: 'icon', type: 'string', nullable: true),
            ],
        ),
        new OA\Property(property: 'created_at', type: 'integer', example: 1762732800),
        new OA\Property(property: 'updated_at', type: 'integer', example: 1762732800),
    ],
)]
#[OA\Schema(
    schema: 'TransactionInput',
    required: ['account_id', 'category_id', 'amount', 'type', 'occurred_at'],
    properties: [
        new OA\Property(property: 'account_id', type: 'integer'),
        new OA\Property(property: 'category_id', type: 'integer'),
        new OA\Property(property: 'amount', type: 'string', description: 'Больше нуля', example: '1500.50'),
        new OA\Property(property: 'type', type: 'string', enum: ['expense', 'income'], description: 'Должен совпадать с типом категории'),
        new OA\Property(property: 'occurred_at', type: 'string', format: 'date', example: '2026-11-10'),
        new OA\Property(property: 'note', type: 'string', nullable: true, maxLength: 2000),
    ],
)]
#[OA\Schema(
    schema: 'ImportResult',
    required: ['imported'],
    properties: [
        new OA\Property(property: 'imported', type: 'integer', description: 'Сколько операций сохранено', example: 42),
    ],
)]
#[OA\Schema(
    schema: 'ImportError',
    required: ['status', 'message', 'errors'],
    properties: [
        new OA\Property(property: 'status', type: 'integer', example: 422),
        new OA\Property(property: 'message', type: 'string', example: 'Импорт отменён: в файле есть ошибки, ничего не сохранено.'),
        new OA\Property(
            property: 'errors',
            type: 'object',
            description: 'Номер строки (как в Excel) => сообщение',
            additionalProperties: new OA\AdditionalProperties(type: 'string'),
        ),
    ],
)]
// --- Схемы: бюджеты ---
#[OA\Schema(
    schema: 'Budget',
    required: ['id', 'category_id', 'month', 'currency', 'limit', 'spent', 'remaining', 'percent', 'status'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 3),
        new OA\Property(property: 'category_id', type: 'integer', example: 6),
        new OA\Property(property: 'month', type: 'string', description: 'ГГГГ-ММ', example: '2026-11'),
        new OA\Property(property: 'currency', type: 'string', example: 'KZT'),
        new OA\Property(property: 'limit', type: 'string', example: '50000.00'),
        new OA\Property(property: 'spent', type: 'string', description: 'Потрачено в валюте бюджета', example: '12000.00'),
        new OA\Property(property: 'remaining', type: 'string', description: 'Лимит минус потрачено; при перерасходе отрицательное', example: '38000.00'),
        new OA\Property(property: 'percent', type: 'integer', description: 'Целые проценты лимита; при перерасходе больше 100', example: 24),
        new OA\Property(property: 'status', type: 'string', enum: ['ok', 'warning', 'exceeded'], description: 'warning — от 80 %, exceeded — больше лимита'),
        new OA\Property(
            property: 'category',
            type: 'object',
            nullable: true,
            properties: [
                new OA\Property(property: 'id', type: 'integer'),
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'color', type: 'string', nullable: true),
                new OA\Property(property: 'icon', type: 'string', nullable: true),
            ],
        ),
        new OA\Property(property: 'created_at', type: 'integer', example: 1762732800),
        new OA\Property(property: 'updated_at', type: 'integer', example: 1762732800),
    ],
)]
#[OA\Schema(
    schema: 'BudgetInput',
    required: ['category_id', 'month', 'currency', 'limit'],
    properties: [
        new OA\Property(property: 'category_id', type: 'integer', description: 'Только категория расходов'),
        new OA\Property(property: 'month', type: 'string', description: 'ГГГГ-ММ', example: '2026-11'),
        new OA\Property(property: 'currency', type: 'string', example: 'KZT'),
        new OA\Property(property: 'limit', type: 'string', description: 'Больше нуля', example: '50000.00'),
    ],
)]
// --- Схемы: отчёты ---
#[OA\Schema(
    schema: 'ReportPeriod',
    required: ['date_from', 'date_to'],
    properties: [
        new OA\Property(property: 'date_from', type: 'string', format: 'date', example: '2026-11-01'),
        new OA\Property(property: 'date_to', type: 'string', format: 'date', example: '2026-11-30'),
    ],
)]
#[OA\Schema(
    schema: 'CategoriesReport',
    required: ['type', 'period', 'currencies'],
    properties: [
        new OA\Property(property: 'type', type: 'string', enum: ['expense', 'income']),
        new OA\Property(property: 'period', ref: '#/components/schemas/ReportPeriod'),
        new OA\Property(
            property: 'currencies',
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'currency', type: 'string', example: 'KZT'),
                    new OA\Property(property: 'total', type: 'string', example: '45000.00'),
                    new OA\Property(property: 'count', type: 'integer', description: 'Число операций'),
                    new OA\Property(
                        property: 'categories',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'category_id', type: 'integer'),
                                new OA\Property(property: 'name', type: 'string'),
                                new OA\Property(property: 'color', type: 'string', nullable: true),
                                new OA\Property(property: 'icon', type: 'string', nullable: true),
                                new OA\Property(property: 'total', type: 'string', example: '12000.00'),
                                new OA\Property(property: 'count', type: 'integer'),
                                new OA\Property(property: 'share', type: 'number', format: 'float', description: 'Доля в процентах', example: 26.7),
                            ],
                            type: 'object',
                        ),
                    ),
                ],
                type: 'object',
            ),
        ),
    ],
)]
#[OA\Schema(
    schema: 'MonthlyReport',
    required: ['period', 'currencies'],
    properties: [
        new OA\Property(property: 'period', ref: '#/components/schemas/ReportPeriod'),
        new OA\Property(
            property: 'currencies',
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'currency', type: 'string', example: 'KZT'),
                    new OA\Property(property: 'total_income', type: 'string', example: '300000.00'),
                    new OA\Property(property: 'total_expense', type: 'string', example: '210000.00'),
                    new OA\Property(property: 'balance', type: 'string', example: '90000.00'),
                    new OA\Property(
                        property: 'months',
                        type: 'array',
                        description: 'Месяцы без операций заполнены нулями',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'month', type: 'string', example: '2026-11'),
                                new OA\Property(property: 'income', type: 'string'),
                                new OA\Property(property: 'expense', type: 'string'),
                                new OA\Property(property: 'balance', type: 'string'),
                            ],
                            type: 'object',
                        ),
                    ),
                ],
                type: 'object',
            ),
        ),
    ],
)]
#[OA\Schema(
    schema: 'BalancesReport',
    required: ['accounts', 'totals'],
    properties: [
        new OA\Property(
            property: 'accounts',
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'id', type: 'integer'),
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'currency', type: 'string', example: 'KZT'),
                    new OA\Property(property: 'initial_balance', type: 'string'),
                    new OA\Property(property: 'income', type: 'string'),
                    new OA\Property(property: 'expense', type: 'string'),
                    new OA\Property(property: 'balance', type: 'string', description: 'Начальный остаток + доходы − расходы'),
                ],
                type: 'object',
            ),
        ),
        new OA\Property(
            property: 'totals',
            type: 'array',
            description: 'Итог по каждой валюте',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'currency', type: 'string', example: 'KZT'),
                    new OA\Property(property: 'balance', type: 'string'),
                ],
                type: 'object',
            ),
        ),
    ],
)]
// --- Схемы: служебные ---
#[OA\Schema(
    schema: 'Health',
    required: ['status', 'database', 'time'],
    properties: [
        new OA\Property(property: 'status', type: 'string', enum: ['ok', 'degraded']),
        new OA\Property(property: 'database', type: 'string', enum: ['up', 'down']),
        new OA\Property(property: 'time', type: 'string', format: 'date-time', description: 'UTC, формат ATOM'),
    ],
)]
final class ApiSchemas
{
}
