<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\BookingCancellation;
use App\Services\OrderCancellation;
use App\Services\Payment\YooMoneyStubGateway;

/**
 * Личный кабинет Покупателя. `index` — заглушка-приёмник после входа
 * (phase-1.md, Таск 6; содержимое — Фаза 7). Питомцы — `FR-ACC-003`
 * (phase-4.md, Таск 2). «Мои записи» и отмена Записи — `FR-SV-008` (Таск 6).
 */
final class AccountController
{
    public const PET_SPECIES = ['Кошка', 'Собака', 'Птица', 'Другое'];
    private const PET_NAME_MAX = 60;
    private const PET_BREED_MAX = 80;
    private const PET_WEIGHT_PATTERN = '/^\d{1,3}(\.\d{1,2})?$/';
    private const PET_FORM_FLASH = 'pet_form';
    private const ORDERS_PER_PAGE = 10;
    private const RETURN_REASON_MAX = 1000;
    private const RETURN_REVIEW_HOURS = 24;
    private const RETURN_RATE_LIMIT_ATTEMPTS = 5;
    private const RETURN_RATE_LIMIT_SECONDS = 60;
    private const RETURN_FORM_FLASH = 'return_form';
    private const RETURN_UPLOAD_PREFIX = 'uploads/returns';
    private const CANCEL_TOO_LATE_ERROR = 'Отменить запись можно не позже чем за %d ч до визита. Обратитесь к администратору.';
    private const CANCEL_NOT_ALLOWED_ERROR = 'Эту запись уже нельзя отменить.';
    private const CANCEL_REFUND_FAILED_ERROR = 'Запись отменена, но вернуть депозит автоматически не удалось. Администратор свяжется с вами.';

    private const ORDER_CANCEL_NOT_ALLOWED_ERROR = 'Этот заказ уже нельзя отменить. Обратитесь к администратору.';
    private const ORDER_CANCEL_REFUND_FAILED_ERROR = 'Заказ отменён, но вернуть деньги автоматически не удалось. Администратор свяжется с вами.';

    public function index(): void
    {
        requireRole('customer');

        render('account/dashboard', []);
    }

    public function pets(): void
    {
        requireRole('customer');

        $this->renderPets(null);
    }

    public function petEdit(string $id): void
    {
        requireRole('customer');

        $pet = $this->findOwnPet($id);
        if ($pet === null) {
            $this->notFound();
            return;
        }

        $this->renderPets($pet);
    }

    public function petStore(): void
    {
        requireRole('customer');
        requireCsrf();

        [$values, $errors] = $this->validatePet();

        if ($errors !== []) {
            $this->rememberForm($values, $errors);
            redirect('/account/pets');
        }

        petCreate(
            (int) $_SESSION['user_id'],
            $values['name'],
            $values['species'],
            $values['breed'] === '' ? null : $values['breed'],
            $values['weight'] === '' ? null : $values['weight'],
        );

        setFlash('success', 'Питомец добавлен.');
        redirect('/account/pets');
    }

    public function petUpdate(string $id): void
    {
        requireRole('customer');
        requireCsrf();

        $pet = $this->findOwnPet($id);
        if ($pet === null) {
            $this->notFound();
            return;
        }

        $petId = (int) $pet['id'];
        [$values, $errors] = $this->validatePet();

        if ($errors !== []) {
            $this->rememberForm($values, $errors);
            redirect("/account/pets/{$petId}/edit");
        }

        petUpdate(
            (int) $_SESSION['user_id'],
            $petId,
            $values['name'],
            $values['species'],
            $values['breed'] === '' ? null : $values['breed'],
            $values['weight'] === '' ? null : $values['weight'],
        );

        setFlash('success', 'Данные питомца сохранены.');
        redirect('/account/pets');
    }

    public function petDelete(string $id): void
    {
        requireRole('customer');
        requireCsrf();

        $pet = $this->findOwnPet($id);
        if ($pet === null) {
            $this->notFound();
            return;
        }

        if (petDeleteIfNoBookings((int) $_SESSION['user_id'], (int) $pet['id'])) {
            setFlash('success', 'Питомец удалён.');
        } else {
            setFlash('error', 'Нельзя удалить питомца, у которого есть записи на услуги.');
        }

        redirect('/account/pets');
    }

