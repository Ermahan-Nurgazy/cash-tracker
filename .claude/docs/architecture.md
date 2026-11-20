# Архитектура

Cash Tracker — трекер личных расходов и доходов. Монорепозиторий из двух независимых приложений (REST API и SPA) и конфигурации окружения.

## Общая схема

```
Браузер ──► Vite dev-сервер (node, :5173)
               │  /api/*  (прокси, VITE_PROXY_TARGET)
               ▼
            nginx (:8080) ──fastcgi──► php-fpm (Yii2, :9000) ──► PostgreSQL 16 (:5432)
```

| Сервис (docker-compose) | Образ | Роль |
|---|---|---|
| `postgres` | postgres:16-alpine | БД, данные в томе `pgdata`, healthcheck `pg_isready` |
| `php` | сборка из `docker/php` (php 8.3-fpm, pdo_pgsql, intl) | Выполняет Yii2, монтирует `./backend` |
| `nginx` | nginx:1.27-alpine | Отдаёт `backend/web`, все запросы проксирует в `index.php` (pretty URLs), `client_max_body_size 10m` |
| `node` | node:22-alpine | Запускает `npm run dev` (Vite) |

Vite проксирует `/api` на nginx, поэтому в разработке CORS не нужен. Для прямых запросов с другого origin список разрешённых задаёт `CORS_ORIGINS`.

## Ключевые принципы

1. **Изоляция данных пользователя.** Каждая пользовательская таблица содержит `user_id`. Выборки всегда фильтруются по текущему пользователю, `user_id` не принимается из запроса и не попадает в ответ. Чужая запись неотличима от несуществующей (404, не 403).
2. **Без конвертации валют.** Валюта хранится в счёте (и в бюджете). Отчёты и бюджеты разбиваются по валюте, суммы разных валют не складываются.
3. **Деньги — строки.** В БД `numeric(14,2)`, в JSON строки вида `"1234.50"`. На бэкенде расчёты ведутся в целых копейках (`cents`), `float` наружу не отдаётся. На фронтенде форматирование только через `utils/format.ts`.
4. **Stateless API.** Нет сессий и cookie-авторизации, идентификация по заголовку `Authorization: Bearer <token>`.
5. **Единый формат ошибок** `{"status", "message"[, "errors"]}`, сообщения на русском.

## Бэкенд (`backend/`)

PHP 8.3, Yii2 2.0, ActiveRecord, Codeception 5, `zircote/swagger-php` для OpenAPI.

```
backend/
  web/index.php         точка входа (YII_DEBUG, YII_ENV из окружения)
  yii                   консольная точка входа (миграции, seed)
  config/               web.php, console.php, db.php, params.php
  modules/v1/           версия 1 API
    Module.php
    controllers/        контроллеры ресурсов и служебные
    docs/               общие компоненты Swagger (ApiSpec, ApiSchemas, ApiResponses, ApiParameters)
  models/               ActiveRecord-модели, формы входа/регистрации, OwnedByUserTrait
  components/           ErrorHandler, Serializer, TransactionCsv, TransactionImporter
  commands/             SeedController (php yii seed)
  migrations/           схема БД
  tests/                Codeception: Api (HTTP-сценарии), Unit (модели)
```

Namespace `app\` = корень `backend/` (PSR-4).

### Поток запроса

1. nginx передаёт запрос в `web/index.php` → `yii\web\Application` (`config/web.php`).
2. Фильтр `corsFilter` добавляет CORS-заголовки (в том числе `Access-Control-Expose-Headers` для `X-Pagination-*`).
3. `urlManager` (строгий разбор, `enableStrictParsing`) сопоставляет путь с контроллером модуля `v1`. Явные правила идут до `yii\rest\UrlRule` (`transactions/export` и `transactions/import` должны быть раньше `transactions/{id}`).
4. Поведение `authenticator` (`HttpBearerAuth`) ищет пользователя по `access_token` (`User::findIdentityByAccessToken`). Публичные действия: `register`, `login`, `health`, `docs`.
5. Контроллер выполняет действие, ответ сериализуется в JSON (`app\components\Serializer`). Ошибки валидации модели превращаются в 422.
6. Любое исключение обрабатывает `app\components\ErrorHandler` и отдаёт единый JSON.

### Слои и их ответственность

| Слой | Класс | Что делает |
|---|---|---|
| Базовый CRUD-контроллер | `OwnedActiveController` | Наследует `yii\rest\ActiveController`. Bearer-аутентификация, фильтр выборки по `user_id`, `create` с установкой владельца, `delete` с проверкой `assertDeletable`, пагинация (по умолчанию 50, максимум 100) и сортировка. Особенности ресурса задаются методами `indexQuery()`, `sortConfig()`, `assertDeletable()` |
| Ресурсные контроллеры | `Account`, `Category`, `Transaction`, `Budget` | Фильтры списков, белые списки сортировки, запрет удаления при связанных данных (409), для операций — экспорт/импорт CSV |
| Контроллеры без модели | `AuthController`, `ReportController`, `HealthController`, `DocsController` | Регистрация/вход, агрегирующие отчёты, проверка работоспособности, Swagger |
| Валидация параметров | `QueryParamsTrait` | Разбор `stringParam`, `intParam`, `dateParam` из query-строки, при ошибке 400 |
| Модели | `User`, `Account`, `Category`, `Transaction`, `Budget` | Правила валидации, связи, формат полей в ответе (`fields()`) |
| Владение записью | `OwnedByUserTrait` | Делает `user_id` небезопасным атрибутом (`!user_id`), скрывает его в ответе, перечитывает запись после сохранения (`refresh()`), чтобы вернуть значения, посчитанные БД |
| CSV | `TransactionCsv`, `TransactionImporter` | Формат файла, разбор кодировок и разделителей; импорт «всё или ничего» в одной транзакции БД |

### Аутентификация

- Регистрация и вход выдают `access_token` (64 случайных символа), записанный в `user.access_token`. Каждый вход перевыпускает токен, поэтому действует только один сеанс.
- `logout` обнуляет токен.
- Пароль хранится как хеш (`Yii::$app->security->generatePasswordHash`). Неверный email и неверный пароль дают одинаковое сообщение (401).
- Токен хранится в БД открытым текстом — известное ограничение (см. README).

### Бюджеты и отчёты

- Бюджет — лимит расходов по категории на месяц в одной валюте. `spent`, `remaining`, `percent`, `status` вычисляются при сериализации (`Budget::fields()`), как сумма расходных операций категории за месяц по счетам нужной валюты. Статус: `ok`, `warning` (от 80% лимита), `exceeded` (больше лимита).
- Отчёты (`ReportController`) — SQL-агрегаты через Query Builder: `categories` (суммы и доли по категориям), `monthly` (доход/расход по месяцам, пустые месяцы заполняются нулями), `balances` (начальный остаток + доходы − расходы по каждому счёту). Результат всегда сгруппирован по валюте.

### OpenAPI

Спецификация собирается из PHP-атрибутов (`#[OA\...]`) на контроллерах и классах в `modules/v1/docs/`. Отдаётся самим API: `GET /api/v1/docs` (Swagger UI) и `GET /api/v1/docs/openapi.json`.

