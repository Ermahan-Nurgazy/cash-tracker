# Новая функциональность - Создать модуль транзакций

## Контекст
Проект: Yii2 + Vue + PostgreSql
Что уже есть: Есть полноценный CRM для управления с финансами

## Задача
Создай Transactions - центральный модуль приложения для учёта доходов и расходов

## Модель данных
Добавь модель Transaction:
- id (String, uuid)
- amount (Decimal)
- type (Enum: INCOME, EXPENSE)
- description (String, nullable)
- date (DateTime)
- categoryId (String, связь с Category)
- userId (String, связь с User)
- createdAt (DateTime, @default(now()))

## Контроллер
- POST /transactions: создать транзакцию
- GET /transactions: список с query параметрами dateFrom, dateTo, type, categoryId

## Ограничения
- Не добавлять зависимости если не указано в задаче
- Псоле реализации собирай проект