    public function bookings(): void
    {
        requireRole('customer');

        $now = new \DateTimeImmutable('now');
        $bookings = array_map(
            static fn (array $booking): array => $booking + [
                'can_cancel' => bookingCanCancelByCustomer(
                    new \DateTimeImmutable((string) $booking['scheduled_at']),
                    $now,
                    BOOKING_CANCEL_THRESHOLD_HOURS
                ),
            ],
            bookingsUpcomingByUser((int) $_SESSION['user_id'])
        );

        render('account/bookings', [
            'bookings'       => $bookings,
            'thresholdHours' => BOOKING_CANCEL_THRESHOLD_HOURS,
            'success'        => getFlash('success'),
            'error'          => getFlash('error'),
        ]);
    }

    public function bookingCancel(string $id): void
    {
        requireRole('customer');
        requireCsrf();

        $booking = ctype_digit($id) && (int) $id > 0 ? bookingFindById((int) $id) : null;
        if ($booking === null || (int) $booking['user_id'] !== (int) $_SESSION['user_id']) {
            $this->notFound();
            return;
        }

        $tooLate = !bookingCanCancelByCustomer(
            new \DateTimeImmutable((string) $booking['scheduled_at']),
            new \DateTimeImmutable('now'),
            BOOKING_CANCEL_THRESHOLD_HOURS
        );

        if ($booking['status'] === 'confirmed' && $tooLate) {
            setFlash('error', sprintf(self::CANCEL_TOO_LATE_ERROR, BOOKING_CANCEL_THRESHOLD_HOURS));
            redirect('/account/bookings');
        }

        $cancellation = new BookingCancellation(new YooMoneyStubGateway(env('PAYMENT_STUB_SECRET')));

        match ($cancellation->cancel((int) $booking['id'])) {
            BookingCancellation::RESULT_NOT_ALLOWED   => setFlash('error', self::CANCEL_NOT_ALLOWED_ERROR),
            BookingCancellation::RESULT_REFUND_FAILED => setFlash('error', self::CANCEL_REFUND_FAILED_ERROR),
            BookingCancellation::RESULT_REFUNDED      => setFlash('success', 'Запись отменена, депозит возвращён.'),
            BookingCancellation::RESULT_CANCELLED     => setFlash('success', 'Запись отменена.'),
        };

        redirect('/account/bookings');
    }

    public function orders(): void
    {
        requireRole('customer');

        $userId = (int) $_SESSION['user_id'];
        $total = orderCountByUser($userId);
        $totalPages = max(1, (int) ceil($total / self::ORDERS_PER_PAGE));
        $page = min(catalogNormalizePage($_GET['page'] ?? null), $totalPages);

        render('account/orders', [
            'orders'     => ordersPageByUser($userId, self::ORDERS_PER_PAGE, ($page - 1) * self::ORDERS_PER_PAGE),
            'page'       => $page,
            'totalPages' => $totalPages,
        ]);
    }

    public function order(string $id): void
    {
        requireRole('customer');

        $order = $this->findOwnOrder($id);
        if ($order === null) {
            $this->notFound();
            return;
        }

        render('account/order', [
            'order'     => $order,
            'items'     => orderItemsForOrder((int) $order['id']),
            'canReturn' => $this->orderIsReturnable($order),
            'hasReturn' => returnFindByOrderId((int) $order['id']) !== null,
            'canCancel' => orderCanBeCancelledByCustomer((string) $order['status']),
            'success'   => getFlash('success'),
            'error'     => getFlash('error'),
        ]);
    }

    public function orderCancel(string $id): void
    {
        requireRole('customer');
        requireCsrf();

        $order = $this->findOwnOrder($id);
        if ($order === null) {
            $this->notFound();
            return;
        }

        $cancellation = new OrderCancellation(new YooMoneyStubGateway(env('PAYMENT_STUB_SECRET')));

        match ($cancellation->cancel((int) $order['id'], ORDER_CUSTOMER_CANCELLABLE_STATUSES)) {
            OrderCancellation::RESULT_NOT_ALLOWED   => setFlash('error', self::ORDER_CANCEL_NOT_ALLOWED_ERROR),
            OrderCancellation::RESULT_REFUND_FAILED => setFlash('error', self::ORDER_CANCEL_REFUND_FAILED_ERROR),
            OrderCancellation::RESULT_REFUNDED      => setFlash('success', 'Заказ отменён, деньги возвращены.'),
            OrderCancellation::RESULT_CANCELLED     => setFlash('success', 'Заказ отменён.'),
        };

        redirect('/account/orders/' . (int) $order['id']);
    }

    public function returns(): void
    {
        requireRole('customer');

        $orders = array_values(array_filter(
            ordersByUserWithReturnFlag((int) $_SESSION['user_id']),
            static fn (array $order): bool => returnOrderCanBeReturned((string) $order['status'], (bool) $order['has_return'])
        ));

        render('account/returns', [
            'orders'  => $orders,
            'returns' => returnsByUser((int) $_SESSION['user_id']),
            'success' => getFlash('success'),
            'error'   => getFlash('error'),
        ]);
    }

