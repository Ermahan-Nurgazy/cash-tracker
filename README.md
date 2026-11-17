# Cash Tracker

Трекер расходов. Монорепозиторий:

- `backend/` — REST API на Yii2 (ActiveRecord, PostgreSQL)
- `frontend/` — SPA на Vue 3 + TypeScript + Vite
- `docker/` — конфигурация окружения (php-fpm, nginx)

## Стек

| Слой | Технологии |
|---|---|
| Окружение | Docker Compose (php 8.3-fpm, nginx, postgres 16, node 22) |
| Бэкенд | Yii2, ActiveRecord, миграции Yii, Codeception |
| Фронтенд | Vue 3, TypeScript, Vite, Pinia, Vue Router, Axios, PrimeVue |

## Быстрый старт

Зависимости пока не установлены. Инструкция по запуску появится после этапа 2.

```
cp .env.example .env
```
