<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

/**
 * Закрытие слотов Специалиста — /admin/time-off (phase-4.md, Таск 9;
 * FR-SV-010). Специалист закрывает дни только себе (его id берётся из
 * профиля, поле формы игнорируется), `shift_admin`/`owner` — любому.
 * Чужой период по id — 404. Дни с подтверждённой Записью закрыть нельзя.
 * Все POST — CSRF + redirect().
 */
final class TimeOffController
{
    private const STAFF_ROLES = ['specialist', 'shift_admin', 'owner'];
    private const DATE_PATTERN = '/^\d{4}-\d{2}-\d{2}$/';
    private const LIST_URL = '/admin/time-off';
    private const VALIDATION_ERROR = 'Укажите Специалиста и даты в формате ГГГГ-ММ-ДД: начало не раньше сегодняшнего дня, конец не раньше начала. Причина — не длиннее 200 символов.';
    private const CONFLICT_ERROR = 'В эти дни есть подтверждённые Записи или ожидающие оплаты. Сначала отмените или перенесите их.';

    public function index(): void
    {
        requireRole(...self::STAFF_ROLES);

        $ownId = $this->ownSpecialistId();
        if ($ownId === 0) {
            $this->notFound();
            return;
        }

        $role = (string) $_SESSION['user_role'];

        render('admin/time-off', [
            'pageTitle'   => 'Закрытие слотов — PetPark',
            'roleLabel'   => adminRoleLabel($role),
            'homeUrl'     => homePathForRole($role),
            'userRole'    => $role,
            'periods'     => specialistTimeOffList($ownId),
            'specialists' => $ownId === null ? bookingSpecialistsForFilter() : [],
            'today'       => date('Y-m-d'),
            'backUrl'     => $role === 'specialist' ? '/specialist' : '/admin/bookings',
            'success'     => getFlash('success'),
            'error'       => getFlash('error'),
        ]);
    }

    public function store(): void
    {
        requireRole(...self::STAFF_ROLES);
        requireCsrf();

        $ownId = $this->ownSpecialistId();
        if ($ownId === 0) {
            $this->notFound();
            return;
        }

        $specialistId = $ownId ?? $this->positiveInt(input('specialist_id'));
        $dateFrom = (string) input('date_from', '');
        $dateTo = (string) input('date_to', '');
        $reason = trim(mb_scrub((string) input('reason', '')));

        $valid = $specialistId !== null
            && $this->isValidDate($dateFrom) && $this->isValidDate($dateTo)
            && $dateFrom >= date('Y-m-d') && $dateTo >= $dateFrom
            && mb_strlen($reason) <= SPECIALIST_TIME_OFF_REASON_MAX;
        if (!$valid) {
            $this->failRedirect(self::VALIDATION_ERROR);
        }

        match (specialistTimeOffCreate($specialistId, $dateFrom, $dateTo, $reason === '' ? null : $reason)) {
            'created'          => setFlash('success', 'Дни закрыты — Покупатели больше не видят там свободных слотов.'),
            'booking_conflict' => setFlash('error', self::CONFLICT_ERROR),
            'invalid'          => setFlash('error', self::VALIDATION_ERROR),
        };

        redirect(self::LIST_URL);
    }

    public function delete(string $id): void
    {
        requireRole(...self::STAFF_ROLES);
        requireCsrf();

        $ownId = $this->ownSpecialistId();
        $periodId = $this->positiveInt($id);
        if ($ownId === 0 || $periodId === null || !specialistTimeOffDelete($periodId, $ownId)) {
            $this->notFound();
            return;
        }

        setFlash('success', 'Дни снова открыты для записи.');
        redirect(self::LIST_URL);
    }

    /** specialists.id текущего Специалиста (0 — профиля нет); null — роль не ограничена одним Специалистом. */
    private function ownSpecialistId(): ?int
    {
        return $_SESSION['user_role'] === 'specialist'
            ? (bookingSpecialistIdByUser((int) $_SESSION['user_id']) ?? 0)
            : null;
    }

    private function failRedirect(string $message): never
    {
        setFlash('error', $message);
        redirect(self::LIST_URL);
    }

    private function positiveInt(mixed $value): ?int
    {
        return is_string($value) && ctype_digit($value) && (int) $value > 0 ? (int) $value : null;
    }

    private function isValidDate(string $date): bool
    {
        if (preg_match(self::DATE_PATTERN, $date) !== 1) {
            return false;
        }
        [$year, $month, $day] = array_map('intval', explode('-', $date));

        return checkdate($month, $day, $year);
    }

    private function notFound(): void
    {
        http_response_code(404);
        render('errors/404');
    }
}
