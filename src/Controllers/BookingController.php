<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AmoCrm;

/**
 * Запись на услуги — /booking (phase-4.md, Таск 3; FR-SV-001–004).
 * Страница формы и два JSON-эндпоинта: Специалисты по выбранным Услугам и
 * свободные слоты Специалиста на дату. Слоты считает сервер
 * (bookingFreeSlots(), Core/Booking.php) по данным БД — из запроса берутся
 * только id Услуг, id Специалиста и дата, длительность и вид Услуг клиенту
 * не доверяются. POST /booking (Таск 4) создаёт Запись; формат антибота и
 * идемпотентности — как в CheckoutController, но без отдельного токена
 * двойного клика: повтор на тот же слот получает «слот занят».
 */
final class BookingController
{
    private const MAX_SERVICES = 10;
    private const MIN_FORM_FILL_SECONDS = 3;
    private const TIME_PATTERN = '/^([01]\d|2[0-3]):[0-5]\d$/';
    private const PET_NAME_MAX = 60;
    private const PET_BREED_MAX = 80;
    private const PET_WEIGHT_PATTERN = '/^\d{1,3}(\.\d{1,2})?$/';
    private const VALIDATION_ERROR = 'Проверьте форму: Услуги, Специалист, время, Питомец и контакты обязательны.';
    private const SLOT_TAKEN_ERROR = 'Это время уже занято или стало недоступно. Выберите другое время.';
    private const EMAIL_TAKEN_ERROR = 'Этот email уже зарегистрирован. Войдите в аккаунт, чтобы записаться.';
    private const RATE_LIMITED_ERROR = 'Слишком много попыток записи. Попробуйте через минуту.';
    private const BOT_NOTICE = 'Спасибо, заявка принята.';
    private const DATE_PATTERN = '/^\d{4}-\d{2}-\d{2}$/';
    private const SERVICES_INVALID_ERROR = 'Выберите Услуги из списка.';
    private const SPECIALIST_INVALID_ERROR = 'Выбранный Специалист не оказывает все эти Услуги.';
    private const DATE_INVALID_ERROR = 'Укажите дату в формате ГГГГ-ММ-ДД.';

    public function index(): void
    {
        $isCustomer = isAuthenticated() && ($_SESSION['user_role'] ?? null) === 'customer';
        $today = new \DateTimeImmutable('today');

        render('booking', [
            'services'    => servicesActive(),
            'isCustomer'  => $isCustomer,
            'pets'        => $isCustomer ? petsByUser((int) $_SESSION['user_id']) : [],
            'speciesList' => AccountController::PET_SPECIES,
            'dateMin'     => $today->format('Y-m-d'),
            'dateMax'     => $today->modify('+' . BOOKING_HORIZON_DAYS . ' days')->format('Y-m-d'),
            'formToken'   => generateFormToken('booking'),
            'notice'      => getFlash('booking_notice'),
            'error'       => getFlash('booking_error'),
        ]);
    }

    /** POST /booking — создание Записи (FR-SV-006/007/011, FR-AUTH-002). */
    public function store(): void
    {
        requireCsrf();

        if (tooManyAttempts('booking', 5, 60)) {
            logWarning('Запись: превышен лимит попыток');
            $this->failAndBack(self::RATE_LIMITED_ERROR);
        }
        hitRateLimit('booking');

        $honeypot = trim((string) input('website'));
        $formToken = input('form_token');
        $tokenValid = verifyFormToken(
            'booking',
            is_string($formToken) && $formToken !== '' ? $formToken : null,
            self::MIN_FORM_FILL_SECONDS
        );
        if ($honeypot !== '' || !$tokenValid) {
            // Бот видит тот же «принято», что и человек; Запись не создаётся (dod-global.md).
            logWarning('Запись: отклонено как бот', [
                'honeypot_filled' => $honeypot !== '',
                'token_valid'     => $tokenValid,
            ]);
            setFlash('booking_notice', self::BOT_NOTICE);
            redirect('/booking');
        }

        $services = $this->requestedServices();
        $date = (string) input('date');
        $time = (string) input('slot');
        $specialistId = $this->positiveInt(input('specialist_id'));

        $specialistOk = false;
        if ($services !== null && $specialistId !== null) {
            foreach (specialistsForServices(array_column($services, 'id')) as $row) {
                $specialistOk = $specialistOk || (int) $row['id'] === $specialistId;
            }
        }
        if ($services === null || !$specialistOk || !$this->isValidDate($date)
            || preg_match(self::TIME_PATTERN, $time) !== 1
        ) {
            $this->failAndBack(self::VALIDATION_ERROR);
        }

        $isCustomer = isAuthenticated() && ($_SESSION['user_role'] ?? null) === 'customer';
        $sessionUserId = $isCustomer ? (int) $_SESSION['user_id'] : null;

        $contact = ['name' => '', 'phone' => '', 'email' => ''];
        if (!$isCustomer) {
            $contact = [
                'name'  => trim(mb_scrub((string) input('contact_name'))),
                'phone' => trim(mb_scrub((string) input('contact_phone'))),
                'email' => trim((string) input('contact_email')),
            ];
            $contactValid = $contact['name'] !== '' && mb_strlen($contact['name']) <= 150
                && $contact['phone'] !== '' && mb_strlen($contact['phone']) <= 20
                && $contact['email'] !== '' && mb_strlen($contact['email']) <= 255
                && filter_var($contact['email'], FILTER_VALIDATE_EMAIL) !== false;
            if (!$contactValid) {
                $this->failAndBack(self::VALIDATION_ERROR);
            }
        }

        $petIdRaw = (string) input('pet_id');
        $petId = null;
        $newPet = null;
        if ($petIdRaw === 'new' || !$isCustomer) {
            $newPet = $this->validNewPet();
            if ($newPet === null) {
                $this->failAndBack(self::VALIDATION_ERROR);
            }
        } else {
            $petId = $this->positiveInt($petIdRaw);
            if ($petId === null) {
                $this->failAndBack(self::VALIDATION_ERROR);
            }
        }

        $result = bookingCreate(
            $sessionUserId,
            $contact,
            $specialistId,
            $date,
            $time,
            $services,
            $petId,
            $newPet
        );

        match ($result['status']) {
            'slot_taken' => $this->failAndBack(self::SLOT_TAKEN_ERROR),
            'invalid'    => $this->failAndBack(self::VALIDATION_ERROR),
            'email_taken' => $this->failAndBack(self::EMAIL_TAKEN_ERROR),
            'created'   => $this->finishCreated($result),
        };
    }

