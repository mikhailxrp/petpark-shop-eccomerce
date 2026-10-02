<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

/**
 * Рассмотрение заявок на Возврат — /admin/returns, /admin/returns/{id}
 * (phase-6.md, Таск 6; FR-RET-002). Доступ — `shift_admin`/`owner`.
 * Статус меняется только через returnTransition(); все POST — CSRF +
 * redirect(). «Завершить» и возврат денег — Таск 8.
 */
final class ReturnController
{
    private const PER_PAGE = 20;
    private const COMMENT_MAX_LENGTH = 1000;
    private const TRANSITION_REJECTED_ERROR = 'Этот переход статуса сейчас недоступен.';
    private const ACTION_FAILED_ERROR = 'Не удалось выполнить действие. Попробуйте ещё раз.';
    private const COMMENT_INVALID_ERROR = 'Комментарий Покупателю обязателен (до 1000 символов).';
    private const MESSAGE_INVALID_ERROR = 'Текст сообщения обязателен (до 1000 символов).';
    private const MESSAGE_STATUS_ERROR = 'Написать Покупателю можно, пока заявка «На рассмотрении».';
    private const MESSAGE_NO_EMAIL_ERROR = 'У заявки нет email — сообщение не поставлено.';

    public function index(): void
    {
        requireRole('shift_admin', 'owner');

        $statusInput = input('status', '');
        $status = is_string($statusInput) && array_key_exists($statusInput, RETURN_STATUS_TRANSITIONS)
            ? $statusInput
            : null;

        $total = returnCountForAdmin($status);
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));

        $pageInput = input('page', '1');
        $page = is_string($pageInput) && ctype_digit($pageInput)
            ? min(max(1, (int) $pageInput), $totalPages)
            : 1;

        $role = (string) $_SESSION['user_role'];

        render('admin/returns', [
            'pageTitle'  => 'Возвраты — PetPark',
            'roleLabel'  => adminRoleLabel($role),
            'homeUrl'    => homePathForRole($role),
            'userRole'   => $role,
            'returns'    => returnListForAdmin($status, self::PER_PAGE, ($page - 1) * self::PER_PAGE),
            'status'     => $status,
            'page'       => $page,
            'totalPages' => $totalPages,
            'total'      => $total,
        ]);
    }

    public function show(string $id): void
    {
        requireRole('shift_admin', 'owner');

        $return = ctype_digit($id) ? returnFindForAdmin((int) $id) : null;

        if ($return === null) {
            $this->notFound();
            return;
        }

        $role = (string) $_SESSION['user_role'];
        $status = (string) $return['status'];

        render('admin/return', [
            'pageTitle'  => 'Возврат по заказу №' . (int) $return['order_id'] . ' — PetPark',
            'roleLabel'  => adminRoleLabel($role),
            'homeUrl'    => homePathForRole($role),
            'userRole'   => $role,
            'return'     => $return,
            'photos'     => returnPhotos((int) $return['id']),
            'canReview'  => returnCanTransition($status, 'in_review'),
            'canDecide'  => returnCanTransition($status, 'approved'),
            'canMessage' => $status === 'in_review',
            'commentMax' => self::COMMENT_MAX_LENGTH,
            'success'    => getFlash('success'),
            'error'      => getFlash('error'),
        ]);
    }

    public function takeInReview(string $id): void
    {
        requireRole('shift_admin', 'owner');
        requireCsrf();

        $returnId = $this->requireReturnId($id);

        $this->transition($returnId, 'in_review', null, 'Заявка взята в работу.');

        redirect('/admin/returns/' . $returnId);
    }

    public function approve(string $id): void
    {
        requireRole('shift_admin', 'owner');
        requireCsrf();

        $returnId = $this->requireReturnId($id);

        $this->decide($returnId, 'approved', 'Заявка одобрена, Покупатель уведомлён.');

        redirect('/admin/returns/' . $returnId);
    }

    public function reject(string $id): void
    {
        requireRole('shift_admin', 'owner');
        requireCsrf();

        $returnId = $this->requireReturnId($id);

        $this->decide($returnId, 'rejected', 'Заявка отклонена, Покупатель уведомлён.');

        redirect('/admin/returns/' . $returnId);
    }

    /** «Написать покупателю» (FR-RET-002 п. 3): письмо через очередь, статус не меняется. */
    public function messageBuyer(string $id): void
    {
        requireRole('shift_admin', 'owner');
        requireCsrf();

        $returnId = $this->requireReturnId($id);
        $return = returnFindForAdmin($returnId);
        $message = $this->readText('message');

        if ($return === null || $return['status'] !== 'in_review') {
            setFlash('error', self::MESSAGE_STATUS_ERROR);
        } elseif ($message === null) {
            setFlash('error', self::MESSAGE_INVALID_ERROR);
        } else {
            try {
                $queued = notifierEnqueueReturnMessage($return, $message);
                setFlash(
                    $queued ? 'success' : 'error',
                    $queued ? 'Сообщение поставлено в очередь на отправку.' : self::MESSAGE_NO_EMAIL_ERROR
                );
            } catch (\Throwable $e) {
                logException($e, ['return_id' => $returnId, 'action' => 'return_message']);
                setFlash('error', self::ACTION_FAILED_ERROR);
            }
        }

        redirect('/admin/returns/' . $returnId);
    }

    /** Одобрить/отклонить: комментарий обязателен, проверяется до обращения к модели. */
    private function decide(int $returnId, string $toStatus, string $successMessage): void
    {
        $comment = $this->readText('comment');

        if ($comment === null) {
            setFlash('error', self::COMMENT_INVALID_ERROR);
            return;
        }

        $this->transition($returnId, $toStatus, $comment, $successMessage);
    }

    private function transition(int $returnId, string $toStatus, ?string $comment, string $successMessage): void
    {
        try {
            $done = returnTransition($returnId, $toStatus, (int) $_SESSION['user_id'], $comment);
        } catch (\Throwable $e) {
            logException($e, ['return_id' => $returnId, 'target' => $toStatus]);
            setFlash('error', self::ACTION_FAILED_ERROR);
            return;
        }

        if ($done) {
            setFlash('success', $successMessage);
        } else {
            setFlash('error', self::TRANSITION_REJECTED_ERROR);
        }
    }

    /** Непустой текст из POST не длиннее лимита; null — ввод некорректен. */
    private function readText(string $key): ?string
    {
        $value = input($key, '');
        $text = is_string($value) ? trim($value) : '';

        return $text !== '' && mb_strlen($text) <= self::COMMENT_MAX_LENGTH ? $text : null;
    }

    /** id существующей заявки; иначе — ответ 404 и остановка. */
    private function requireReturnId(string $id): int
    {
        $returnId = ctype_digit($id) ? (int) $id : 0;

        if ($returnId === 0 || returnFindForAdmin($returnId) === null) {
            $this->notFound();
            exit;
        }

        return $returnId;
    }

    private function notFound(): void
    {
        http_response_code(404);
        render('errors/404');
    }
}