    public function returnForm(string $orderId): void
    {
        requireRole('customer');

        $order = $this->findOwnOrder($orderId);
        if ($order === null) {
            $this->notFound();
            return;
        }

        if (!$this->orderIsReturnable($order)) {
            $this->rejectReturn();
        }

        $form = $this->takeReturnForm();

        render('account/return-form', [
            'order'     => $order,
            'items'     => orderItemsForOrder((int) $order['id']),
            'reason'    => (string) ($form['values']['reason'] ?? ''),
            'errors'    => $form['errors'] ?? [],
            'reasonMax' => self::RETURN_REASON_MAX,
            'photosMin' => RETURN_PHOTOS_MIN,
            'photosMax' => RETURN_PHOTOS_MAX,
            'photoMb'   => intdiv(RETURN_PHOTO_MAX_BYTES, 1024 * 1024),
            'error'     => getFlash('error'),
        ]);
    }

    public function returnStore(string $orderId): void
    {
        requireRole('customer');
        requireCsrf();

        $order = $this->findOwnOrder($orderId);
        if ($order === null) {
            $this->notFound();
            return;
        }

        $formUrl = '/account/returns/' . (int) $order['id'] . '/new';

        if (tooManyAttempts('return', self::RETURN_RATE_LIMIT_ATTEMPTS, self::RETURN_RATE_LIMIT_SECONDS)) {
            setFlash('error', 'Слишком много попыток. Повторите через минуту.');
            redirect($formUrl);
        }
        hitRateLimit('return');

        if (!$this->orderIsReturnable($order)) {
            $this->rejectReturn();
        }

        $reason = trim(mb_scrub((string) input('reason')));
        $errors = [];

        if ($reason === '') {
            $errors['reason'] = 'Опишите причину возврата.';
        } elseif (mb_strlen($reason) > self::RETURN_REASON_MAX) {
            $errors['reason'] = 'Причина — не длиннее ' . self::RETURN_REASON_MAX . ' символов.';
        }

        // Причина не прошла — файлы на диск даже не пишем.
        if ($errors !== []) {
            $this->rememberReturnForm($reason, $errors);
            redirect($formUrl);
        }

        $upload = fileUploadSaveImages(
            fileUploadNormalize(is_array($_FILES['photos'] ?? null) ? $_FILES['photos'] : []),
            $this->returnUploadDir(),
            self::RETURN_UPLOAD_PREFIX
        );

        if (!$upload['ok']) {
            $this->rememberReturnForm($reason, ['photos' => $upload['error']]);
            redirect($formUrl);
        }

        try {
            $result = returnCreate((int) $order['id'], (int) $_SESSION['user_id'], $reason, $upload['paths']);
        } catch (\Throwable $e) {
            fileUploadDelete($upload['paths'], $this->returnUploadDir(), self::RETURN_UPLOAD_PREFIX);
            logError('Не удалось создать заявку на Возврат', ['order_id' => (int) $order['id'], 'error' => $e->getMessage()]);
            setFlash('error', 'Не удалось отправить заявку. Попробуйте позже.');
            redirect($formUrl);
        }

        if ($result['status'] !== 'created') {
            fileUploadDelete($upload['paths'], $this->returnUploadDir(), self::RETURN_UPLOAD_PREFIX);
            $this->rejectReturn();
        }

        setFlash('success', 'Заявка принята. Рассмотрим в течение ' . self::RETURN_REVIEW_HOURS . ' часов.');
        redirect('/account/returns');
    }

    /**
     * @param array<string, mixed>|null $editPet null — режим добавления
     */
    private function renderPets(?array $editPet): void
    {
        $form = $this->takeForm();

        if ($form !== null) {
            $values = $form['values'];
            $errors = $form['errors'];
        } else {
            $values = [
                'name'    => (string) ($editPet['name'] ?? ''),
                'species' => (string) ($editPet['species'] ?? ''),
                'breed'   => (string) ($editPet['breed'] ?? ''),
                'weight'  => $editPet !== null && $editPet['weight'] !== null
                    ? rtrim(rtrim((string) $editPet['weight'], '0'), '.')
                    : '',
            ];
            $errors = [];
        }

        // Истёкшее удержание не должно висеть в карточке «Ждёт оплаты» (FR-SV-009, без cron).
        bookingReleaseExpired();

        render('account/pets', [
            'pets'       => petsByUser((int) $_SESSION['user_id']),
            'pendingBookings' => bookingsPendingByPet((int) $_SESSION['user_id']),
            'editPet'    => $editPet,
            'values'     => $values,
            'errors'     => $errors,
            'speciesList' => self::PET_SPECIES,
            'success'    => getFlash('success'),
            'error'      => getFlash('error'),
        ]);
    }