    /** GET /booking/success/{id} */
    public function success(string $id): void
    {
        $bookingId = (int) $id;
        bookingReleaseExpired();
        $booking = $bookingId > 0 ? bookingFindById($bookingId) : null;

        if ($booking === null || !$this->canView($booking)) {
            http_response_code(404);
            render('errors/404');
            return;
        }

        render('booking-success', ['booking' => $booking]);
    }

    /** GET /booking/specialists?services[]=1&services[]=2 */
    public function specialists(): void
    {
        // Эндпоинт только читает: снимаем блокировку файла сессии (как searchVariants).
        session_write_close();

        $services = $this->requestedServices();
        if ($services === null) {
            $this->json(['error' => self::SERVICES_INVALID_ERROR], 400);
        }

        $specialists = [];
        foreach (specialistsForServices(array_column($services, 'id')) as $row) {
            $specialists[] = ['id' => (int) $row['id'], 'name' => (string) $row['name']];
        }

        $this->json(['specialists' => $specialists]);
    }

    /** GET /booking/slots?specialist_id=1&date=2026-10-05&services[]=1 */
    public function slots(): void
    {
        session_write_close();

        $services = $this->requestedServices();
        if ($services === null) {
            $this->json(['error' => self::SERVICES_INVALID_ERROR], 400);
        }

        $date = (string) input('date');
        if (!$this->isValidDate($date)) {
            $this->json(['error' => self::DATE_INVALID_ERROR], 400);
        }

        $specialistId = $this->positiveInt(input('specialist_id'));
        $specialist = null;
        foreach (specialistsForServices(array_column($services, 'id')) as $row) {
            if ($specialistId !== null && (int) $row['id'] === $specialistId) {
                $specialist = $row;
                break;
            }
        }
        if ($specialist === null) {
            $this->json(['error' => self::SPECIALIST_INVALID_ERROR], 400);
        }

        $blockMinutes = bookingBlockMinutes(
            array_sum(array_map(static fn (array $s): int => (int) $s['duration_minutes'], $services)),
            array_column($services, 'kind')
        );

        // Истёкшее удержание не должно прятать слот (FR-SV-009, без cron).
        bookingReleaseExpired($specialistId);

        $slots = bookingFreeSlots(
            $specialist,
            $date,
            $blockMinutes,
            bookingsBusyForDay($specialistId, $date),
            specialistTimeOffFrom($specialistId, $date),
            new \DateTimeImmutable('now')
        );

        $this->json(['slots' => $slots]);
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
     * Новый Питомец из полей `pet_*` формы; правила те же, что у карточки в
     * кабинете (AccountController::validatePet). null — поля некорректны.
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

    /**
     * @param array{status: 'created', booking_id: int, booking_status: string, new_account: array{name: string, email: string, password: string}|null} $result
     */
    private function finishCreated(array $result): never
    {
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

        // Без Депозита Запись подтверждена сразу — передаём в AmoCRM (FR-SV-011);
        // с Депозитом это сделает оплата (Таск 5).
        if ($result['booking_status'] === 'confirmed') {
            try {
                (new AmoCrm())->registerBooking($bookingId);
            } catch (\Throwable $e) {
                logException($e, ['booking_id' => $bookingId]);
            }
        }

        clearRateLimit('booking');
        ensureSessionStarted();
        $_SESSION['booking_ids'][] = $bookingId;

        redirect('/booking/success/' . $bookingId);
    }

    /** Запись видна сессии, создавшей её, и её владельцу-Покупателю. */
    private function canView(array $booking): bool
    {
        ensureSessionStarted();

        $inSession = in_array((int) $booking['id'], $_SESSION['booking_ids'] ?? [], true);
        $isOwner = isAuthenticated()
            && ($_SESSION['user_role'] ?? null) === 'customer'
            && (int) $booking['user_id'] === (int) $_SESSION['user_id'];

        return $inSession || $isOwner;
    }

    private function failAndBack(string $message): never
    {
        setFlash('booking_error', $message);
        redirect('/booking');
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
}
