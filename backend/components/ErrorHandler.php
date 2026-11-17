<?php

namespace app\components;

use Throwable;
use yii\web\ErrorHandler as BaseErrorHandler;
use yii\web\HttpException;

/**
 * Единый формат ошибок API:
 *
 *   {"status": 404, "message": "Текст на русском"}
 *
 * Ошибки валидации (422) дополнительно содержат errors: {поле: сообщение}, см. Serializer.
 * В режиме отладки добавляется debug с типом исключения и местом возникновения.
 */
class ErrorHandler extends BaseErrorHandler
{
    private const DEFAULT_MESSAGES = [
        400 => 'Некорректный запрос.',
        401 => 'Требуется авторизация: токен отсутствует или недействителен.',
        403 => 'Доступ запрещён.',
        404 => 'Ресурс не найден.',
        405 => 'Метод не поддерживается для этого адреса.',
        409 => 'Конфликт данных.',
        415 => 'Неподдерживаемый тип содержимого.',
        422 => 'Ошибка валидации.',
        429 => 'Слишком много запросов. Повторите позже.',
        500 => 'Внутренняя ошибка сервера.',
    ];

    protected function convertExceptionToArray($exception): array
    {
        $status = $exception instanceof HttpException ? $exception->statusCode : 500;

        $result = [
            'status' => $status,
            'message' => $this->messageFor($exception, $status),
        ];

        if (YII_DEBUG) {
            $result['debug'] = [
                'type' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ];
        }

        return $result;
    }

    private function messageFor(Throwable $exception, int $status): string
    {
        $message = $exception instanceof HttpException ? $exception->getMessage() : '';

        // Свои сообщения (на русском) отдаём как есть; английские тексты фреймворка заменяем
        if ($message !== '' && preg_match('/\p{Cyrillic}/u', $message)) {
            return $message;
        }

        if (str_starts_with($message, 'Invalid JSON')) {
            return 'Тело запроса содержит некорректный JSON.';
        }

        return self::DEFAULT_MESSAGES[$status] ?? 'Ошибка запроса.';
    }
}
