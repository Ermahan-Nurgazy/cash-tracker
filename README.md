# Cash Tracker

Трекер личных расходов и доходов: счета в разных валютах, категории, операции, отчёты, дашборд, бюджеты с лимитами по категориям, импорт и экспорт в CSV.

Монорепозиторий:

- `backend/`: REST API на Yii2 (ActiveRecord, PostgreSQL, Bearer-токены)
- `frontend/`: SPA на Vue 3 + TypeScript + Vite
- `docker/`: конфигурация окружения (php-fpm, nginx)
- `.claude/plans/`: план работ с чек-листом

## Возможности

- Регистрация и вход, у каждого пользователя свои данные.
- Счета с валютой и начальным остатком, категории расходов и доходов (цвет, иконка).
- Операции: добавление, правка, удаление, фильтры (период, счёт, категория, тип, поиск по заметке), сортировка, страницы.
- Дашборд: итоги месяца, расходы по категориям, динамика за 12 месяцев (график и таблица), остатки по счетам.
- Бюджеты: лимит расходов по категории на месяц, остаток, процент, статус «в пределах / на исходе / превышен».
- Импорт и экспорт операций в CSV.
- Светлая и тёмная темы.

Суммы в разных валютах не складываются и не конвертируются: отчёты и бюджеты разбиты по валюте счёта.

## Стек

| Слой | Технологии |
|---|---|
| Окружение | Docker Compose (php 8.3-fpm, nginx, postgres 16, node 22) |
| Бэкенд | Yii2 2.0, ActiveRecord, миграции Yii, Codeception 5 |
| Фронтенд | Vue 3, TypeScript, Vite, Pinia, Vue Router, Axios, PrimeVue 4, Chart.js, Vitest |

## Быстрый старт

Нужны Docker и Docker Compose. Все команды выполняются в корне репозитория.

```bash
# 1. Переменные окружения (при необходимости отредактируйте значения)
cp .env.example .env

# 2. Зависимости
docker compose run --rm php composer install
docker compose run --rm node npm ci

# 3. Запуск
docker compose up -d --build

# 4. Таблицы в базе
docker compose exec php php yii migrate --interactive=0

# 5. Демо-данные (необязательно)
docker compose exec php php yii seed --transactions
```

Адреса:

| Что | Адрес |
|---|---|
| Приложение (Vite) | http://localhost:5173 |
| API | http://localhost:8080/api/v1 |
| Проверка API | http://localhost:8080/api/v1/health |

Демо-пользователь после `seed`: `demo@example.com` / `demo12345`. Можно и зарегистрироваться на странице входа.

Vite проксирует `/api` на nginx, поэтому фронтенд работает без настройки CORS. Остановить окружение: `docker compose down` (данные БД сохраняются в томе `pgdata`; `docker compose down -v` удаляет и их).

## Переменные окружения

Файл `.env` в корне читает Docker Compose.

| Переменная | По умолчанию | Назначение |
|---|---|---|
| `DB_HOST`, `DB_PORT` | `postgres`, `5432` | Адрес PostgreSQL для контейнеров |
| `DB_NAME`, `DB_USER`, `DB_PASSWORD` | `cash_tracker`, `cash`, `change_me` | База и учётные данные. Пароль смените |
| `YII_DEBUG`, `YII_ENV` | `true`, `dev` | Режим отладки и окружение Yii. В продакшене `false` и `prod` |
| `COOKIE_VALIDATION_KEY` | `change_me_to_random_string` | Ключ Yii. Задайте случайную строку |
| `CORS_ORIGINS` | `http://localhost:5173` | Разрешённые Origin через запятую |
| `NGINX_PORT`, `POSTGRES_PORT`, `VITE_PORT` | `8080`, `5432`, `5173` | Порты на хосте |
| `VITE_API_URL` | `/api/v1` | Базовый адрес API для фронтенда |

В режиме `prod` ответы об ошибках не содержат поле `debug`, а `php yii seed` отказывается работать без `--force`.

## Демо-данные: `php yii seed`

Команда идемпотентна, повторный запуск ничего не дублирует.

```bash
docker compose exec php php yii seed                    # пользователь, счёт «Наличные», категории
docker compose exec php php yii seed --transactions     # плюс 6 месяцев операций и бюджеты на текущий месяц
docker compose exec php php yii seed --email=me@example.com --password=secret123 --currency=USD
```

## Тесты и проверки

