<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\BookingCancellation;
use App\Services\Payment\YooMoneyStubGateway;

/**
 * Календарь Записей и действия над ними (phase-4.md, Таск 7; FR-SV-010,
 * часть 1). `shift_admin`/`owner` видят всех Специалистов с фильтром,
 * Специалист — только свои Записи: чужая Запись по id в URL и прямой POST
 * дают 404. Статус меняется только через bookingTransition() /
 * BookingCancellation, все POST — CSRF + redirect(). Записи `slot_selected`
 * («Ждёт оплаты») только показываются — подтверждает их оплата (Таск 5).
 */
final class BookingController
{
    private const STAFF_ROLES = ['specialist', 'shift_admin', 'owner'];
    private const ADMIN_ROLES = ['shift_admin', 'owner'];
    private const ACTION_REJECTED_ERROR = 'Это действие для Записи сейчас недоступно.';
    private const REFUND_FAILED_ERROR = 'Запись отменена, но вернуть Депозит не удалось — проверьте платёж вручную.';
    private const WEEKDAYS = ['Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота', 'Воскресенье'];

    public function index(): void
    {
        requireRole(...self::ADMIN_ROLES);

        $filterInput = input('specialist', '');
        $specialists = bookingSpecialistsForFilter();
        $specialistId = is_string($filterInput) && ctype_digit($filterInput)
            && in_array((int) $filterInput, array_column($specialists, 'id'), true)
            ? (int) $filterInput
            : null;

        $this->renderWeek('/admin/bookings', $specialistId, $specialists);
    }

    /** Календарь Специалиста на `/specialist` — вызывается из DashboardController. */
    public function specialistWeek(int $specialistId): void
    {
        $this->renderWeek('/specialist', $specialistId, []);
    }

    public function show(string $id): void
    {
        requireRole(...self::STAFF_ROLES);

        $booking = $this->findAccessible($id);
        if ($booking === null) {
            $this->notFound();
            return;
        }

        $role = (string) $_SESSION['user_role'];

        render('admin/booking', [
            'pageTitle' => 'Запись №' . $booking['id'] . ' — PetPark',
            'roleLabel' => adminRoleLabel($role),
            'homeUrl'   => homePathForRole($role),
            'userRole'  => $role,
            'booking'   => $booking,
            'backUrl'   => $role === 'specialist' ? '/specialist' : '/admin/bookings',
            'canAct'    => $booking['status'] === 'confirmed',
            'success'   => getFlash('success'),
            'error'     => getFlash('error'),
        ]);
    }

    public function complete(string $id): void
    {
        $booking = $this->actionTarget($id);
        if ($booking === null) {
            return;
        }

        if (bookingTransition((int) $booking['id'], 'completed')) {
            setFlash('success', 'Запись отмечена как завершённая.');
        } else {
            setFlash('error', self::ACTION_REJECTED_ERROR);
        }

        redirect('/admin/bookings/' . (int) $booking['id']);
    }

    public function noShow(string $id): void
    {
        $booking = $this->actionTarget($id);
        if ($booking === null) {
            return;
        }

        $bookingId = (int) $booking['id'];

        if (!bookingTransition($bookingId, 'no_show')) {
            setFlash('error', self::ACTION_REJECTED_ERROR);
            redirect('/admin/bookings/' . $bookingId);
        }

        $forfeited = bookingMarkDepositForfeited($bookingId);
        setFlash('success', $forfeited ? 'Неявка отмечена, Депозит не возвращается.' : 'Неявка отмечена.');

        redirect('/admin/bookings/' . $bookingId);
    }

    public function cancel(string $id): void
    {
        $booking = $this->actionTarget($id);
        if ($booking === null) {
            return;
        }

        $bookingId = (int) $booking['id'];
        $cancellation = new BookingCancellation(new YooMoneyStubGateway(env('PAYMENT_STUB_SECRET')));

        // Порог 3 часа — только для Покупателя; отмена персоналом — форс-мажор.
        match ($cancellation->cancel($bookingId)) {
            BookingCancellation::RESULT_NOT_ALLOWED   => setFlash('error', self::ACTION_REJECTED_ERROR),
            BookingCancellation::RESULT_REFUND_FAILED => setFlash('error', self::REFUND_FAILED_ERROR),
            BookingCancellation::RESULT_REFUNDED      => setFlash('success', 'Запись отменена, Депозит возвращён.'),
            BookingCancellation::RESULT_CANCELLED     => setFlash('success', 'Запись отменена.'),
        };

        redirect('/admin/bookings/' . $bookingId);
    }

