<?php

declare(strict_types=1);

namespace App\Services\Ai;

/**
 * Контракт ИИ-провайдера (php.md, «Functions vs classes»): помощники знают
 * только AiClient, провайдер выбирается настройкой класса задачи в .env
 * (BR-AI-001 п. 3), а не кодом.
 */
interface AiProvider
{
    public function name(): string;

    /**
     * Один запрос к модели. Не бросает исключений — сбой сети, таймаут и
     * ошибка провайдера возвращаются как ok=false (недоступность ИИ не
     * должна ронять основной сценарий, §11.8).
     *
     * @return array{ok: bool, text: string, tokens: int, error: ?string}
     */
    public function complete(string $system, string $prompt, int $timeoutSeconds): array;
}
