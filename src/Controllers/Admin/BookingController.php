<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AccountController;
use App\Services\AmoCrm;
use App\Services\BookingCancellation;
use App\Services\Payment\YooMoneyStubGateway;

/**
 * Календарь Записей и действия над ними (phase-4.md, Таски 7–8; FR-SV-010).
 * `shift_admin`/`owner` видят всех Специалистов с фильтром, Специалист —
 * только свои Записи: чужая Запись по id в URL и прямой POST дают 404.
 * Статус меняется только через bookingTransition() / BookingCancellation,
 * все POST — CSRF + redirect(). Записи `slot_selected` («Ждёт оплаты»)
 * только показываются — подтверждает их оплата (Таск 5).
 * Ручная Запись по звонку и перенос (Таск 8): Специалист работает только в
 * своём календаре — его id берётся из профиля, а не из формы. Слоты, как и
 * на публичной форме, считает сервер; длительность и вид Услуг клиенту не
 * доверяются.
 */
final class BookingController
{
    private const STAFF_ROLES = ['specialist', 'shift_admin', 'owner'];
    private const ADMIN_ROLES = ['shift_admin', 'owner'];
    private const MAX_SERVICES = 10;
    private const TIME_PATTERN = '/^([01]\d|2[0-3]):[0-5]\d$/';
    private const DATE_PATTERN = '/^\d{4}-\d{2}-\d{2}$/';
    private const PET_NAME_MAX = 60;
    private const PET_BREED_MAX = 80;
    private const PET_WEIGHT_PATTERN = '/^\d{1,3}(\.\d{1,2})?$/';
    private const CREATE_VALIDATION_ERROR = 'Проверьте форму: контакты, Питомец, Услуги, Специалист и время обязательны.';
    private const RESCHEDULE_VALIDATION_ERROR = 'Выберите Специалиста, дату и время из предложенных.';
    private const SLOT_TAKEN_ERROR = 'Это время уже занято или стало недоступно. Выберите другое время.';
    private const SERVICES_INVALID_ERROR = 'Выберите Услуги из списка.';
    private const SPECIALIST_INVALID_ERROR = 'Этот Специалист не оказывает все выбранные Услуги.';
    private const DATE_INVALID_ERROR = 'Укажите дату в формате ГГГГ-ММ-ДД.';
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
        // «Мои клиенты» (FR-MGR-003) — только клиенты из календаря этого Специалиста.
        $this->renderWeek('/specialist', $specialistId, [], clientRecent($specialistId, 5));
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

    /** GET /admin/bookings/new — форма Записи по звонку (FR-SV-010). */
    public function create(): void
    {
        requireRole(...self::STAFF_ROLES);

        $role = (string) $_SESSION['user_role'];
        $today = new \DateTimeImmutable('today');
        $oldJson = getFlash('booking_manual_old');
        $old = $oldJson !== null ? json_decode($oldJson, true) : null;

        render('admin/booking-create', [
            'pageTitle'   => 'Новая запись — PetPark',
            'roleLabel'   => adminRoleLabel($role),
            'homeUrl'     => homePathForRole($role),
            'userRole'    => $role,
            'services'    => servicesActive(),
            'speciesList' => AccountController::PET_SPECIES,
            'dateMin'     => $today->format('Y-m-d'),
            'dateMax'     => $today->modify('+' . BOOKING_HORIZON_DAYS . ' days')->format('Y-m-d'),
            'backUrl'     => $role === 'specialist' ? '/specialist' : '/admin/bookings',
            'form'        => is_array($old) ? $old : [],
            'error'       => getFlash('error'),
        ]);
    }

