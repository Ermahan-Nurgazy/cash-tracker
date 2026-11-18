# Cash Tracker: план разработки

## Context
Делаем трекер расходов в монорепозитории `C:\OSPanel\home\cash-tracker` (сейчас папка пустая). Фронтенд на Vue, бэкенд на Yii2, база PostgreSQL, ORM ActiveRecord.
Текущий этап: создать структуру проектов и подготовить их **без установки зависимостей**. Значит, никаких `composer install` / `npm install` / `create-project`: `composer.json`, `package.json`, конфиги и каркасные файлы пишем вручную.
Работаем по шагам. После каждого шага я останавливаюсь и жду вашего одобрения, а выполненные пункты отмечаю в чек-листе ниже (`[x]`).

## Согласованный стек
| Слой | Выбор |
|---|---|
| Окружение | Docker Compose: `php-fpm 8.3` (с pdo_pgsql, intl), `nginx`, `postgres:16`, `node:22` |
| Бэкенд | Yii2 (`yiisoft/yii2 ~2.0.51`) на основе basic-шаблона, переделанного в чистый REST/JSON API (модуль `v1`, `yii\rest\ActiveController`) |
| ORM / БД | ActiveRecord + миграции Yii, PostgreSQL 16 |
| Авторизация | Bearer-токен (`HttpBearerAuth`), хеши паролей через `Yii::$app->security` |
| Тесты бэкенда | Codeception (unit + api) |
| Фронтенд | Vue 3 + TypeScript + Vite, Pinia, Vue Router, Axios |
| UI | PrimeVue 4 (+ PrimeIcons), графики через Chart.js |
| Качество фронта | ESLint (flat config) + Prettier, Vitest + @vue/test-utils, `vue-tsc` |
| Прочее | `.editorconfig`, `.gitignore`, `.env.example`, git-репозиторий |

## Структура монорепозитория
```
cash-tracker/
├── backend/                 # Yii2 REST API
│   ├── composer.json
│   ├── config/  (web.php, console.php, db.php, params.php)
│   ├── controllers/ modules/v1/controllers/
│   ├── models/  migrations/  commands/
│   ├── runtime/ web/index.php  yii
│   └── tests/
├── frontend/                # Vue 3 + Vite + TS
│   ├── package.json vite.config.ts tsconfig*.json index.html
│   ├── eslint.config.js .prettierrc
│   └── src/ (main.ts App.vue router/ stores/ api/ views/ components/ types/)
├── docker/
│   ├── php/Dockerfile  php.ini
│   └── nginx/default.conf
├── docker-compose.yml
├── .env.example  .editorconfig  .gitignore
└── README.md
```
Маршрутизация в nginx: `/api/*` уходит в Yii (`backend/web`). В разработке фронт отдаёт Vite dev server (порт 5173) с proxy `/api` на nginx.

## Модель данных (для этапа 2)
- `user`: id, email (unique), password_hash, auth_key, access_token, created_at, updated_at
- `category`: id, user_id → user, name, type (`expense`|`income`), color, icon, created_at
- `account`: id, user_id, name, currency (char 3), initial_balance numeric(14,2)
- `transaction`: id, user_id, account_id, category_id, amount numeric(14,2), type, occurred_at date, note text, created_at, updated_at; индексы по (user_id, occurred_at), category_id
- `budget` (опционально): id, user_id, category_id, period_month date, limit numeric(14,2)

---

## Чек-лист

### Этап 1. Каркас монорепозитория (текущая задача, без установки зависимостей)
- [x] 1.1 Корень: `README.md`, `.gitignore`, `.editorconfig`, `.env.example`, `git init`
- [x] 1.2 Docker: `docker-compose.yml`, `docker/php/Dockerfile` (php 8.3-fpm + pdo_pgsql, intl, composer), `docker/nginx/default.conf`, сервис postgres 16 с volume
- [x] 1.3 Бэкенд: `backend/composer.json` (yii2, yii2-debug/gii для dev, codeception), структура папок, `web/index.php`, `yii`, `config/*` (db через env, JSON-парсер запроса и ответ в JSON, urlManager, CORS, сессии отключены), модуль `v1`, health-эндпоинт `GET /api/v1/health`
- [x] 1.4 Фронтенд: `frontend/package.json`, `vite.config.ts` (alias `@`, proxy `/api`), `tsconfig*.json`, `index.html`, `src/main.ts` (PrimeVue, Pinia, Router), `App.vue`, заготовки `router/`, `stores/`, `api/http.ts` (Axios), `views/HomeView.vue`, ESLint/Prettier/Vitest-конфиги
- [x] 1.5 Проверка структуры (дерево файлов, валидность JSON/YAML) и итоговый отчёт

### Этап 2. Запуск окружения и база данных
- [x] 2.1 `docker compose build/up`, `composer install`, `npm install` (с вашего разрешения)
- [x] 2.2 Проверка: `GET /api/v1/health` и стартовая страница Vue
- [x] 2.3 Миграции: user, account, category, transaction (`budget` перенесён на шаг 5.1)
- [x] 2.4 Модели ActiveRecord с правилами валидации и связями
- [x] 2.5 Seed-команда (`yii seed`): демо-пользователь и категории

### Этап 3. API бэкенда
- [x] 3.1 Аутентификация: register, login (выдача токена), me, logout
- [x] 3.2 CRUD категорий и счетов (только свои данные, фильтр по user_id)
- [x] 3.3 CRUD транзакций: фильтры (период, категория, счёт, тип), пагинация, сортировка
- [x] 3.4 Отчёты: суммы по категориям за период, динамика по месяцам, баланс счетов
- [x] 3.5 Единый формат ошибок и валидации, CORS
- [x] 3.6 Тесты Codeception (unit для моделей, api для эндпоинтов)

### Этап 4. Фронтенд
- [x] 4.1 Axios-клиент с токеном и перехватом 401, типы API
- [x] 4.2 Страницы логина и регистрации, auth-store, guard в роутере
- [x] 4.3 Layout: меню, шапка, тема PrimeVue
- [x] 4.4 Транзакции: таблица с фильтрами, форма добавления и редактирования
- [x] 4.5 Категории и счета: управление
- [x] 4.6 Дашборд: итоги за месяц, графики по категориям и динамике
- [x] 4.7 Тесты Vitest для stores и ключевых компонентов

### Этап 5. Доводка
- [x] 5.1 Бюджеты и лимиты по категориям: миграция `budget` (id, user_id, category_id, period_month date, limit numeric(14,2)), модель, API, UI
- [x] 5.2 Импорт/экспорт CSV (опционально)
- [x] 5.3 README: запуск, переменные окружения, API
- [x] 5.4 Финальная проверка: линтеры, тесты, сценарий end-to-end вручную

## Порядок работы
- Каждый пункт выполняю отдельно, показываю, что сделано, и жду одобрения перед следующим.
- После одобрения отмечаю пункт `[x]` в этом файле.
- Зависимости ставлю и контейнеры запускаю не раньше этапа 2, только с вашего разрешения.

## Проверка этапа 1
- Дерево файлов совпадает со структурой выше.
- `composer.json` и `package.json` проходят JSON-валидацию, `docker-compose.yml` проходит `docker compose config` (только проверка, без запуска).
- Ничего не установлено: нет `vendor/` и `node_modules/`.