```bash
# Бэкенд: Codeception, используется отдельная БД cash_tracker_test
docker compose exec postgres psql -U cash -d cash_tracker -c "CREATE DATABASE cash_tracker_test"   # один раз
docker compose exec -e DB_NAME=cash_tracker_test php php yii migrate --interactive=0                # один раз и после новых миграций
docker compose exec php php vendor/bin/codecept run

# Фронтенд
docker compose exec node npm run test            # Vitest
docker compose exec node npm run test:coverage   # с отчётом о покрытии
docker compose exec node npm run lint            # ESLint
docker compose exec node npm run type-check      # vue-tsc
docker compose exec node npm run build           # production-сборка
docker compose exec node npx prettier --check .
```

Если пользователь БД называется не `cash`, подставьте своё значение из `DB_USER`.

## Структура бэкенда

```
backend/
  commands/      консольные команды (seed)
  components/    ErrorHandler, Serializer, импорт и экспорт CSV
  config/        web.php (маршруты, CORS), console.php, params.php
  migrations/    user, account, category, transaction, budget
  models/        ActiveRecord-модели и формы
  modules/v1/    контроллеры API версии 1
  tests/         Codeception: Api (сценарии HTTP) и Unit (модели)
```

## API

База: `/api/v1`. Формат: JSON, `Content-Type: application/json`. Все адреса, кроме регистрации, входа и `health`, требуют заголовок `Authorization: Bearer <access_token>`.

### Соглашения

- **Суммы** передаются строками с двумя знаками: `"1234.50"`. **Даты**: `ГГГГ-ММ-ДД`, месяцы: `ГГГГ-ММ`.
- **Ошибки** всегда в одном формате: `{"status": 404, "message": "Текст на русском"}`. Ошибки валидации (422) содержат ещё `errors` с первым сообщением по каждому полю: `{"status": 422, "message": "Ошибка валидации.", "errors": {"amount": "…"}}`.
- **Коды:** 200, 201 (создание, заголовок `Location`), 204 (удаление), 400 (неверный параметр запроса), 401, 404, 405 (с заголовком `Allow`), 409 (нельзя удалить: есть связанные данные), 413 (слишком большой файл), 422.
- **Изоляция:** чужая запись неотличима от несуществующей (404). Поле `user_id` не принимается из тела запроса и в ответах не показывается.
- **Списки:** параметры `page`, `per-page` (по умолчанию 50, максимум 100) и `sort` (`sort=name`, `sort=-name`). Пагинация в заголовках `X-Pagination-Total-Count`, `X-Pagination-Current-Page`, `X-Pagination-Page-Count`, `X-Pagination-Per-Page`.
- **Токен:** каждый вход выдаёт новый токен, предыдущий перестаёт действовать (один активный сеанс). Выход (`logout`) сбрасывает токен.

### Аутентификация

| Метод и адрес | Тело | Ответ |
|---|---|---|
| `POST /auth/register` | `{email, password}` (пароль 8–72 символа) | 201 `{user, access_token}` |
| `POST /auth/login` | `{email, password}` | 200 `{user, access_token}` |
| `GET /auth/me` | | 200 пользователь |
| `POST /auth/logout` | | 204 |

### Счета: `/accounts`

CRUD: `GET /accounts`, `POST /accounts`, `GET|PUT|PATCH|DELETE /accounts/{id}`.

Поля: `name` (до 100 символов, уникально у пользователя), `currency` (три латинские буквы, `KZT`), `initial_balance` (по умолчанию `"0.00"`). Валюту счёта с операциями менять нельзя. Счёт с операциями удалить нельзя (409).

### Категории: `/categories`

CRUD, как у счетов. Фильтр списка: `type=expense|income`.

Поля: `name`, `type` (`expense` или `income`, неизменяем, если есть операции), `color` (`#RRGGBB`, необязательно), `icon` (необязательно). Категорию с операциями удалить нельзя (409). Бюджеты категории удаляются вместе с ней.

### Операции: `/transactions`

CRUD. Поля: `account_id`, `category_id`, `amount` (больше нуля), `type`, `occurred_at`, `note` (до 2000 символов). Тип операции должен совпадать с типом категории. В ответе есть вложенные `account {id, name, currency}` и `category {id, name, type, color, icon}`.

Фильтры списка: `date_from`, `date_to` (включительно), `account_id`, `category_id`, `type`, `q` (поиск по заметке). Сортировка: `occurred_at` (по умолчанию `-occurred_at`), `amount`, `created_at`.

### Бюджеты: `/budgets`

CRUD. Поля при создании: `category_id` (только расходная), `month` (`2026-11`), `currency`, `limit` (больше нуля). Уникальность: категория + месяц + валюта.

В ответе кроме полей бюджета:

| Поле | Значение |
|---|---|
| `spent` | Потрачено по категории за месяц на счетах этой валюты |
| `remaining` | Лимит минус потрачено, при перерасходе отрицательное |
| `percent` | Целые проценты лимита, при перерасходе больше 100 |
| `status` | `ok`, `warning` (от 80%) или `exceeded` (больше лимита) |

