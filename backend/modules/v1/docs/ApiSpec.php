<?php

namespace app\modules\v1\docs;

use OpenApi\Attributes as OA;

/**
 * Общая часть OpenAPI-спецификации API v1: описание, сервер, схема авторизации и теги.
 *
 * Класс только несёт атрибуты Swagger и в коде не используется. Операции (методы и адреса)
 * описываются атрибутами на самих контроллерах.
 */
#[OA\Info(
    version: '1.0.0',
    title: 'Cash Tracker API',
    description: 'REST API трекера личных расходов и доходов. Суммы передаются строками с двумя знаками '
        . '(«1234.50»), даты — ГГГГ-ММ-ДД, месяцы — ГГГГ-ММ. Суммы в разных валютах не складываются.',
)]
#[OA\Server(url: '/api/v1', description: 'API версии 1')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    description: 'Токен из ответа /auth/login или /auth/register: Authorization: Bearer <access_token>',
)]
#[OA\Tag(name: 'Auth', description: 'Регистрация, вход и выход')]
#[OA\Tag(name: 'Accounts', description: 'Счета пользователя')]
#[OA\Tag(name: 'Categories', description: 'Категории расходов и доходов')]
#[OA\Tag(name: 'Transactions', description: 'Операции, импорт и экспорт CSV')]
#[OA\Tag(name: 'Budgets', description: 'Лимиты расходов по категориям на месяц')]
#[OA\Tag(name: 'Reports', description: 'Отчёты по операциям (по блокам валют)')]
#[OA\Tag(name: 'Health', description: 'Проверка работоспособности')]
#[OA\Tag(name: 'Docs', description: 'Документация API')]
final class ApiSpec
{
}
