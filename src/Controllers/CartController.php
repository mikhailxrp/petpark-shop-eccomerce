<?php

declare(strict_types=1);

namespace App\Controllers;

/**
 * Корзина — /cart (phase-2.md, Таск 2; FR-CART-001–004, FR-CART-006,
 * BR-004). SQL — в src/Models/Cart.php, правила количества/сумм — в
 * Core/Cart.php. Суммы всегда пересчитываются из product_variants,
 * price_seen только записывается (ADR-016).
 *
 * update/remove отвечают JSON на fetch() из public/assets/js/cart.js и
 * redirect() на обычную отправку формы (без JS).
 */
final class CartController
{
    public function index(): void
    {
        $summary = cartSummarize(cartItemsForOwner(cartOwner()));

        render('cart', [
            'items'    => $summary['items'],
            'subtotal' => $summary['subtotal'],
            'notice'   => getFlash('cart_notice'),
            'error'    => getFlash('cart_error'),
        ]);
    }

    /**
     * «В корзину» с Карточки товара и из листинга. AJAX-отправка (Таск 3,
     * public/assets/js/cart.js) обновляет счётчик и cart-popup в шапке без
     * перезагрузки; без JS — обычный redirect() на /cart с уведомлением.
     */
    public function add(): void
    {
        requireCsrf();

        $owner = cartOwner();
        $variantId = cartNormalizeId(input('variant_id'));
        $quantity = cartNormalizeQuantity(input('quantity', '1'));

        if ($variantId === null || $quantity === null || $quantity < 1) {
            $this->respond($owner, 422, null, 'Не удалось добавить товар: укажите количество от 1.');
        }

        $variant = cartFindVariant($variantId);
        $available = $variant !== null
            ? cartAvailableQuantity((int) $variant['stock_quantity'], (int) $variant['reserved_quantity'])
            : 0;

        if ($variant === null || $available === 0) {
            $this->respond($owner, 422, null, 'Этого товара сейчас нет в наличии — в корзину он не добавлен.');
        }

        $existing = cartFindItemByVariant($owner, $variantId);
        $requested = ($existing !== null ? (int) $existing['quantity'] : 0) + $quantity;
        $finalQuantity = cartClampQuantity($requested, $available);
        $priceSeen = orderLineTotal((string) $variant['price'], $variant['discount_price'], 1);

        if ($existing !== null) {
            cartUpdateItemOnAdd($owner, (int) $existing['id'], $finalQuantity, $priceSeen);
        } else {
            cartInsertItem($owner, $variantId, $finalQuantity, $priceSeen);
        }

        $this->respond(
            $owner,
            200,
            $finalQuantity < $requested
                ? "Товар добавлен. В наличии только {$available} шт. — количество в корзине ограничено остатком."
                : 'Товар добавлен в корзину.',
            null
        );
    }

    /**
     * Изменение количества (FR-CART-001): 0 — удаление, больше остатка —
     * ограничивается остатком с предупреждением.
     */
    public function update(): void
    {
        requireCsrf();

        $owner = cartOwner();
        $itemId = cartNormalizeId(input('item_id'));
        $quantity = cartNormalizeQuantity(input('quantity'));

        if ($itemId === null || $quantity === null) {
            $this->respond($owner, 422, null, 'Количество — целое число от 0.');
        }

        $item = cartFindItem($owner, $itemId);
        if ($item === null) {
            $this->respond($owner, 404, null, 'Позиция не найдена в корзине.');
        }

        $available = cartAvailableQuantity((int) $item['stock_quantity'], (int) $item['reserved_quantity']);
        $finalQuantity = cartClampQuantity($quantity, $available);

        if ($finalQuantity === 0) {
            cartDeleteItem($owner, $itemId);
            $this->respond(
                $owner,
                200,
                $quantity > 0 ? 'Товара больше нет в наличии — позиция удалена из корзины.' : null,
                null
            );
        }

        cartUpdateItemQuantity($owner, $itemId, $finalQuantity);
        $this->respond(
            $owner,
            200,
            $finalQuantity < $quantity ? "В наличии только {$available} шт. — количество ограничено остатком." : null,
            null
        );
    }

    /**
     * Удаление Позиции (FR-CART-002) — без подтверждения, резерв не
     * затрагивается (резерва на этом шаге ещё нет, BR-003).
     */
    public function remove(): void
    {
        requireCsrf();

        $owner = cartOwner();
        $itemId = cartNormalizeId(input('item_id'));

        if ($itemId === null || !cartDeleteItem($owner, $itemId)) {
            $this->respond($owner, 404, null, 'Позиция не найдена в корзине.');
        }

        $this->respond($owner, 200, null, null);
    }

    /**
     * Ответ после изменения: для fetch() — JSON с актуальной корзиной,
     * перечитанной из БД (JS ничего не считает сам), иначе — flash и
     * redirect() на /cart.
     *
     * @param array{user_id: ?int, session_id: ?string} $owner
     */
    private function respond(array $owner, int $status, ?string $warning, ?string $error): never
    {
        if (!isAjaxRequest()) {
            if ($error !== null) {
                setFlash('cart_error', $error);
            } elseif ($warning !== null) {
                setFlash('cart_notice', $warning);
            }
            redirect('/cart');
        }

        $summary = cartSummarize(cartItemsForOwner($owner));
        $items = [];
        $count = 0;
        foreach ($summary['items'] as $item) {
            $items[] = [
                'id'        => (int) $item['id'],
                'quantity'  => (int) $item['quantity'],
                'lineTotal' => cartFormatMoney($item['line_total']) . ' ₽',
            ];
            $count += (int) $item['quantity'];
        }

        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'       => $error === null,
            'error'    => $error,
            'warning'  => $warning,
            'items'    => $items,
            'count'    => $count,
            'subtotal' => cartFormatMoney($summary['subtotal']) . ' ₽',
            'isEmpty'  => $items === [],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
