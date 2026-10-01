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
    private const DRAFTS_PER_PAGE = 10;
    private const DRAFTS_URL = '/admin/ai/attributes/drafts';

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
            'draftsUrl'  => self::DRAFTS_URL,
            'allowed'    => aiTaskAllowed(AI_TASK_ATTRIBUTES, aiLimitPercent(aiCallsMonthSpent(), AI_MONTHLY_LIMIT_RUB)),
        ]);
    }

    /** Черновики Характеристик на решение Владельца (FR-AI-001), по Товарам, с пагинацией. */
    public function drafts(): void
    {
        $role = $this->ownerRole();

        $total = attributeDraftOpenProductCount();
        $totalPages = max(1, (int) ceil($total / self::DRAFTS_PER_PAGE));
        $page = $this->pageFromInput($totalPages);

        $products = attributeDraftOpenProducts(self::DRAFTS_PER_PAGE, ($page - 1) * self::DRAFTS_PER_PAGE);
        $draftsByProduct = [];
        foreach (attributeDraftsOpenForProducts(array_map(static fn (array $p): int => (int) $p['id'], $products)) as $draft) {
            $draftsByProduct[(int) $draft['product_id']][] = $draft;
        }

        render('admin/ai-attributes', [
            'pageTitle'       => 'Черновики Характеристик — PetPark',
            'roleLabel'       => adminRoleLabel($role),
            'homeUrl'         => homePathForRole($role),
            'userRole'        => $role,
            'products'        => $products,
            'draftsByProduct' => $draftsByProduct,
            'dictionary'      => attributeDictionary(ATTRIBUTE_EXTRACT_NAMES),
            'page'            => $page,
            'totalPages'      => $totalPages,
            'total'           => $total,
            'decideUrl'       => self::DRAFTS_URL . '/decide',
            'draftsUrl'       => self::DRAFTS_URL,
            'maxLength'       => ATTRIBUTE_VALUE_MAX_LENGTH,
            'success'         => getFlash('success'),
            'error'           => getFlash('error'),
            'errorDraftId'    => (int) (getFlash('error_draft_id') ?? 0),
        ]);
    }

    /** Решение по одному черновику: confirm / edit / reject. Всегда redirect() обратно в список. */
    public function decide(): void
    {
        $this->ownerRole();
        requireCsrf();

        $draftIdInput = input('draft_id', '');
        $draftId = is_string($draftIdInput) && ctype_digit($draftIdInput) ? (int) $draftIdInput : 0;
        $pageInput = input('page', '1');
        $backUrl = is_string($pageInput) && ctype_digit($pageInput) && (int) $pageInput > 1
            ? self::DRAFTS_URL . '?page=' . (int) $pageInput
            : self::DRAFTS_URL;

        $actionInput = input('action', '');
        $action = is_string($actionInput) ? $actionInput : '';
        $valueInput = input('value', '');

        // confirm берёт значение самого черновика — читаем его из БД, не из формы.
        $draftValue = $action === ATTRIBUTE_ACTION_CONFIRM ? attributeDraftOpenValue($draftId) : null;
        if ($action === ATTRIBUTE_ACTION_CONFIRM && $draftValue === null) {
            setFlash('error', 'Черновик уже обработан или не найден.');
            redirect($backUrl);
        }

        $decision = attributeDecisionResolve($action, $draftValue, is_string($valueInput) ? $valueInput : '');

        if ($draftId === 0 || $decision['error'] !== null) {
            setFlash('error', $decision['error'] ?? 'Черновик не найден.');
            setFlash('error_draft_id', (string) $draftId);
            redirect($backUrl);
        }

        try {
            $applied = attributeDraftDecide($draftId, $decision['value'], (string) $decision['outcome']);
        } catch (Throwable $e) {
            logError('Решение по черновику Характеристики не записано', ['draft_id' => $draftId, 'error' => $e->getMessage()]);
            setFlash('error', 'Не удалось сохранить решение. Попробуйте ещё раз.');
            redirect($backUrl);
        }

        if ($applied) {
            setFlash('success', match ($decision['outcome']) {
                'rejected' => 'Черновик отклонён.',
                default    => 'Характеристика сохранена — Товар найдётся в фильтре каталога.',
            });
        } else {
            setFlash('error', 'Черновик уже обработан.');
        }

        redirect($backUrl);
    }

    private function pageFromInput(int $totalPages): int
    {
        $pageInput = input('page', '1');

        return is_string($pageInput) && ctype_digit($pageInput)
            ? min(max(1, (int) $pageInput), $totalPages)
            : 1;
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