## Фронтенд (`frontend/`)

Vue 3 (Composition API, `<script setup lang="ts">`), TypeScript, Vite, Pinia, Vue Router, Axios, PrimeVue 4, Chart.js, Vitest.

```
frontend/src/
  main.ts          создание приложения, подключение Pinia, роутера, PrimeVue
  App.vue
  api/             http.ts (axios), функции по сущностям, errors.ts, pagination.ts, token.ts
  stores/          Pinia-хранилища
  router/          маршруты и guard
  layouts/         AppLayout.vue (каркас с меню), nav.ts (пункты меню)
  views/           страницы (*View.vue)
  components/      диалоги форм, график, форма входа, меню пользователя
  utils/           format, months, dashboard, charts, palette, download
  types/api.ts     типы контракта API
  test/setup.ts    общая настройка Vitest
```

### Слои

```
View / Component ──► Store (Pinia) ──► api/*.ts ──► http.ts (axios) ──► /api/v1
```

- **`api/`** — единственное место, где делаются HTTP-запросы. `http.ts` подставляет `Authorization: Bearer`, разбирает ошибки в `ApiError` (`toApiError`), а при 401 (кроме `/auth/login` и `/auth/register`) сбрасывает токен и вызывает обработчик выхода. Для скачивания файлов (`responseType: 'blob'`) тело ошибки разбирается из Blob в JSON.
- **`stores/`** — состояние данных (списки, загрузка, ошибки). Локальное состояние формы остаётся в компоненте. `dashboard` защищён от гонок номером запроса. При выходе `auth.reset()` очищает все хранилища, чтобы данные прежнего пользователя не попали следующему.
- **`router/`** — маршруты с `meta.requiresAuth`, `meta.guestOnly`, `meta.title`. Guard восстанавливает пользователя по токену (`auth.restore()`), перенаправляет гостей на `/login?redirect=…`, авторизованных уводит со страниц входа. `safeRedirect` допускает только внутренние пути (защита от open redirect).
- **`utils/`** — форматирование сумм и дат, работа с месяцами, расчёты дашборда, настройка графиков, палитра категорий и список валют.

### Маршруты

| Путь | Страница | Доступ |
|---|---|---|
| `/` | Дашборд | после входа |
| `/transactions` | Операции | после входа |
| `/categories` | Категории | после входа |
| `/budgets` | Бюджеты | после входа |
| `/accounts` | Счета | после входа |
| `/login`, `/register` | Вход, регистрация | только гости |
| остальные | redirect на `/` | |

### Хранилища

`auth`, `theme` (светлая/тёмная, класс `app-dark`), `accounts`, `categories`, `transactions` (фильтры, страницы, `PER_PAGE = 20`), `recentTransactions`, `dashboard`, `budgets`, `health`.

## Тестирование

| Слой | Инструмент | Где | Что проверяется |
|---|---|---|---|
| Бэкенд, API | Codeception (suite `Api`) | `backend/tests/Api/*Cest.php` | HTTP-сценарии: успех, 401, 404 чужой записи, 422, граничные случаи. Общая база — `ApiBase` |
| Бэкенд, модели | Codeception (suite `Unit`) | `backend/tests/Unit` | Логика моделей и расчётов |
| Фронтенд | Vitest + Vue Test Utils (jsdom) | `__tests__/*.spec.ts` рядом с кодом | Поведение: запросы, отображение, хранилища, утилиты. HTTP мокается |

Бэкенд-тесты идут в отдельной БД `cash_tracker_test`.

## Безопасность

- Пароли — хеши, длина 8–72 символа.
- Запросы к БД — Query Builder и параметры; фильтры и сортировка — по белым спискам; `LIKE` экранируется.
- CSV-экспорт защищён от формул Excel (ячейки с `=`, `+`, `-`, `@` получают апостроф).
- Ограничения целостности задаются и в миграциях, и в правилах моделей.
- В `prod` ответы об ошибках не содержат поле `debug`, `seed` требует `--force`.

Известные ограничения перечислены в README, раздел «Известные ограничения».
