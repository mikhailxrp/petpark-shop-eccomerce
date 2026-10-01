<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use Throwable;

/**
 * ИИ-генератор описаний — /admin/ai/descriptions (phase-5.md, Таск 5; FR-AI-002).
 * Только Владелец. Генерация пишет лишь products.description_draft — витрина не
 * меняется; description обновляет только publish(). Пакет по Категории идёт
 * порциями, пока страница открыта (ai-batch.js, без cron).
 */
final class AiDescriptionsController
{
    private const BATCH_SIZE = 3;
    private const PRODUCTS_PER_PAGE = 10;
    private const PAGE_URL = '/admin/ai/descriptions';
    private const FILTER_DRAFTS = 'drafts';
    private const EXCERPT_LENGTH = 400;

    private const STOP_UNAVAILABLE = 'unavailable';
    private const STOP_BLOCKED = 'blocked';

    private const STOP_MESSAGES = [
        self::STOP_UNAVAILABLE => 'ИИ-провайдер недоступен. Необработанные Товары остались в очереди — запустите генерацию позже.',
        self::STOP_BLOCKED => 'Достигнут месячный лимит расхода на ИИ — генерация приостановлена.',
    ];

    public function index(): void
    {
        $role = $this->ownerRole();

        $categories = categoryAll();
        $categoryId = $this->categoryIdFromInput($categories);
        $categoryIds = $this->categoryScope($categories, $categoryId);
        $draftsOnly = $this->draftsOnlyFromInput();

        $total = productDescriptionListCount($categoryIds, $draftsOnly);
        $totalPages = max(1, (int) ceil($total / self::PRODUCTS_PER_PAGE));
        $page = $this->pageFromInput($totalPages);

        render('admin/ai-descriptions', [
            'pageTitle'  => 'Генерация описаний — PetPark',
            'roleLabel'  => adminRoleLabel($role),
            'homeUrl'    => homePathForRole($role),
            'userRole'   => $role,
            'categories' => $categories,
            'categoryId' => $categoryId,
            'draftsOnly' => $draftsOnly,
            'products'   => productDescriptionList($categoryIds, $draftsOnly, self::PRODUCTS_PER_PAGE, ($page - 1) * self::PRODUCTS_PER_PAGE),
            'page'       => $page,
            'totalPages' => $totalPages,
            'total'      => $total,
            'counts'     => $this->counts($categoryIds),
            'allowed'    => aiTaskAllowed(AI_TASK_DESCRIPTION, aiLimitPercent(aiCallsMonthSpent(), AI_MONTHLY_LIMIT_RUB)),
            'pageUrl'    => self::PAGE_URL,
            'runUrl'     => self::PAGE_URL . '/run',
            'generateUrl' => self::PAGE_URL . '/generate',
            'publishUrl' => self::PAGE_URL . '/publish',
            'discardUrl' => self::PAGE_URL . '/discard',
            'maxLength'  => DESCRIPTION_MAX_LENGTH,
            'excerptLength' => self::EXCERPT_LENGTH,
            'success'    => getFlash('success'),
            'error'      => getFlash('error'),
            'errorProductId' => (int) (getFlash('error_product_id') ?? 0),
        ]);
    }

    /** Черновик для одного Товара. Описание на витрине не меняется. */
    public function generate(): void
    {
        $this->ownerRole();
        requireCsrf();

        $backUrl = $this->backUrl();
        $productId = $this->idFromInput('product_id');
        $product = $productId > 0 ? productDescriptionSource($productId) : null;

        if ($product === null) {
            setFlash('error', 'Товар не найден.');
            redirect($backUrl);
        }

        try {
            $outcome = descriptionGenerateForProduct($product, productConfirmedAttributes($productId));

            if ($outcome['status'] === 'ok') {
                productDescriptionDraftSave($productId, $outcome['text']);
                setFlash('success', 'Черновик описания готов — проверьте его и опубликуйте.');
            } else {
                setFlash('error', self::STOP_MESSAGES[$outcome['status']] ?? 'Не удалось получить описание от ИИ. Попробуйте ещё раз.');
            }
        } catch (Throwable $e) {
            logError('Генерация описания не удалась', ['product_id' => $productId, 'error' => $e->getMessage()]);
            setFlash('error', 'Не удалось сгенерировать описание. Попробуйте ещё раз.');
        }

        redirect($backUrl);
    }

