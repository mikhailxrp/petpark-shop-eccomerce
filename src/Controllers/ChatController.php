<?php

declare(strict_types=1);

namespace App\Controllers;

/**
 * Консультант в чате — POST /chat (FR-AI-003, phase-5.md Таск 6). Ответ — JSON
 * для public/assets/js/chat.js. История диалога — в сессии посетителя, не в БД.
 * Недоступность ИИ — status «fallback», без ошибок на сайте: покупка, Заказ и
 * Запись от чата не зависят. Демо-лимит (CHAT_LIMIT_REQUESTS) считается по
 * cookie посетителя.
 */
final class ChatController
{
    private const RATE_ACTION = 'chat';
    private const RATE_MAX_REQUESTS = 15;
    private const RATE_WINDOW_SECONDS = 60;

    private const DEMO_COOKIE = 'chat_demo_token';
    private const DEMO_COOKIE_PATTERN = '/^[0-9a-f]{32}$/';
    private const DEMO_COOKIE_BYTES = 16;
    private const DEMO_COOKIE_DAYS = 30;

    private const SESSION_HISTORY_KEY = 'chat_history';

    private const MESSAGES = [
        'csrf'    => 'Сессия устарела. Обновите страницу и попробуйте снова.',
        'empty'   => 'Напишите вопрос.',
        'long'    => 'Слишком длинный вопрос — сократите его до %d символов.',
        'rate'    => 'Слишком много вопросов подряд. Подождите минуту и попробуйте снова.',
        'limit'   => 'Демонстрационный лимит исчерпан: вы задали все %d вопросов.',
    ];

    public function send(): void
    {
        if (!verifyCsrfToken(input('_csrf'))) {
            $this->respond(403, 'invalid', self::MESSAGES['csrf']);
        }

        $question = trim((string) input('message'));
        if ($question === '' || !mb_check_encoding($question, 'UTF-8')) {
            $this->respond(422, 'invalid', self::MESSAGES['empty']);
        }
        if (mb_strlen($question) > CHAT_MAX_QUESTION_LENGTH) {
            $this->respond(422, 'invalid', sprintf(self::MESSAGES['long'], CHAT_MAX_QUESTION_LENGTH));
        }

        if (tooManyAttempts(self::RATE_ACTION, self::RATE_MAX_REQUESTS, self::RATE_WINDOW_SECONDS)) {
            logWarning('Чат: превышен лимит запросов');
            $this->respond(429, 'rate', self::MESSAGES['rate']);
        }
        hitRateLimit(self::RATE_ACTION);

        if (CHAT_LIMIT_REQUESTS) {
            $token = getOrSetPersistentToken(
                self::DEMO_COOKIE,
                self::DEMO_COOKIE_PATTERN,
                self::DEMO_COOKIE_BYTES,
                self::DEMO_COOKIE_DAYS
            );
            if (consultantDemoLimitReached(true, $this->demoUsed($token), CHAT_DEMO_MAX_REQUESTS)) {
                $this->respond(200, 'limit', sprintf(self::MESSAGES['limit'], CHAT_DEMO_MAX_REQUESTS));
            }
            $this->demoHit($token);
        }

        $history = consultantTrimHistory((array) ($_SESSION[self::SESSION_HISTORY_KEY] ?? []), CHAT_HISTORY_MESSAGES);

        $reply = consultantReply($question, $history);

        if ($reply['status'] !== 'ok') {
            $this->respond(200, 'fallback', '');
        }

        $history[] = ['role' => 'user', 'text' => $question];
        $history[] = ['role' => 'assistant', 'text' => $reply['text']];
        $_SESSION[self::SESSION_HISTORY_KEY] = consultantTrimHistory($history, CHAT_HISTORY_MESSAGES);

        $this->respond(200, 'ok', $reply['text'], $reply['cards']);
    }

    /**
     * @param list<array{title: string, price: string, note: string, url: string}> $cards
     */
    private function respond(int $httpStatus, string $status, string $text, array $cards = []): never
    {
        http_response_code($httpStatus);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => $status, 'text' => $text, 'cards' => $cards], JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function demoPath(string $token): string
    {
        $dir = ROOT_PATH . '/storage/cache/chat-demo';
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException("Не удалось создать директорию: {$dir}");
        }

        return $dir . '/' . $token . '.txt';
    }

    private function demoUsed(string $token): int
    {
        $path = $this->demoPath($token);

        return is_file($path) ? (int) file_get_contents($path) : 0;
    }

    private function demoHit(string $token): void
    {
        file_put_contents($this->demoPath($token), (string) ($this->demoUsed($token) + 1), LOCK_EX);
    }
}
