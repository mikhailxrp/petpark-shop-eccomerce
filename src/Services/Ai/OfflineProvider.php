<?php

declare(strict_types=1);

namespace App\Services\Ai;

/**
 * Провайдер «недоступен»: для тестов и проверки сценариев без ИИ (§11.8,
 * AI_PROVIDER_*=offline). Сеть не трогает.
 */
final class OfflineProvider implements AiProvider
{
    public function name(): string
    {
        return 'offline';
    }

    public function complete(string $system, string $prompt, int $timeoutSeconds): array
    {
        return ['ok' => false, 'text' => '', 'tokens' => 0, 'error' => 'provider offline'];
    }
}