    /** POST /admin/bookings — ручная Запись: валидация здесь, транзакция — bookingCreateManual(). */
    public function store(): void
    {
        requireRole(...self::STAFF_ROLES);
        requireCsrf();

        $form = [
            'contact_name'  => trim(mb_scrub((string) input('contact_name'))),
            'contact_phone' => trim(mb_scrub((string) input('contact_phone'))),
            'contact_email' => trim((string) input('contact_email')),
            'services'      => array_values(array_filter((array) input('services', []), 'is_string')),
            'specialist_id' => (string) input('specialist_id'),
            'date'          => (string) input('date'),
            'slot'          => (string) input('slot'),
            'pet_id'        => (string) input('pet_id'),
            'pet_name'      => (string) input('pet_name'),
            'pet_species'   => (string) input('pet_species'),
            'pet_breed'     => (string) input('pet_breed'),
            'pet_weight'    => (string) input('pet_weight'),
        ];

        $services = $this->requestedServices();
        $specialistId = $this->ownOrRequestedSpecialistId();
        $contactValid = $form['contact_name'] !== '' && mb_strlen($form['contact_name']) <= 150
            && $form['contact_phone'] !== '' && mb_strlen($form['contact_phone']) <= 20
            && $form['contact_email'] !== '' && mb_strlen($form['contact_email']) <= 255
            && filter_var($form['contact_email'], FILTER_VALIDATE_EMAIL) !== false;

        $specialistOk = false;
        if ($services !== null && $specialistId !== null) {
            foreach (specialistsForServices(array_column($services, 'id')) as $row) {
                $specialistOk = $specialistOk || (int) $row['id'] === $specialistId;
            }
        }

        $petId = null;
        $newPet = null;
        if ($form['pet_id'] === 'new' || $form['pet_id'] === '') {
            $newPet = $this->validNewPet();
        } else {
            $petId = $this->positiveInt($form['pet_id']);
        }
        $petValid = $newPet !== null || $petId !== null;

        if ($services === null || !$specialistOk || !$contactValid || !$petValid
            || !$this->isValidDate($form['date']) || preg_match(self::TIME_PATTERN, $form['slot']) !== 1
        ) {
            $this->failCreate($form, self::CREATE_VALIDATION_ERROR);
        }

        $result = bookingCreateManual(
            (int) $_SESSION['user_id'],
            ['name' => $form['contact_name'], 'phone' => $form['contact_phone'], 'email' => $form['contact_email']],
            $specialistId,
            $form['date'],
            $form['slot'],
            $services,
            $petId,
            $newPet
        );

        match ($result['status']) {
            'slot_taken' => $this->failCreate($form, self::SLOT_TAKEN_ERROR),
            'invalid'    => $this->failCreate($form, self::CREATE_VALIDATION_ERROR),
            'created'    => null,
        };

        $bookingId = $result['booking_id'];

        if ($result['new_account'] !== null) {
            try {
                sendNewCustomerAccountEmail(
                    $result['new_account']['email'],
                    $result['new_account']['name'],
                    $result['new_account']['password']
                );
            } catch (\Throwable $e) {
                // Запись уже создана — ошибку SMTP не показываем (php.md).
                logException($e, ['booking_id' => $bookingId]);
            }
        }

        $this->registerInAmoCrm($bookingId);

        setFlash('success', 'Запись создана и подтверждена.');
        redirect('/admin/bookings/' . $bookingId);
    }

    /** GET /admin/bookings/specialists?services[]=1 — Специалисты, оказывающие все Услуги (для Специалиста — только он сам). */
    public function specialists(): void
    {
        requireRole(...self::STAFF_ROLES);
        $ownId = $this->ownSpecialistId();
        session_write_close();

        $services = $this->requestedServices();
        if ($services === null) {
            $this->json(['error' => self::SERVICES_INVALID_ERROR], 400);
        }

        $specialists = [];
        foreach (specialistsForServices(array_column($services, 'id')) as $row) {
            if ($ownId === null || (int) $row['id'] === $ownId) {
                $specialists[] = ['id' => (int) $row['id'], 'name' => (string) $row['name']];
            }
        }

        $this->json(['specialists' => $specialists]);
    }

