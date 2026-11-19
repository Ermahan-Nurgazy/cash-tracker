# Фронтенд (Vue 3)

SPA: Vue 3 (Composition API, `<script setup lang="ts">`), TypeScript, Vite, Pinia, Vue Router, Axios, PrimeVue 4, Chart.js. Тесты — Vitest + Vue Test Utils (jsdom). Контракт API описан в [README](../README.md), раздел «API».

## Структура

```
src/
  api/          axios-клиент (http.ts) и функции по сущностям, разбор ошибок (errors.ts)
  components/   диалоги форм, график, форма входа
  layouts/      каркас приложения с меню
  router/       маршруты и guard (защита страниц, redirect после входа)
  stores/       Pinia: auth, theme, accounts, categories, transactions, dashboard, budgets
  types/        типы ответов и запросов API (api.ts)
  utils/        форматирование, месяцы, расчёты дашборда, настройка графиков
  views/        страницы: вход, регистрация, дашборд, операции, категории, бюджеты, счета
  test/         общая настройка тестов (setup.ts)
```

Тесты лежат рядом с кодом в папках `__tests__/` (`*.spec.ts`). Импорты — через алиас `@/` (корень `src/`).

## Команды

Выполняются в корне репозитория:

```bash
docker compose exec node npm run dev             # уже запущено сервисом node, порт 5173
docker compose exec node npm run test            # Vitest
docker compose exec node npm run test:coverage   # с отчётом о покрытии
docker compose exec node npm run lint            # ESLint
docker compose exec node npm run type-check      # vue-tsc
docker compose exec node npm run build           # type-check + production-сборка
docker compose exec node npx prettier --check .
docker compose exec node npm run format          # prettier --write src/
```

Dev-сервер Vite проксирует `/api` на nginx, поэтому CORS на локальной машине не нужен. Базовый адрес API задаёт `VITE_API_URL`.

## Правила кода

- Только `<script setup lang="ts">`, строгие типы, без `any`. Типы данных API лежат в `src/types/api.ts`: меняется контракт — меняются и они.
- Форматирование — Prettier: без точек с запятой, одинарные кавычки, ширина строки 100. Перед коммитом проходят `lint`, `type-check`, `prettier --check`.
- Тексты интерфейса, комментарии и сообщения об ошибках — по-русски; идентификаторы, имена файлов и компонентов — по-английски. Компоненты называются в PascalCase (`AccountFormDialog.vue`), страницы — `*View.vue`.
- Запросы к API делаются только через функции из `src/api/`, а не напрямую из компонентов. Ошибки разбираются через `toApiError`: ошибки валидации (422) показываются у полей, остальные — сообщением в форме.
- Состояние данных (списки, загрузка, ошибки) живёт в Pinia-хранилищах, компоненты берут его оттуда. Локальное состояние формы и диалога остаётся в компоненте.
- UI собирается из компонентов PrimeVue и токенов темы (`var(--p-...)`), без жёстко заданных цветов: приложение поддерживает светлую и тёмную темы. Стили компонентов — `<style scoped>`.
- Суммы приходят строками (`"1234.50"`). Форматирование для показа — только через `src/utils/format.ts`, суммы в разных валютах не складываются.
- Токен хранится в `localStorage`. При ответе 401 `http.ts` сбрасывает сеанс и ведёт на страницу входа; эту логику не дублируйте.
- Новая страница: компонент в `views/`, маршрут в `router/index.ts` с `meta.title` и при необходимости `requiresAuth` или `guestOnly`, пункт меню в `layouts/AppLayout.vue`.
- Для повторяющейся логики есть `utils/`: сначала проверьте, нет ли там нужной функции.

## Тесты

- Новый компонент, хранилище и функция в `api/` или `utils/` получают `*.spec.ts` в соседней `__tests__/`.
- HTTP в тестах мокается, обращений к реальному API нет. Общая настройка — `src/test/setup.ts`.
- Проверяйте поведение (что видит пользователь, какие запросы уходят), а не детали реализации.
- Перед коммитом `npm run test` проходит целиком.
