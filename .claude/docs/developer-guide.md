# Гайд для разработчиков

Практическое руководство: как запустить проект, где что лежит и как добавлять функциональность. Правила кода по слоям — в `backend/CLAUDE.md` и `frontend/CLAUDE.md`, правила коммитов, веток и PR — в корневом `CLAUDE.md`. Архитектура — [architecture.md](architecture.md), API — [api.md](api.md), БД — [database.md](database.md).

## 1. Запуск

Нужны Docker и Docker Compose. Все команды выполняются в **корне репозитория**.

```bash
cp .env.example .env                                   # поменяйте DB_PASSWORD и COOKIE_VALIDATION_KEY
docker compose run --rm php composer install
docker compose run --rm node npm ci
docker compose up -d --build
docker compose exec php php yii migrate --interactive=0
docker compose exec php php yii seed --transactions    # демо-данные: demo@example.com / demo12345
```

| Что | Адрес |
|---|---|
| Приложение | http://localhost:5173 |
| API | http://localhost:8080/api/v1 |
| Swagger UI | http://localhost:8080/api/v1/docs |

Остановка: `docker compose down` (БД сохраняется), `docker compose down -v` (БД удаляется).

Логи: `docker compose logs -f php nginx node`. Логи Yii (error, warning) — в `backend/runtime/logs/`.

## 2. Проверки

```bash
# Бэкенд (отдельная БД; создать один раз)
docker compose exec postgres psql -U cash -d cash_tracker -c "CREATE DATABASE cash_tracker_test"
docker compose exec -e DB_NAME=cash_tracker_test php php yii migrate --interactive=0
docker compose exec php php vendor/bin/codecept run
docker compose exec php php vendor/bin/codecept run Api AccountCest        # один файл
docker compose exec php php vendor/bin/openapi modules -o /tmp/openapi.json # OpenAPI без [warning] и [error]

# Фронтенд
docker compose exec node npm run test
docker compose exec node npm run lint
docker compose exec node npm run type-check
docker compose exec node npm run build
docker compose exec node npx prettier --check .
```

Перед коммитом и PR всё перечисленное должно проходить. После новой миграции применяйте её и к тестовой БД.

## 3. Процесс работы

1. Ветка от актуальной `main`: `feature/…`, `fix/…`, `docs/…` (латиница, дефисы). В `main` напрямую не коммитим.
2. Один коммит — одно логическое изменение, формат Conventional Commits: `feat(frontend): страница бюджетов`. Описание по-русски, без соавторства и подписей инструментов.
3. Коммит и push — только по просьбе владельца задачи. Хуки не пропускаем.
4. PR в `main` с описанием по шаблону из `CLAUDE.md`; мерж (предпочтительно squash) делает человек. После слияния ветка удаляется.
5. Выполненные шаги отмечаются в `.claude/plans/cash-tracker-plan.md`.
6. При добавлении функциональности проверьте и актуализируйте документы в `.claude/docs/`.

## 4. Рецепт: новый ресурс в бэкенде (на примере сущности `Thing`)

1. **Миграция.** `docker compose exec php php yii migrate/create create_thing_table`. Таблица с `user_id` (FK на `user`, CASCADE), ограничениями (`unique`, `CHECK`), индексами, обязательным `safeDown`. Примените к основной и тестовой БД.
2. **Модель** `models/Thing.php`: `ActiveRecord` + `TimestampBehavior` + `use OwnedByUserTrait;`. Правила валидации дублируют ограничения БД; связи на `account`/`category` проверяйте через `exist` с условием `user_id`. PHPDoc на каждом методе.
3. **Контроллер** `modules/v1/controllers/ThingController.php`, наследник `OwnedActiveController`: `$modelClass`, `sortConfig()` (белый список полей), при необходимости `indexQuery()` (фильтры через `QueryParamsTrait`, `->with()` для связей против N+1) и `assertDeletable()` (бросает `ConflictHttpException`).
4. **Маршрут.** Добавьте `'api/v1/things' => 'v1/thing'` в список `controller` правила `yii\rest\UrlRule` в `config/web.php`. Нестандартные адреса (как `transactions/export`) — явными правилами **выше** `UrlRule`, с версией «с методом» и «без» (для ответа 405).
5. **Swagger.** `#[OA\Get|Post|Put|Patch|Delete]` на класс контроллера (для унаследованных CRUD-действий) со всеми кодами ответов; схему `Thing` добавьте в `modules/v1/docs/ApiSchemas.php`, повторяющиеся ответы и параметры берите по `$ref` из `ApiResponses`/`ApiParameters`. Проверьте `vendor/bin/openapi modules`: предупреждений быть не должно.
6. **Тесты.** `tests/Api/ThingCest.php` на базе `ApiBase`: успех, 401, 404 для чужой записи, 422, граничные случаи, 409 при удалении (если применимо). Логика модели — в `tests/Unit`.
7. **Документация.** README (раздел «API»), `.claude/docs/api.md`, `database.md`, при необходимости `architecture.md`.
8. **Фронтенд** — см. следующий раздел.

