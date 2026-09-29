<?php

declare(strict_types=1);

namespace App\Controllers;

/**
 * Оформление заказа — /checkout (phase-2.md, Таск 4; FR-CHK-001–003,
 * FR-CHK-006, FR-SHIP-001–004, BR-001, BR-006). Обработчика `POST
 * /checkout` здесь ещё нет — его подключает Таск 5.
 *
 * Обе стоимости доставки (самовывоз/курьер) и оба итога считаются здесь
 * через Core/Order.php и передаются во View уже готовыми строками — JS
 * (public/assets/js/checkout.js) только переключает их при смене
 * radio, деньги на клиенте не считаются.
 */
final class CheckoutController
{
    public function index(): void
    {
        $summary = cartSummarize(cartItemsForOwner(cartOwner()));

        if ($summary['items'] === []) {
            redirect('/cart');
        }

        $contact = ['name' => '', 'phone' => '', 'email' => ''];
        $isCustomer = isAuthenticated() && ($_SESSION['user_role'] ?? null) === 'customer';

        if ($isCustomer) {
            $user = userFindById((int) $_SESSION['user_id']);
            if ($user !== null) {
                $contact = [
                    'name'  => (string) $user['name'],
                    'phone' => (string) ($user['phone'] ?? ''),
                    'email' => (string) $user['email'],
                ];
            }
        }

        $deliveryCost = [
            'pickup'  => orderDeliveryCost('pickup', $summary['subtotal'], DELIVERY_FREE_THRESHOLD, DELIVERY_COURIER_COST),
            'courier' => orderDeliveryCost('courier', $summary['subtotal'], DELIVERY_FREE_THRESHOLD, DELIVERY_COURIER_COST),
        ];

        $total = [];
        foreach ($deliveryCost as $method => $cost) {
            $total[$method] = orderKopecksToMoney(
                orderMoneyToKopecks($summary['subtotal']) + orderMoneyToKopecks($cost)
            );
        }

        render('checkout', [
            'subtotal'     => $summary['subtotal'],
            'deliveryCost' => $deliveryCost,
            'total'        => $total,
            'contact'      => $contact,
            'isCustomer'   => $isCustomer,
        ]);
    }
}