Фильтры списка: `month`, `category_id`, `currency`. Сортировка: `month` (по умолчанию `-month`), `limit`, `created_at`.

### Отчёты: `/reports/*`

Каждый отчёт разбит на блоки по валюте, суммы без конвертации.

| Адрес | Параметры | Содержимое |
|---|---|---|
| `GET /reports/categories` | `date_from`, `date_to` (по умолчанию текущий месяц), `type` (по умолчанию `expense`), `account_id` | Суммы, число операций и доля по категориям |
| `GET /reports/monthly` | `date_from`, `date_to` (по умолчанию последние 12 месяцев, не больше 60), `account_id` | По месяцам: доходы, расходы, итог. Пустые месяцы заполнены нулями |
| `GET /reports/balances` | | Остатки по счетам (начальный остаток + доходы − расходы) и итог по валютам |

### Импорт и экспорт CSV

**`GET /transactions/export`** принимает те же фильтры, что и список (без сортировки и страниц), и возвращает файл `text/csv`. До 10 000 операций за раз.

**`POST /transactions/import`**: тело запроса и есть CSV-файл с `Content-Type: text/csv`, до 1 МБ и 2000 строк.

```bash
curl -X POST http://localhost:8080/api/v1/transactions/import \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: text/csv" \
  --data-binary @operations.csv
```

Формат файла:

- Столбцы: `Дата;Тип;Сумма;Валюта;Счёт;Категория;Заметка`. Обязательны первые три, а также `Счёт` и `Категория`. Столбцы ищутся по заголовку, на русском или английском (`date, type, amount, currency, account, category, note`).
- Экспорт пишет UTF-8 с BOM, разделитель `;`, суммы с запятой. Импорт понимает также `,` и табуляцию, Windows-1251, суммы с точкой и пробелами, даты `ДД.ММ.ГГГГ`, типы `расход/доход` и `expense/income`.
- Счета и категории должны существовать, названия сверяются без учёта регистра. Если валюта указана, она должна совпадать с валютой счёта.
- Всё или ничего: при любой ошибке не сохраняется ничего, в ответе 422 перечень ошибок по строкам, номера как в Excel (заголовок — строка 1).
- Повторный импорт того же файла создаст дубликаты: операции не сверяются с существующими.
- В экспорте ячейки, начинающиеся с `=`, `+`, `-`, `@`, получают апостроф: защита от выполнения формул в Excel.

### Служебные

`GET /health`: проверка работы API и подключения к базе, без авторизации.

### Пример сеанса

```bash
API=http://localhost:8080/api/v1

TOKEN=$(curl -s -X POST $API/auth/login -H 'Content-Type: application/json' \
  -d '{"email":"demo@example.com","password":"demo12345"}' | sed 's/.*"access_token":"\([^"]*\)".*/\1/')

curl -s $API/accounts -H "Authorization: Bearer $TOKEN"

curl -s -X POST $API/transactions -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"account_id":1,"category_id":6,"amount":"1500.50","type":"expense","occurred_at":"2026-11-10","note":"Продукты"}'

curl -s "$API/reports/categories?date_from=2026-11-01&date_to=2026-11-30" -H "Authorization: Bearer $TOKEN"
```

В Windows при отправке кириллицы в теле запроса `curl` может испортить кодировку: передавайте тело из файла (`--data-binary @body.json`).

## Фронтенд

```
frontend/src/
  api/          axios-клиент и функции по сущностям, разбор ошибок
  components/   диалоги форм, график, форма входа
  layouts/      каркас приложения с меню
  router/       маршруты и guard (защита страниц, redirect после входа)
  stores/       Pinia: auth, theme, accounts, categories, transactions, dashboard, budgets
  utils/        форматирование, месяцы, расчёты дашборда, настройка графиков
  views/        страницы: вход, регистрация, дашборд, операции, категории, бюджеты, счета
```

Токен хранится в `localStorage`. При ответе 401 приложение сбрасывает сеанс и открывает страницу входа.

## Известные ограничения

- Токен доступа хранится в БД открытым текстом, а не хешем.
- Нет ограничения числа попыток входа.
- Один активный сеанс на пользователя: вход с другого устройства завершает прежний.
- Курсы валют не используются: итоги по разным валютам показываются отдельно.
- Импорт не отслеживает дубликаты, экспорт ограничен 10 000 операций.
- Ответ 404 на несуществующий адрес не содержит CORS-заголовков (важно только при запросах с другого origin без прокси Vite).
- Ответы API содержат cookie `_csrf`, хотя сеансы не используются.