Правила, которые легко нарушить:

- Чужая запись — всегда 404, не 403. Не принимайте `user_id` из тела, не отдавайте его в ответе.
- Суммы не переводите во `float` для ответа: используйте строки и расчёт в копейках (см. `Budget`, `ReportController`).
- Не складывайте суммы разных валют.
- Пользовательский ввод в SQL — только через параметры Query Builder; сортировка и фильтры — по белым спискам.
- Ошибки — исключениями Yii; свой формат ответа об ошибке не делайте.

## 5. Рецепт: новая страница во фронтенде

1. **Типы** в `src/types/api.ts` — по контракту API.
2. **API-функции** в `src/api/<сущность>.ts` через `http` (не вызывайте axios из компонентов). Тест в `src/api/__tests__/`.
3. **Хранилище** `src/stores/<сущность>.ts` (Pinia, setup-стиль): список, `loading`, `error`, действия, `reset()`. Подключите `reset()` в `auth.reset()` — данные прежнего пользователя не должны остаться после выхода. Для запросов, которые можно перебить (смена фильтра), используйте счётчик запроса, как в `dashboard`.
4. **Компоненты.** Страница `views/ThingView.vue`, формы — `components/ThingFormDialog.vue`. Только `<script setup lang="ts">`, без `any`, UI из PrimeVue и токенов темы (`var(--p-...)`), без жёстких цветов (есть светлая и тёмная темы), `<style scoped>`.
5. **Ошибки.** Разбор через `toApiError`: 422 — под полями формы (`errors`), остальное — сообщением в форме.
6. **Маршрут** в `router/index.ts` с `meta.title` и (для приватных страниц) под родителем с `requiresAuth`; пункт меню в `layouts/nav.ts`.
7. **Форматирование.** Суммы и даты — только через `utils/format.ts`; месяцы — `utils/months.ts`.
8. **Тесты** `__tests__/*.spec.ts`: HTTP мокается, проверяйте поведение (что видит пользователь, какие запросы уходят).
9. Стиль: Prettier (без `;`, одинарные кавычки, ширина 100), `npm run format`.

## 6. Рецепт: изменение контракта API

В одной задаче: контроллер/модель → Swagger-атрибуты → маршрут → тесты бэкенда → README → `.claude/docs/api.md` → `frontend/src/types/api.ts` → `frontend/src/api/` и хранилища → тесты фронтенда. Если старые клиенты перестанут работать — `feat!:` в заголовке коммита и `BREAKING CHANGE:` в футере.

## 7. Частые ситуации

| Симптом | Причина и решение |
|---|---|
| 404 на новый адрес | Не добавлено правило в `config/web.php` (включён `enableStrictParsing`), либо правило стоит после `yii\rest\UrlRule` |
| 405 вместо ожидаемого ответа | В `verbs()` контроллера не указан метод |
| 401 на всех запросах | Нет `Authorization: Bearer …` или вход выполнен на другом устройстве (токен перевыпущен) |
| Тесты бэкенда падают на отсутствующих таблицах | Миграции не применены к `cash_tracker_test` |
| Swagger не показывает endpoint | Нет атрибутов `#[OA\…]` или ошибка в них; запустите `vendor/bin/openapi modules` и смотрите `[warning]`/`[error]` |
| Кириллица в `curl` на Windows портится | Передавайте тело из файла: `--data-binary @body.json` |
| Сломанный CORS при прямых запросах к API | Origin не указан в `CORS_ORIGINS`; через Vite-прокси CORS не нужен |
| Фронтенд не видит API | Проверьте `VITE_API_URL` и `VITE_PROXY_TARGET` (в Docker — `http://nginx`) |
| `seed` отказывается работать | `YII_ENV=prod`: нужен `--force` |

## 8. Переменные окружения

Описаны в `.env.example` и README. Секреты (`.env`, пароли, ключи) не коммитятся и не публикуются в ответах/PR. В `prod`: `YII_DEBUG=false`, `YII_ENV=prod`, случайный `COOKIE_VALIDATION_KEY`, надёжный `DB_PASSWORD`, `CORS_ORIGINS` только с реальными доменами.