    /**
     * @param list<array{id: int, name: string}> $specialists список для фильтра; пустой — фильтра нет
     */
    private function renderWeek(string $baseUrl, ?int $specialistId, array $specialists): void
    {
        bookingReleaseExpired();

        $weekInput = input('week', '');
        $today = date('Y-m-d');

        try {
            $week = bookingWeekBounds(is_string($weekInput) && $weekInput !== '' ? $weekInput : $today);
        } catch (\InvalidArgumentException) {
            $week = bookingWeekBounds($today);
        }

        $monday = new \DateTimeImmutable($week['from']);
        $days = [];
        foreach (self::WEEKDAYS as $i => $weekday) {
            $date = $monday->modify("+{$i} days");
            $days[$date->format('Y-m-d')] = [
                'date'     => $date->format('d.m.Y'),
                'weekday'  => $weekday,
                'isToday'  => $date->format('Y-m-d') === $today,
                'bookings' => [],
            ];
        }

        foreach (bookingsForWeek($week['from'], $week['to'], $specialistId) as $booking) {
            $days[substr((string) $booking['scheduled_at'], 0, 10)]['bookings'][] = $booking;
        }

        $role = (string) $_SESSION['user_role'];
        $weekUrl = static fn (string $date): string => $baseUrl . '?' . http_build_query(
            array_filter(['week' => $date, 'specialist' => $specialistId])
        );

        render('admin/bookings', [
            'pageTitle'    => 'Записи — PetPark',
            'roleLabel'    => adminRoleLabel($role),
            'homeUrl'      => homePathForRole($role),
            'userRole'     => $role,
            'baseUrl'      => $baseUrl,
            'days'         => $days,
            'weekFrom'     => $week['from'],
            'weekLabel'    => $monday->format('d.m.Y') . ' — ' . $monday->modify('+6 days')->format('d.m.Y'),
            'prevWeekUrl'  => $weekUrl($monday->modify('-7 days')->format('Y-m-d')),
            'nextWeekUrl'  => $weekUrl($monday->modify('+7 days')->format('Y-m-d')),
            'todayUrl'     => $weekUrl($today),
            'specialists'  => $specialists,
            'specialistId' => $specialistId,
            'success'      => getFlash('success'),
            'error'        => getFlash('error'),
        ]);
    }

    /**
     * Запись, к которой у текущего пользователя есть доступ: Специалист — только
     * своя, `shift_admin`/`owner` — любая. Освобождённые слоты в календаре не
     * показываются — по прямой ссылке тоже 404.
     *
     * @return array<string, mixed>|null
     */
    private function findAccessible(string $id): ?array
    {
        $booking = ctype_digit($id) && (int) $id > 0 ? bookingFindForStaff((int) $id) : null;
        if ($booking === null || $booking['status'] === 'slot_released') {
            return null;
        }

        if ($_SESSION['user_role'] === 'specialist') {
            $ownId = bookingSpecialistIdByUser((int) $_SESSION['user_id']);
            if ($ownId === null || (int) $booking['specialist_id'] !== $ownId) {
                return null;
            }
        }

        return $booking;
    }

    /**
     * Общее начало POST-действий: роль, CSRF, доступ к Записи.
     * null — ответ 404 уже отправлен.
     *
     * @return array<string, mixed>|null
     */
    private function actionTarget(string $id): ?array
    {
        requireRole(...self::STAFF_ROLES);
        requireCsrf();

        $booking = $this->findAccessible($id);
        if ($booking === null) {
            $this->notFound();
        }

        return $booking;
    }

    private function notFound(): void
    {
        http_response_code(404);
        render('errors/404');
    }
}
