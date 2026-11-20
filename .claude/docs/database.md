# Схема БД и назначение полей

СУБД: PostgreSQL 16. Схема создаётся миграциями Yii (`backend/migrations/`). Имена таблиц — через префикс `{{%...}}` (по умолчанию без префикса): `user`, `account`, `category`, `transaction`, `budget`.

Общие правила:

- Первичный ключ каждой таблицы — `id` (serial).
- `created_at` / `updated_at` — целые секунды Unix (UTC), заполняются `TimestampBehavior`. У `category` только `created_at`.
- Суммы — `numeric(14,2)`, даты — `date`.
- Все таблицы, кроме `user`, содержат `user_id` — владельца записи. Он задаётся сервером, а не клиентом.

## Связи

```
user 1 ──< account 1 ──< transaction >── 1 category >── 1 user
  │                           │              │
  │                           └── user       └──< budget
  └──< category, budget (user_id)
```

| Внешний ключ | Ссылается на | При удалении родителя |
|---|---|---|
| `account.user_id` | `user.id` | CASCADE |
| `category.user_id` | `user.id` | CASCADE |
| `transaction.user_id` | `user.id` | CASCADE |
| `transaction.account_id` | `account.id` | **RESTRICT** (счёт с операциями удалить нельзя) |
| `transaction.category_id` | `category.id` | **RESTRICT** (категорию с операциями удалить нельзя) |
| `budget.user_id` | `user.id` | CASCADE |
| `budget.category_id` | `category.id` | CASCADE (бюджет не живёт без категории) |

На уровне API RESTRICT проявляется ответом 409 (проверка `assertDeletable` срабатывает раньше ошибки БД).

## `user` — пользователи

Миграция `m261117_100000_create_user_table`.

| Поле | Тип | Назначение |
|---|---|---|
| `id` | serial PK | Идентификатор |
| `email` | varchar(255), NOT NULL | Логин. Хранится в нижнем регистре без пробелов. Уникален (`ux_user_email`) |
| `password_hash` | varchar(255), NOT NULL | Хеш пароля (`Security::generatePasswordHash`). Наружу не отдаётся |
| `auth_key` | varchar(32), NOT NULL | Ключ `IdentityInterface` (32 случайных символа). Наружу не отдаётся |
| `access_token` | varchar(64), NULL | Bearer-токен текущего сеанса. Перевыпускается при каждом входе, обнуляется при выходе. Уникален (`ux_user_access_token`). Наружу не отдаётся. Хранится открытым текстом (известное ограничение) |
| `created_at`, `updated_at` | integer, NOT NULL | Время создания и изменения |

## `account` — счета

Миграция `m261117_100100_create_account_table`.

| Поле | Тип | Назначение |
|---|---|---|
| `id` | serial PK | |
| `user_id` | integer, NOT NULL, FK | Владелец |
| `name` | varchar(100), NOT NULL | Название («Наличные», «Карта Kaspi»). Уникально у пользователя (`ux_account_user_name`) |
| `currency` | char(3), NOT NULL | Код валюты (`KZT`, `USD`). Определяет валюту всех операций счёта. Не меняется, если по счёту есть операции (проверка в модели) |
| `initial_balance` | numeric(14,2), NOT NULL, default 0 | Остаток на момент заведения счёта. Текущий остаток = `initial_balance` + доходы − расходы |
| `created_at`, `updated_at` | integer, NOT NULL | |

Индексы: `ux_account_user_name (user_id, name)` — уникальный.

## `category` — категории

Миграция `m261117_100200_create_category_table`.

| Поле | Тип | Назначение |
|---|---|---|
| `id` | serial PK | |
| `user_id` | integer, NOT NULL, FK | Владелец |
| `name` | varchar(100), NOT NULL | Название («Продукты», «Зарплата») |
| `type` | varchar(10), NOT NULL | `expense` или `income`. CHECK `ck_category_type`. Не меняется, если по категории есть операции |
| `color` | varchar(7), NULL | Цвет `#RRGGBB` для графиков и меток |
| `icon` | varchar(50), NULL | Имя иконки (PrimeIcons) |
| `created_at` | integer, NOT NULL | |

Индексы: `ux_category_user_name_type (user_id, name, type)` — уникальный; одно и то же название допустимо для расходной и доходной категории («Подарки»).

## `transaction` — операции

Миграция `m261117_100300_create_transaction_table`.