    /**
     * @return array<string, mixed>|null null — id некорректен, Питомца нет или он чужой
     */
    private function findOwnPet(string $id): ?array
    {
        if (!ctype_digit($id) || (int) $id < 1) {
            return null;
        }

        return petFind((int) $_SESSION['user_id'], (int) $id);
    }

    /**
     * @return array<string, mixed>|null null — id некорректен, Заказа нет или он чужой
     */
    private function findOwnOrder(string $id): ?array
    {
        if (!ctype_digit($id) || (int) $id < 1) {
            return null;
        }

        $order = orderFindById((int) $id);

        return $order !== null && (int) $order['user_id'] === (int) $_SESSION['user_id'] ? $order : null;
    }

    /**
     * @param array<string, mixed> $order
     */
    private function orderIsReturnable(array $order): bool
    {
        return returnOrderCanBeReturned((string) $order['status'], returnFindByOrderId((int) $order['id']) !== null);
    }

    private function rejectReturn(): never
    {
        setFlash('error', 'Для этого заказа нельзя подать заявку на возврат.');
        redirect('/account/returns');
    }

    private function returnUploadDir(): string
    {
        return ROOT_PATH . '/public/' . self::RETURN_UPLOAD_PREFIX;
    }

    /**
     * @param array<string, string> $errors
     */
    private function rememberReturnForm(string $reason, array $errors): void
    {
        setFlash(self::RETURN_FORM_FLASH, json_encode([
            'values' => ['reason' => $reason],
            'errors' => $errors,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{values: array<string, string>, errors: array<string, string>}|null
     */
    private function takeReturnForm(): ?array
    {
        $raw = getFlash(self::RETURN_FORM_FLASH);
        if ($raw === null) {
            return null;
        }

        $form = json_decode($raw, true);

        return is_array($form) && is_array($form['values'] ?? null) && is_array($form['errors'] ?? null)
            ? $form
            : null;
    }

    private function notFound(): void
    {
        http_response_code(404);
        render('errors/404');
    }

    /**
     * @return array{0: array<string, string>, 1: array<string, string>} [значения, ошибки по полям]
     */
    private function validatePet(): array
    {
        // mb_scrub — битые UTF-8 байты заменяются, иначе json_encode флеша и INSERT падают
        $values = [
            'name'    => trim(mb_scrub((string) input('name'))),
            'species' => mb_scrub((string) input('species')),
            'breed'   => trim(mb_scrub((string) input('breed'))),
            'weight'  => str_replace(',', '.', trim(mb_scrub((string) input('weight')))),
        ];
        $errors = [];

        if ($values['name'] === '') {
            $errors['name'] = 'Укажите кличку.';
        } elseif (mb_strlen($values['name']) > self::PET_NAME_MAX) {
            $errors['name'] = 'Кличка — не длиннее ' . self::PET_NAME_MAX . ' символов.';
        }

        if (!in_array($values['species'], self::PET_SPECIES, true)) {
            $errors['species'] = 'Выберите вид животного.';
        }

        if (mb_strlen($values['breed']) > self::PET_BREED_MAX) {
            $errors['breed'] = 'Порода — не длиннее ' . self::PET_BREED_MAX . ' символов.';
        }

        if ($values['weight'] !== ''
            && (preg_match(self::PET_WEIGHT_PATTERN, $values['weight']) !== 1 || (float) $values['weight'] <= 0)
        ) {
            $errors['weight'] = 'Вес — число от 0.01 до 999.99 кг.';
        }

        return [$values, $errors];
    }

    /**
     * Ошибки и введённое переживают redirect через сессию (POST → redirect).
     *
     * @param array<string, string> $values
     * @param array<string, string> $errors
     */
    private function rememberForm(array $values, array $errors): void
    {
        setFlash(self::PET_FORM_FLASH, json_encode(['values' => $values, 'errors' => $errors], JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{values: array<string, string>, errors: array<string, string>}|null
     */
    private function takeForm(): ?array
    {
        $raw = getFlash(self::PET_FORM_FLASH);
        if ($raw === null) {
            return null;
        }

        $form = json_decode($raw, true);

        return is_array($form) && is_array($form['values'] ?? null) && is_array($form['errors'] ?? null)
            ? $form
            : null;
    }
}
