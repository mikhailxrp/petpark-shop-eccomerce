<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use Throwable;

/**
 * Пакетный ИИ-разбор Характеристик — /admin/ai/attributes (phase-5.md, Таск 3;
 * FR-AI-001). Только Владелец. Порциями, пока страница открыта (без cron):
 * JS повторяет POST /admin/ai/attributes/run, пока очередь не опустеет.
 * Пишет только в product_attribute_drafts — каталог не меняется.
 */
final class AiAttributesController
{
    private const BATCH_SIZE = 5;
    private const QUEUE_PREVIEW_LIMIT = 20;

    private const STOP_UNAVAILABLE = 'unavailable';
    private const STOP_BLOCKED = 'blocked';

    private const STOP_MESSAGES = [
        self::STOP_UNAVAILABLE => 'ИИ-провайдер недоступен. Необработанные Товары остались в очереди — запустите разбор позже.',
        self::STOP_BLOCKED => 'Достигнут месячный лимит расхода на ИИ — разбор приостановлен.',
    ];

    public function index(): void
    {
        $role = $this->ownerRole();

        render('admin/ai-attributes-run', [
            'pageTitle'  => 'Разбор Характеристик — PetPark',
            'roleLabel'  => adminRoleLabel($role),
            'homeUrl'    => homePathForRole($role),
            'userRole'   => $role,
            'counts'     => $this->counts(),
            'queue'      => attributeDraftQueue(ATTRIBUTE_EXTRACT_NAMES, 0, self::QUEUE_PREVIEW_LIMIT),
            'runUrl'     => '/admin/ai/attributes/run',
            'allowed'    => aiTaskAllowed(AI_TASK_ATTRIBUTES, aiLimitPercent(aiCallsMonthSpent(), AI_MONTHLY_LIMIT_RUB)),
        ]);
    }

    /** Одна порция: JSON с прогрессом. Курсор after_id — чтобы Товар с ошибкой не брался снова. */
    public function run(): void
    {
        $this->ownerRole();
        requireCsrf();

        // Сессия больше не нужна, а вызовы ИИ долгие — не блокируем остальные запросы.
        session_write_close();

        $afterIdInput = input('after_id', '0');
        $afterId = is_string($afterIdInput) && ctype_digit($afterIdInput) ? (int) $afterIdInput : 0;

        $products = attributeDraftQueue(ATTRIBUTE_EXTRACT_NAMES, $afterId, self::BATCH_SIZE);
        $dictionary = attributeDictionary(ATTRIBUTE_EXTRACT_NAMES);

        $processed = 0;
        $failed = 0;
        $stop = '';
        $lastId = $afterId;

        foreach ($products as $product) {
            $lastId = (int) $product['id'];

            try {
                $missing = attributeMissingNames($lastId, ATTRIBUTE_EXTRACT_NAMES);
                $outcome = attributeExtractForProduct($product, $missing, $dictionary);

                if ($outcome['status'] === 'ok') {
                    attributeDraftsSave($lastId, $outcome['drafts']);
                    $processed++;
                    continue;
                }

                if (isset(self::STOP_MESSAGES[$outcome['status']])) {
                    $stop = $outcome['status'];
                    break;
                }

                $failed++;
            } catch (Throwable $e) {
                logError('Разбор Характеристик не удался', ['product_id' => $lastId, 'error' => $e->getMessage()]);
                $failed++;
            }
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'processed' => $processed,
            'failed'    => $failed,
            'last_id'   => $lastId,
            'done'      => $stop === '' && count($products) < self::BATCH_SIZE,
            'stop'      => $stop,
            'message'   => self::STOP_MESSAGES[$stop] ?? '',
            'counts'    => $this->counts(),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** Гость → логин, не Владелец → 404 (как /admin/ai). */
    private function ownerRole(): string
    {
        ensureSessionStarted();

        if (!isAuthenticated()) {
            redirect('/admin/login');
        }

        $role = (string) ($_SESSION['user_role'] ?? '');
        if ($role !== 'owner') {
            http_response_code(404);
            render('errors/404');
            exit;
        }

        return $role;
    }

    /** @return array{queue: int, processed: int, pending: int, needs_decision: int} */
    private function counts(): array
    {
        return ['queue' => attributeDraftQueueCount(ATTRIBUTE_EXTRACT_NAMES)] + attributeDraftCounts();
    }
}