| Поле | Тип | Назначение |
|---|---|---|
| `id` | serial PK | |
| `user_id` | integer, NOT NULL, FK | Владелец. Дублирует владельца счёта для быстрой фильтрации и изоляции |
| `account_id` | integer, NOT NULL, FK | Счёт. Валюта операции — валюта счёта |
| `category_id` | integer, NOT NULL, FK | Категория |
| `amount` | numeric(14,2), NOT NULL | Сумма, всегда положительная. Направление определяет `type`. CHECK `ck_transaction_amount` (`amount > 0`) |
| `type` | varchar(10), NOT NULL | `expense` или `income`. CHECK `ck_transaction_type`. Должен совпадать с типом категории (проверка в модели) |
| `occurred_at` | date, NOT NULL | Дата операции (не время создания записи) |
| `note` | text, NULL | Заметка; в API ограничена 2000 символами |
| `created_at`, `updated_at` | integer, NOT NULL | |

Индексы: `ix_transaction_user_occurred (user_id, occurred_at)` — основной для списков и отчётов; `ix_transaction_account (account_id)`; `ix_transaction_category (category_id)`.

## `budget` — бюджеты

Миграция `m261119_100000_create_budget_table`.

| Поле | Тип | Назначение |
|---|---|---|
| `id` | serial PK | |
| `user_id` | integer, NOT NULL, FK | Владелец |
| `category_id` | integer, NOT NULL, FK | Расходная категория (тип проверяется в модели) |
| `period_month` | date, NOT NULL | Первое число месяца бюджета. CHECK `ck_budget_month` (`EXTRACT(DAY FROM period_month) = 1`). В API представлено полем `month` вида `ГГГГ-ММ` |
| `currency` | varchar(3), NOT NULL | Валюта лимита. Хранится в бюджете, потому что расходы могут идти со счетов разных валют, а суммы не складываются |
| `limit` | numeric(14,2), NOT NULL | Лимит расходов. CHECK `ck_budget_limit` (`"limit" > 0`). Имя `limit` — зарезервированное слово SQL, в сыром SQL его нужно брать в кавычки |
| `created_at`, `updated_at` | integer, NOT NULL | |

Индексы: `ux_budget_user_category_month_currency (user_id, category_id, period_month, currency)` — уникальный; `ix_budget_user_month (user_id, period_month)`.

Вычисляемые значения (`spent`, `remaining`, `percent`, `status`) в таблице не хранятся: считаются при выдаче из операций категории за месяц на счетах той же валюты.

## Целостность: где что проверяется

| Правило | БД | Модель/контроллер |
|---|---|---|
| Уникальность email, названия счёта, категории, бюджета | unique-индексы | `unique`-валидаторы |
| Тип `expense`/`income` | CHECK | `in`-валидатор |
| Сумма и лимит > 0 | CHECK | `number min 0.01` |
| Счёт и категория принадлежат тому же пользователю | нет (только FK на `id`) | `exist` с условием `user_id` |
| Тип операции = тип категории | нет | `validateTypeMatchesCategory` |
| Бюджет только на расходную категорию | нет | `exist` с фильтром `type = expense` |
| Валюта счёта/тип категории не меняются при наличии операций | нет | `validateCurrencyChange`, `validateTypeChange` |
| Удаление счёта/категории с операциями | RESTRICT | `assertDeletable` → 409 |

Правила, не выражаемые в БД, обязательно должны быть в модели; если правило есть в БД, дублируйте его и в модели, чтобы клиент получил 422, а не 500.

## Миграции

- Применить: `docker compose exec php php yii migrate --interactive=0`. Тестовая БД: добавьте `-e DB_NAME=cash_tracker_test`.
- Создать: `docker compose exec php php yii migrate/create <имя>` (имя файла `mYYMMDD_HHMMSS_описание`).
- Применённые миграции не редактируются: изменения — новой миграцией с обязательным `safeDown`. Коммит с областью `db`.
- Откатить последнюю: `docker compose exec php php yii migrate/down 1`.
- Журнал применённых миграций ведёт таблица `migration`.

## Демо-данные

`php yii seed` создаёт демо-пользователя (`demo@example.com` / `demo12345`), счёт «Наличные» и стандартные категории (12 расходных, 4 доходных, с цветами). С `--transactions` добавляет операции за 6 месяцев и бюджеты на текущий месяц. Команда идемпотентна; в `prod` требует `--force`.
