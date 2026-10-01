<?php

declare(strict_types=1);

namespace App\Controllers;

/**
 * Запись на услуги — /booking (phase-4.md, Таск 3; FR-SV-001–004).
 * Страница формы и два JSON-эндпоинта: Специалисты по выбранным Услугам и
 * свободные слоты Специалиста на дату. Слоты считает сервер
 * (bookingFreeSlots(), Core/Booking.php) по данным БД — из запроса берутся
 * только id Услуг, id Специалиста и дата, длительность и вид Услуг клиенту
 * не доверяются. Создание Записи (POST /booking) — Таск 4.
 */
final class BookingController
{
    private const MAX_SERVICES = 10;
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
        ]);
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
