<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use RuntimeException;
use Throwable;

/**
 * ИИ-помощники в админке — /admin/ai (phase-5.md, Таск 2; NFR-AI §11.8).
 * Расход за месяц и доля лимита, состояние помощников, журнал вызовов,
 * доля поправленных черновиков. Доступ — только `owner` (финансовые данные):
 * остальным 404, а не редирект, как у requireRole().
 */
final class AiController
{
    private const PER_PAGE = 20;
    private const NOTIFY_KIND = 'limit_80';

    private const TASK_LABELS = [
        AI_TASK_ATTRIBUTES  => 'Разбор Характеристик (FR-AI-001)',
        AI_TASK_DESCRIPTION => 'Генератор описаний (FR-AI-002)',
        AI_TASK_CONSULTANT  => 'Консультант в чате (FR-AI-003)',
        AI_TASK_ORDER_DRAFT => 'Разбор Обращения (FR-AI-004)',
    ];

    private const CLASS_LABELS = [
        AI_CLASS_ANONYMOUS => 'без ПДн',
        AI_CLASS_PERSONAL  => 'с ПДн',
    ];

    private const STATUS_LABELS = [
        'ok'          => ['Успешно', 'success'],
        'unavailable' => ['Недоступен', 'warning'],
        'blocked'     => ['Заблокирован лимитом', 'secondary'],
        'error'       => ['Ошибка', 'danger'],
    ];

    public function index(): void
    {
        ensureSessionStarted();

        if (!isAuthenticated()) {
            redirect('/admin/login');
        }

        $role = (string) ($_SESSION['user_role'] ?? '');
        if ($role !== 'owner') {
            http_response_code(404);
            render('errors/404');
            return;
        }

        $this->purgeJournal();

        $spent = aiCallsMonthSpent();
        $percent = aiLimitPercent($spent, AI_MONTHLY_LIMIT_RUB);
        $notify = aiShouldNotify($percent);
        if ($notify) {
            $this->notifyOwnerOnce($percent, $spent);
        }

        $total = aiCallsCount();
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        $pageInput = input('page', '1');
        $page = is_string($pageInput) && ctype_digit($pageInput)
            ? min(max(1, (int) $pageInput), $totalPages)
            : 1;

        render('admin/ai', [
            'pageTitle'  => 'ИИ-помощники — PetPark',
            'roleLabel'  => adminRoleLabel($role),
            'homeUrl'    => homePathForRole($role),
            'userRole'   => $role,
            'spent'      => $spent,
            'limit'      => AI_MONTHLY_LIMIT_RUB,
            'percent'    => $percent,
            'barValue'   => min($percent, 100),
            'notify'     => $notify,
            'assistants' => $this->assistants($percent),
            'calls'      => $this->journalRows($page),
            'outcomes'   => $this->outcomeSummary(),
            'page'       => $page,
            'totalPages' => $totalPages,
            'total'      => $total,
        ]);
    }

    /** Ленивая очистка по срокам хранения; сбой не ломает страницу. */
    private function purgeJournal(): void
    {
        try {
            aiCallsPurgeExpired();
        } catch (Throwable $e) {
            logError('Очистка журнала ИИ не удалась', ['error' => $e->getMessage()]);
        }
    }

    /** Одно письмо за месяц: письмо шлёт тот запрос, что занял отметку. */
    private function notifyOwnerOnce(int $percent, string $spent): void
    {
        try {
            if (!aiNotificationClaim(self::NOTIFY_KIND)) {
                return;
            }

            try {
                $owner = aiOwnerContact();
                if ($owner === null) {
                    throw new RuntimeException('В users нет пользователя с ролью owner');
                }
                sendAiLimitNotifyEmail($owner['email'], $owner['name'], $percent, $spent, AI_MONTHLY_LIMIT_RUB);
            } catch (Throwable $e) {
                aiNotificationRelease(self::NOTIFY_KIND);
                throw $e;
            }
        } catch (Throwable $e) {
            logError('Письмо о лимите ИИ не отправлено', ['error' => $e->getMessage()]);
        }
    }

    /** @return array<int, array{name: string, class: string, active: bool}> */
    private function assistants(int $percent): array
    {
        $rows = [];
        foreach (self::TASK_LABELS as $task => $label) {
            $rows[] = [
                'name'   => $label,
                'class'  => self::CLASS_LABELS[aiTaskClass($task)],
                'active' => aiTaskAllowed($task, $percent),
            ];
        }

        return $rows;
    }

    /** @return array<int, array<string, mixed>> */
    private function journalRows(int $page): array
    {
        $rows = [];
        foreach (aiCallsList(self::PER_PAGE, ($page - 1) * self::PER_PAGE) as $call) {
            $task = (string) $call['task'];
            $status = (string) $call['status'];
            [$statusLabel, $statusVariant] = self::STATUS_LABELS[$status] ?? [$status, 'secondary'];
            $rows[] = [
                'date'          => date('d.m.Y H:i', (int) strtotime((string) $call['created_at'])),
                'task'          => self::TASK_LABELS[$task] ?? $task,
                'class'         => self::CLASS_LABELS[(string) $call['task_class']] ?? (string) $call['task_class'],
                'provider'      => (string) $call['provider'],
                'tokens'        => (int) $call['tokens'],
                'cost'          => (string) $call['cost'],
                'statusLabel'   => $statusLabel,
                'statusVariant' => $statusVariant,
            ];
        }

        return $rows;
    }

    /** @return array{total: int, edited: int, editedPercent: int|null} */
    private function outcomeSummary(): array
    {
        $counts = aiDraftOutcomeCounts();
        $total = array_sum($counts);

        return [
            'total'         => $total,
            'edited'        => $counts['edited'],
            'editedPercent' => $total > 0 ? intdiv($counts['edited'] * 100, $total) : null,
        ];
    }
}