    /**
     * GET /admin/bookings/slots?specialist_id=1&date=2026-10-05 с `services[]`
     * (новая Запись) либо `booking_id` (перенос: Услуги берутся из Записи, её
     * собственный слот не считается занятым).
     */
    public function slots(): void
    {
        requireRole(...self::STAFF_ROLES);

        $date = (string) input('date');
        if (!$this->isValidDate($date)) {
            $this->json(['error' => self::DATE_INVALID_ERROR], 400);
        }

        $excludeBookingId = null;
        if (input('booking_id') !== '') {
            $booking = $this->findAccessible((string) input('booking_id'));
            if ($booking === null || $booking['status'] !== 'confirmed') {
                $this->json(['error' => self::ACTION_REJECTED_ERROR], 400);
            }
            $excludeBookingId = (int) $booking['id'];
            $rows = bookingServiceRows($excludeBookingId);
            $serviceIds = array_column($rows, 'service_id');
            $durations = array_map(static fn (array $row): int => (int) $row['duration_minutes'], $rows);
            $kinds = array_column($rows, 'kind');
        } else {
            $services = $this->requestedServices();
            if ($services === null) {
                $this->json(['error' => self::SERVICES_INVALID_ERROR], 400);
            }
            $serviceIds = array_column($services, 'id');
            $durations = array_map(static fn (array $row): int => (int) $row['duration_minutes'], $services);
            $kinds = array_column($services, 'kind');
        }

        $specialistId = $this->ownOrRequestedSpecialistId();
        session_write_close();

        $specialist = null;
        foreach (specialistsForServices(array_map('intval', $serviceIds)) as $row) {
            if ($specialistId !== null && (int) $row['id'] === $specialistId) {
                $specialist = $row;
                break;
            }
        }
        if ($specialist === null) {
            $this->json(['error' => self::SPECIALIST_INVALID_ERROR], 400);
        }

        // Истёкшее удержание не должно прятать слот (FR-SV-009, без cron).
        bookingReleaseExpired($specialistId);

        $slots = bookingFreeSlots(
            $specialist,
            $date,
            bookingBlockMinutes(array_sum($durations), $kinds),
            bookingsBusyForDay($specialistId, $date, $excludeBookingId),
            specialistTimeOffFrom($specialistId, $date),
            new \DateTimeImmutable('now')
        );

        $this->json(['slots' => $slots]);
    }

    /** GET /admin/bookings/pets?email=… — Питомцы Покупателя с таким email (пусто, если такого нет). */
    public function pets(): void
    {
        requireRole(...self::STAFF_ROLES);
        session_write_close();

        $email = trim((string) input('email'));
        $customer = filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? userFindByEmail($email) : null;

        $pets = [];
        if ($customer !== null && $customer['role'] === 'customer') {
            foreach (petsByUser((int) $customer['id']) as $pet) {
                $pets[] = ['id' => (int) $pet['id'], 'name' => (string) $pet['name'], 'species' => (string) $pet['species']];
            }
        }

        $this->json(['pets' => $pets]);
    }

    /** GET /admin/bookings/{id}/reschedule — форма переноса подтверждённой Записи. */
    public function rescheduleForm(string $id): void
    {
        requireRole(...self::STAFF_ROLES);

        $booking = $this->findAccessible($id);
        if ($booking === null) {
            $this->notFound();
            return;
        }

        $bookingId = (int) $booking['id'];
        if ($booking['status'] !== 'confirmed') {
            setFlash('error', self::ACTION_REJECTED_ERROR);
            redirect('/admin/bookings/' . $bookingId);
        }

        $ownId = $this->ownSpecialistId();
        $serviceIds = array_map('intval', array_column(bookingServiceRows($bookingId), 'service_id'));
        $specialists = [];
        foreach (specialistsForServices($serviceIds) as $row) {
            if ($ownId === null || (int) $row['id'] === $ownId) {
                $specialists[] = ['id' => (int) $row['id'], 'name' => (string) $row['name']];
            }
        }

        $role = (string) $_SESSION['user_role'];
        $today = new \DateTimeImmutable('today');

        render('admin/booking-reschedule', [
            'pageTitle'   => 'Перенос записи №' . $bookingId . ' — PetPark',
            'roleLabel'   => adminRoleLabel($role),
            'homeUrl'     => homePathForRole($role),
            'userRole'    => $role,
            'booking'     => $booking,
            'specialists' => $specialists,
            'dateMin'     => $today->format('Y-m-d'),
            'dateMax'     => $today->modify('+' . BOOKING_HORIZON_DAYS . ' days')->format('Y-m-d'),
            'error'       => getFlash('error'),
        ]);
    }

    /** POST /admin/bookings/{id}/reschedule — отмена + новая Запись одной транзакцией (bookingReschedule()). */
    public function reschedule(string $id): void
    {
        $booking = $this->actionTarget($id);
        if ($booking === null) {
            return;
        }

        $bookingId = (int) $booking['id'];
        $formUrl = '/admin/bookings/' . $bookingId . '/reschedule';
        $specialistId = $this->ownOrRequestedSpecialistId();
        $date = (string) input('date');
        $time = (string) input('slot');

        if ($specialistId === null || !$this->isValidDate($date) || preg_match(self::TIME_PATTERN, $time) !== 1) {
            setFlash('error', self::RESCHEDULE_VALIDATION_ERROR);
            redirect($formUrl);
        }

        $result = bookingReschedule($bookingId, $specialistId, $date, $time, (int) $_SESSION['user_id']);

        match ($result['status']) {
            'slot_taken'  => $this->failRedirect(self::SLOT_TAKEN_ERROR, $formUrl),
            'invalid'     => $this->failRedirect(self::RESCHEDULE_VALIDATION_ERROR, $formUrl),
            'not_allowed' => $this->failRedirect(self::ACTION_REJECTED_ERROR, '/admin/bookings/' . $bookingId),
            'rescheduled' => null,
        };

        $this->registerInAmoCrm($result['booking_id']);

        setFlash('success', 'Запись перенесена. Предыдущая запись №' . $bookingId . ' отменена.');
        redirect('/admin/bookings/' . $result['booking_id']);
    }