    /** Одна порция пакета по Категории: JSON с прогрессом (ai-batch.js). */
    public function run(): void
    {
        $this->ownerRole();
        requireCsrf();

        // Сессия больше не нужна, а вызовы ИИ долгие — не блокируем остальные запросы.
        session_write_close();

        $categories = categoryAll();
        $categoryIds = $this->categoryScope($categories, $this->categoryIdFromInput($categories));
        $afterId = $this->idFromInput('after_id');

        $products = productDescriptionQueue($categoryIds ?? [], $afterId, self::BATCH_SIZE);

        $processed = 0;
        $failed = 0;
        $stop = '';
        $lastId = $afterId;

        foreach ($products as $product) {
            $lastId = (int) $product['id'];

            try {
                $source = productDescriptionSource($lastId);
                if ($source === null) {
                    $failed++;
                    continue;
                }

                $outcome = descriptionGenerateForProduct($source, productConfirmedAttributes($lastId));

                if ($outcome['status'] === 'ok') {
                    productDescriptionDraftSave($lastId, $outcome['text']);
                    $processed++;
                    continue;
                }

                if (isset(self::STOP_MESSAGES[$outcome['status']])) {
                    $stop = $outcome['status'];
                    break;
                }

                $failed++;
            } catch (Throwable $e) {
                logError('Пакетная генерация описания не удалась', ['product_id' => $lastId, 'error' => $e->getMessage()]);
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
            'counts'    => $this->counts($categoryIds),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** Публикация черновика (с правкой или без) в карточку Товара. */
    public function publish(): void
    {
        $this->ownerRole();
        requireCsrf();

        $backUrl = $this->backUrl();
        $productId = $this->idFromInput('product_id');
        $textInput = input('text', '');
        $text = descriptionNormalize(is_string($textInput) ? $textInput : '');

        $error = match (true) {
            $productId === 0 => 'Товар не найден.',
            $text === '' => 'Описание не может быть пустым.',
            !mb_check_encoding($text, 'UTF-8') => 'Текст в неверной кодировке — вставьте его заново.',
            mb_strlen($text) > DESCRIPTION_MAX_LENGTH => 'Описание длиннее ' . DESCRIPTION_MAX_LENGTH . ' символов.',
            default => null,
        };

        if ($error !== null) {
            setFlash('error', $error);
            setFlash('error_product_id', (string) $productId);
            redirect($backUrl);
        }

        try {
            $outcome = productDescriptionPublish($productId, $text);
        } catch (Throwable $e) {
            logError('Описание не опубликовано', ['product_id' => $productId, 'error' => $e->getMessage()]);
            setFlash('error', 'Не удалось опубликовать описание. Попробуйте ещё раз.');
            redirect($backUrl);
        }

        if ($outcome === null) {
            setFlash('error', 'Черновик уже обработан или не найден.');
        } else {
            setFlash('success', 'Описание опубликовано в карточке Товара.');
        }

        redirect($backUrl);
    }

    /** Отклонение черновика — описание на витрине остаётся прежним. */
    public function discard(): void
    {
        $this->ownerRole();
        requireCsrf();

        $backUrl = $this->backUrl();
        $productId = $this->idFromInput('product_id');

        try {
            $discarded = $productId > 0 && productDescriptionDiscard($productId);
        } catch (Throwable $e) {
            logError('Черновик описания не отклонён', ['product_id' => $productId, 'error' => $e->getMessage()]);
            setFlash('error', 'Не удалось отклонить черновик. Попробуйте ещё раз.');
            redirect($backUrl);
        }

        if ($discarded) {
            setFlash('success', 'Черновик отклонён.');
        } else {
            setFlash('error', 'Черновик уже обработан или не найден.');
        }

        redirect($backUrl);
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

    /** Выбранная Категория; неизвестная или не заданная = 0 (все Категории). */
    private function categoryIdFromInput(array $categories): int
    {
        $id = $this->idFromInput('category_id');
        $known = array_map(static fn (array $category): int => (int) $category['id'], $categories);

        return in_array($id, $known, true) ? $id : 0;
    }

    /** @return list<int>|null Категория с потомками; null — все Категории */
    private function categoryScope(array $categories, int $categoryId): ?array
    {
        return $categoryId > 0 ? catalogDescendantCategoryIds($categories, $categoryId) : null;
    }

    private function draftsOnlyFromInput(): bool
    {
        return input('filter', '') === self::FILTER_DRAFTS;
    }

    private function idFromInput(string $key): int
    {
        $value = input($key, '0');

        return is_string($value) && ctype_digit($value) ? (int) $value : 0;
    }

    private function pageFromInput(int $totalPages): int
    {
        $page = $this->idFromInput('page');

        return min(max(1, $page), $totalPages);
    }

    /** Возврат на тот же срез списка (Категория, фильтр, страница) после POST. */
    private function backUrl(): string
    {
        $page = $this->idFromInput('page');
        $query = array_filter([
            'category_id' => $this->idFromInput('category_id'),
            'filter'      => $this->draftsOnlyFromInput() ? self::FILTER_DRAFTS : '',
            'page'        => $page > 1 ? $page : 0,
        ]);

        return self::PAGE_URL . ($query === [] ? '' : '?' . http_build_query($query));
    }

    /**
     * Ключи совпадают с ai-batch.js: queue — очередь выбранной Категории,
     * processed — Товаров с черновиком.
     *
     * @param list<int>|null $categoryIds
     * @return array{queue: int, processed: int}
     */
    private function counts(?array $categoryIds): array
    {
        return [
            'queue'     => $categoryIds === null ? 0 : productDescriptionQueueCount($categoryIds),
            'processed' => productDescriptionDraftCount(),
        ];
    }
}
