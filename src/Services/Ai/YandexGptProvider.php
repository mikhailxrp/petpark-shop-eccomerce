<?php

declare(strict_types=1);

namespace App\Services\Ai;

/**
 * YandexGPT через REST (Foundation Models, синхронный completion).
 * Ключ, folder id и модель — из .env (AI_YANDEX_*), конструктор получает
 * их готовыми — клиент с одним и тем же конфигом на каждый вызов
 * (php.md: класс оправдан).
 */
final class YandexGptProvider implements AiProvider
{
    private const ENDPOINT = 'https://ai.api.cloud.yandex.net/foundationModels/v1/completion';
    private const TEMPERATURE = 0.2;
    private const MAX_TOKENS = 1000;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $folderId,
        private readonly string $model,
    ) {
    }

    public function name(): string
    {
        return 'yandexgpt';
    }

    public function complete(string $system, string $prompt, int $timeoutSeconds): array
    {
        $messages = [];
        if ($system !== '') {
            $messages[] = ['role' => 'system', 'text' => $system];
        }
        $messages[] = ['role' => 'user', 'text' => $prompt];

        $body = json_encode([
            'modelUri' => sprintf('gpt://%s/%s', $this->folderId, $this->model),
            'completionOptions' => [
                'stream' => false,
                'temperature' => self::TEMPERATURE,
                'maxTokens' => (string) self::MAX_TOKENS,
            ],
            'messages' => $messages,
        ], JSON_UNESCAPED_UNICODE);

        $curl = curl_init(self::ENDPOINT);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => $timeoutSeconds,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Api-Key ' . $this->apiKey,
                'x-folder-id: ' . $this->folderId,
            ],
        ]);
        $raw = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        if ($raw === false) {
            return $this->failure('curl: ' . $curlError);
        }
        if ($status !== 200) {
            return $this->failure('http ' . $status);
        }

        $data = json_decode((string) $raw, true);
        $text = $data['result']['alternatives'][0]['message']['text'] ?? null;
        if (!is_string($text)) {
            return $this->failure('unexpected response shape');
        }

        return [
            'ok' => true,
            'text' => $text,
            'tokens' => (int) ($data['result']['usage']['totalTokens'] ?? 0),
            'error' => null,
        ];
    }

    /** @return array{ok: bool, text: string, tokens: int, error: ?string} */
    private function failure(string $reason): array
    {
        return ['ok' => false, 'text' => '', 'tokens' => 0, 'error' => $reason];
    }
}