    /**
     * @param list<array{id: int, name: string}> $specialists список для фильтра; пустой — фильтра нет
     * @param list<array<string, mixed>>|null $recentClients блок «Мои клиенты»; null — не показывать
     */
    private function renderWeek(string $baseUrl, ?int $specialistId, array $specialists, ?array $recentClients = null): void
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
            ...($recentClients !== null ? ['recentClients' => $recentClients] : []),
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

    /** specialists.id текущего Специалиста (0 — профиля нет); null — роль не ограничена одним Специалистом. */
    private function ownSpecialistId(): ?int
    {
        return $_SESSION['user_role'] === 'specialist'
            ? (bookingSpecialistIdByUser((int) $_SESSION['user_id']) ?? 0)
            : null;
    }

    /** Специалист записи: у роли `specialist` — он сам (поле формы игнорируется), у остальных — из запроса. */
    private function ownOrRequestedSpecialistId(): ?int
    {
        $ownId = $this->ownSpecialistId();
        if ($ownId !== null) {
            return $ownId > 0 ? $ownId : null;
        }

        return $this->positiveInt(input('specialist_id'));
    }

    /**
     * Услуги из `services[]` запроса: уникальные положительные id, все
     * существуют и активны. null — параметр некорректен.
     *
     * @return array<int, array<string, mixed>>|null
     */
    private function requestedServices(): ?array
    {
        $raw = input('services');
        if (!is_array($raw) || $raw === [] || count($raw) > self::MAX_SERVICES) {
            return null;
        }

        $ids = [];
        foreach ($raw as $value) {
            $id = $this->positiveInt($value);
            if ($id === null) {
                return null;
            }
            $ids[$id] = $id;
        }

        $services = servicesFindActiveByIds(array_values($ids));

        return count($services) === count($ids) ? $services : null;
    }

    /**
     * Новый Питомец из полей `pet_*`; правила те же, что у публичной формы
     * Записи и карточки в кабинете. null — поля некорректны.
     *
     * @return array{name: string, species: string, breed: ?string, weight: ?string}|null
     */
    private function validNewPet(): ?array
    {
        $name = trim(mb_scrub((string) input('pet_name')));
        $species = mb_scrub((string) input('pet_species'));
        $breed = trim(mb_scrub((string) input('pet_breed')));
        $weight = str_replace(',', '.', trim(mb_scrub((string) input('pet_weight'))));

        $valid = $name !== '' && mb_strlen($name) <= self::PET_NAME_MAX
            && in_array($species, AccountController::PET_SPECIES, true)
            && mb_strlen($breed) <= self::PET_BREED_MAX
            && ($weight === '' || (preg_match(self::PET_WEIGHT_PATTERN, $weight) === 1 && (float) $weight > 0));

        return $valid ? [
            'name'    => $name,
            'species' => $species,
            'breed'   => $breed === '' ? null : $breed,
            'weight'  => $weight === '' ? null : $weight,
        ] : null;
    }

    /** Запись в AmoCRM (FR-SV-011); сбой не должен ломать уже созданную Запись. */
    private function registerInAmoCrm(int $bookingId): void
    {
        try {
            (new AmoCrm())->registerBooking($bookingId);
        } catch (\Throwable $e) {
            logException($e, ['booking_id' => $bookingId]);
        }
    }

    /** @param array<string, mixed> $form введённое — вернётся в форму */
    private function failCreate(array $form, string $message): never
    {
        setFlash('booking_manual_old', (string) json_encode($form, JSON_UNESCAPED_UNICODE));
        $this->failRedirect($message, '/admin/bookings/new');
    }

    private function failRedirect(string $message, string $url): never
    {
        setFlash('error', $message);
        redirect($url);
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

    /** @param array<string, mixed> $data */
    private function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function notFound(): void
    {
        http_response_code(404);
        render('errors/404');
    }
}
