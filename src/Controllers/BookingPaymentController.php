<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AmoCrm;
use App\Services\Payment\PaymentGateway;
use App\Services\Payment\YooMoneyStubGateway;

/**
 * Оплата Депозита Записи — заглушка ЮMoney (phase-4.md, Таск 5; FR-SV-005,
 * FR-SV-006, FR-SV-009). Страницы доступны сессии, создавшей Запись, либо её
 * владельцу (то же правило, что у страницы успеха); чужая/несуществующая
 * Запись — 404.
 *
 * Смена статуса — только условными UPDATE в Models/Booking.php: повторный
 * «оплачено» и оплата после истечения удержания ничего не меняют. Каждый
 * callback пишется в payment_logs. Пользователю про подпись и шлюз ничего
 * не показываем.
 */
final class BookingPaymentController
{
    private const PAYMENT_FAILED_ERROR = 'Не удалось выполнить платёж. Попробуйте ещё раз.';
    private const RELEASED_NOTICE = 'Запись отменена, время снова доступно другим.';
    private const RETURN_PETS = 'pets';

    private PaymentGateway $gateway;

    public function __construct()
    {
        $this->gateway = new YooMoneyStubGateway(env('PAYMENT_STUB_SECRET'));
    }

    public function show(string $id): void
    {
        $booking = $this->findPayableBooking($id);
        $bookingId = (int) $booking['id'];

        render('payment/booking', [
            'booking'           => $booking,
            'signaturePaid'     => $this->gateway->signBooking($bookingId, PaymentGateway::RESULT_PAID),
            'signatureDeclined' => $this->gateway->signBooking($bookingId, PaymentGateway::RESULT_DECLINED),
        ]);
    }

    public function callback(string $id): void
    {
        requireCsrf();

        $booking = $this->findViewableBooking($id);
        $bookingId = (int) $booking['id'];

        $result = (string) input('result');
        $signature = (string) input('signature');
        $signatureValid = in_array($result, [PaymentGateway::RESULT_PAID, PaymentGateway::RESULT_DECLINED], true)
            && $this->gateway->verifyBookingSignature($bookingId, $result, $signature);

        bookingPaymentLogCreate(
            $bookingId,
            $this->gateway->providerName(),
            $signatureValid,
            (string) json_encode(['result' => $result, 'signature' => $signature], JSON_UNESCAPED_UNICODE)
        );

        if (!$signatureValid) {
            logWarning('Платёжный callback Записи отклонён', ['booking_id' => $bookingId, 'signature_valid' => false]);
            setFlash('payment_error', self::PAYMENT_FAILED_ERROR);
            redirect('/booking/' . $bookingId . '/pay/failed');
        }

        if ($result === PaymentGateway::RESULT_PAID) {
            // false = уже подтверждена/отпущена/срок истёк: статус не трогаем.
            if (bookingConfirmWithDeposit($bookingId)) {
                (new AmoCrm())->registerBooking($bookingId);
            }
            redirect('/booking/success/' . $bookingId);
        }

        redirect('/booking/' . $bookingId . '/pay/failed');
    }

    public function failed(string $id): void
    {
        $booking = $this->findPayableBooking($id);

        render('payment/booking-failed', [
            'booking' => $booking,
            'error'   => getFlash('payment_error'),
        ]);
    }

    /** Отказ от неоплаченного удержания (со страницы неудачи и из карточки Питомца). */
    public function release(string $id): void
    {
        requireCsrf();

        $booking = $this->findViewableBooking($id);

        if (bookingReleaseHold((int) $booking['id'])) {
            logInfo('Удержание слота отменено Покупателем', ['booking_id' => (int) $booking['id']]);
        }

        $backToPets = (string) input('return') === self::RETURN_PETS
            && isAuthenticated()
            && ($_SESSION['user_role'] ?? null) === 'customer';
        if ($backToPets) {
            setFlash('success', self::RELEASED_NOTICE);
            redirect('/account/pets');
        }

        setFlash('booking_notice', self::RELEASED_NOTICE);
        redirect('/booking');
    }

    /**
     * Запись доступна сессии; иначе 404 (не раскрываем, существует ли номер).
     * Истёкшие удержания освобождаются до чтения — статус всегда актуален.
     *
     * @return array<string, mixed>
     */
    private function findViewableBooking(string $id): array
    {
        $bookingId = ctype_digit($id) ? (int) $id : 0;
        bookingReleaseExpired();
        $booking = $bookingId > 0 ? bookingFindById($bookingId) : null;

        if ($booking === null || !$this->canView($booking)) {
            http_response_code(404);
            render('errors/404');
            exit;
        }

        return $booking;
    }

    /**
     * Запись, Депозит которой ещё можно внести; остальные — на страницу
     * успеха, где виден их фактический статус.
     *
     * @return array<string, mixed>
     */
    private function findPayableBooking(string $id): array
    {
        $booking = $this->findViewableBooking($id);

        if ($booking['status'] !== 'slot_selected') {
            redirect('/booking/success/' . (int) $booking['id']);
        }

        return $booking;
    }

    /** Запись видна сессии, создавшей её, и её владельцу-Покупателю (как BookingController::canView()). */
    private function canView(array $booking): bool
    {
        ensureSessionStarted();

        $inSession = in_array((int) $booking['id'], $_SESSION['booking_ids'] ?? [], true);
        $isOwner = isAuthenticated()
            && ($_SESSION['user_role'] ?? null) === 'customer'
            && (int) $booking['user_id'] === (int) $_SESSION['user_id'];

        return $inSession || $isOwner;
    }
}
