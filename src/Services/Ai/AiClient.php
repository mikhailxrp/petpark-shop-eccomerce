<?php

declare(strict_types=1);

namespace App\Services\Ai {

    /**
     * Единая точка вызова ИИ (BR-AI-001, NFR-AI §11.8): выбирает провайдера по
     * классу задачи, проверяет лимит ДО вызова, пишет каждый вызов в ai_calls,
     * при сбое возвращает статус «unavailable», а не исключение.
     */
    final class AiClient
    {
        public const STATUS_OK = 'ok';
        public const STATUS_UNAVAILABLE = 'unavailable';
        public const STATUS_BLOCKED = 'blocked';
        public const STATUS_ERROR = 'error';

        // Лениво чистим журнал раз в N вызовов (cron на shared-хостинге нет).
        private const PURGE_EVERY_N_CALLS = 50;

        /**
         * @param array<string, AiProvider> $providerByClass класс задачи => провайдер
         * @param string $pricePer1k цена ₽ за 1000 токенов
         * @param string $monthlyLimit лимит расхода ₽/мес
         */
        public function __construct(
            private readonly array $providerByClass,
            private readonly string $pricePer1k,
            private readonly string $monthlyLimit,
        ) {
        }

        /**
         * @return array{status: string, text: string, tokens: int, cost: string, provider: string}
         */
        public function complete(string $task, string $prompt, string $system = '', int $timeoutSeconds = 20): array
        {
            $class = aiTaskClass($task);
            $provider = $this->providerByClass[$class]
                ?? $this->providerByClass[AI_CLASS_PERSONAL]
                ?? new OfflineProvider();

            $percent = aiLimitPercent(aiCallsMonthSpent(), $this->monthlyLimit);
            if (!aiTaskAllowed($task, $percent)) {
                logWarning('AI: лимит расхода исчерпан, вызов не выполнен', [
                    'task' => $task,
                    'limit_percent' => $percent,
                ]);

                return $this->finish($task, $class, $provider->name(), $prompt, self::STATUS_BLOCKED, '', 0);
            }

            try {
                $result = $provider->complete($system, $prompt, $timeoutSeconds);
            } catch (\Throwable $e) {
                $result = ['ok' => false, 'text' => '', 'tokens' => 0, 'error' => $e::class];
            }

            if (!$result['ok']) {
                logWarning('AI: провайдер недоступен', [
                    'task' => $task,
                    'provider' => $provider->name(),
                    'reason' => $result['error'],
                ]);

                return $this->finish($task, $class, $provider->name(), $prompt, self::STATUS_UNAVAILABLE, '', 0);
            }

            return $this->finish(
                $task,
                $class,
                $provider->name(),
                $prompt,
                self::STATUS_OK,
                $result['text'],
                $result['tokens']
            );
        }

        /** @return array{status: string, text: string, tokens: int, cost: string, provider: string} */
        private function finish(
            string $task,
            string $class,
            string $provider,
            string $prompt,
            string $status,
            string $text,
            int $tokens
        ): array {
            $cost = $status === self::STATUS_OK ? aiCallCost($tokens, $this->pricePer1k) : '0.0000';

            // Сбой журнала не должен ронять основной сценарий.
            try {
                aiCallInsert($task, $class, $provider, $prompt, $tokens, $cost, $status);
                if (random_int(1, self::PURGE_EVERY_N_CALLS) === 1) {
                    aiCallsPurgeExpired();
                }
            } catch (\Throwable $e) {
                logError('AI: не удалось записать вызов в ai_calls', ['error' => $e->getMessage()]);
            }

            return [
                'status' => $status,
                'text' => $text,
                'tokens' => $tokens,
                'cost' => $cost,
                'provider' => $provider,
            ];
        }
    }
}

namespace {

    use App\Services\Ai\AiClient;
    use App\Services\Ai\AiProvider;
    use App\Services\Ai\OfflineProvider;
    use App\Services\Ai\YandexGptProvider;

    /**
     * Точка входа для контроллеров и сервисов помощников.
     *
     * @return array{status: string, text: string, tokens: int, cost: string, provider: string}
     */
    function aiComplete(string $task, string $prompt, string $system = '', int $timeoutSeconds = AI_TIMEOUT_SECONDS): array
    {
        static $client = null;
        $client ??= new AiClient(
            [
                AI_CLASS_ANONYMOUS => aiBuildProvider(AI_PROVIDER_ANONYMOUS),
                AI_CLASS_PERSONAL  => aiBuildProvider(AI_PROVIDER_PERSONAL),
            ],
            AI_PRICE_PER_1K_TOKENS,
            AI_MONTHLY_LIMIT_RUB
        );

        return $client->complete($task, $prompt, $system, $timeoutSeconds);
    }

    /** Имя провайдера из .env → объект. Неизвестное имя = offline (никуда не уходим). */
    function aiBuildProvider(string $name): AiProvider
    {
        return match ($name) {
            'yandexgpt' => new YandexGptProvider(
                env('AI_YANDEX_KEY', ''),
                env('AI_YANDEX_FOLDER_ID', ''),
                env('AI_YANDEX_MODEL', 'yandexgpt/latest')
            ),
            default => new OfflineProvider(),
        };
    }
}
