# Бэкенд (Yii2)

REST API версии 1: PHP 8.3, Yii2 2.0, ActiveRecord, PostgreSQL 16, аутентификация по Bearer-токену. Контракт API (адреса, поля, коды ответов, CSV) описан в [README](../README.md), раздел «API».

## Структура

```
commands/      консольные команды (seed)
components/    ErrorHandler, Serializer, импорт и экспорт CSV
config/        web.php (маршруты, CORS), console.php, params.php
migrations/    user, account, category, transaction, budget
models/        ActiveRecord-модели и формы (LoginForm, RegisterForm)
modules/v1/    контроллеры API версии 1
tests/         Codeception: Api (сценарии HTTP) и Unit (модели)
```

Namespace `app\` соответствует корню `backend/` (PSR-4), например `app\modules\v1\controllers\AccountController`.

## Команды

Выполняются в корне репозитория (не в `backend/`):

```bash
docker compose exec php composer install
docker compose exec php php yii migrate --interactive=0       # применить миграции
docker compose exec php php yii migrate/create <имя>          # новая миграция
docker compose exec php php yii seed --transactions           # демо-данные
docker compose exec php php vendor/bin/codecept run           # все тесты
docker compose exec php php vendor/bin/codecept run Api AccountCest   # один файл
```

Тесты идут в отдельной БД `cash_tracker_test`. Её создание и миграции — по README, раздел «Тесты и проверки». После новой миграции примените её и к тестовой БД: `docker compose exec -e DB_NAME=cash_tracker_test php php yii migrate --interactive=0`.

## Правила кода

- Комментарии, PHPDoc, сообщения валидации и ошибок — по-русски, как в существующем коде. Идентификаторы — по-английски.
- Контроллеры ресурсов наследуют `OwnedActiveController` (CRUD, изоляция по пользователю, пагинация и сортировка). Особенности ресурса задаются в `sortConfig()`, `assertDeletable()` и подобных методах, а не переписыванием действий.
- В модели с владельцем (`user_id`) подключайте `OwnedByUserTrait`: владелец задаётся сервером, не берётся из тела запроса и не попадает в ответ. Все выборки идут только по данным текущего пользователя; чужая запись — 404, а не 403.
- Ошибки — через исключения Yii (`NotFoundHttpException`, `ConflictHttpException` и т. д.). Формат `{"status", "message"}` и `errors` для 422 формирует `components/ErrorHandler`, свой формат в контроллерах не делайте. В режиме `prod` в ответе нет поля `debug`.
- Суммы — `numeric(14,2)`, наружу отдаются строками с двумя знаками (`"1234.50"`), в PHP не превращаются во `float`. Даты — `ГГГГ-ММ-ДД`, месяцы — `ГГГГ-ММ`.
- Суммы в разных валютах не складываются и не конвертируются: отчёты и бюджеты группируются по валюте счёта.
- Запросы собирайте через Query Builder и параметры. Конкатенировать пользовательский ввод в SQL нельзя. Для фильтров и сортировки используйте белые списки полей.
- Ограничения целостности (`unique`, внешние ключи, `CHECK`) задавайте и в миграции, и в правилах валидации модели.
- Новые endpoint-ы регистрируются в `config/web.php` и описываются в README.

## Миграции

- Имя: `mYYMMDD_HHMMSS_описание`, класс создаётся командой `yii migrate/create`.
- Применённые и отправленные в `main` миграции не редактируются: изменения делаются новой миграцией.
- Обязательно пишите `safeDown` (или `down`), чтобы миграцию можно было откатить.
- Изменение схемы — отдельный коммит с областью `db`.

## Тесты

- Каждый endpoint покрывается сценарием в `tests/Api/*Cest.php` (общая база — `ApiBase.php`): успешный путь, 401, 404 для чужой записи, 422 и граничные случаи.
- Логика моделей и расчётов — в `tests/Unit`.
- Исправление ошибки начинается с теста, который её воспроизводит.
- Перед коммитом `codecept run` должен проходить целиком.
