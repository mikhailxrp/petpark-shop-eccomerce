<?php

declare(strict_types=1);

/**
 * Глобальные обработчики ошибок PHP.
 * Подключается в public/index.php до dispatch().
 */

function registerAppErrorHandlers(): void
{
    set_exception_handler(function (Throwable $e): void {
        logException($e);
        renderErrorResponse();
    });

    set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
        if (!(error_reporting() & $errno)) {
            return false;
        }
        logError("PHP Error [{$errno}]: {$errstr}", ['file' => $errfile, 'line' => $errline]);
        return true;
    });

    register_shutdown_function(function (): void {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            logError('Fatal: ' . $error['message'], ['file' => $error['file'], 'line' => $error['line']]);
            renderErrorResponse();
        }
    });
}

function renderErrorResponse(): void
{
    if (!headers_sent()) {
        http_response_code(500);
    }

    $isDev = defined('APP_ENV') && APP_ENV === 'local';

    try {
        render('errors/500', ['isDev' => $isDev]);
    } catch (Throwable) {
        // Сам рендер вью упал (например, файл шаблона повреждён) — этот
        // код выполняется внутри глобального обработчика ошибок, поэтому
        // здесь нужен гарантированный вывод без риска новой необработанной
        // ошибки. Никаких деталей исключения — они уже ушли в лог выше.
        echo $isDev
            ? '<h1>500 — Ошибка приложения</h1><p>Смотри storage/logs/app.log</p>'
            : '<h1>Что-то пошло не так</h1><p>Попробуйте позже.</p>';
    }
}
