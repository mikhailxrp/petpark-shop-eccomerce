<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CartTest extends TestCase
{
    protected function setUp(): void
    {
        // Сначала старт сессии: первый session_start() в процессе
        // перезаписал бы $_SESSION, выставленный тестом.
        ensureSessionStarted();
        $_SESSION = [];
        unset($_COOKIE[CART_GUEST_COOKIE_NAME]);
    }

    public function testNormalizeQuantityAcceptsNonNegativeIntegers(): void
    {
        $this->assertSame(0, \cartNormalizeQuantity('0'));
        $this->assertSame(5, \cartNormalizeQuantity('5'));
        $this->assertSame(5, \cartNormalizeQuantity(' 5 '));
        $this->assertSame(3, \cartNormalizeQuantity(3));
    }

    public function testNormalizeQuantityRejectsGarbage(): void
    {
        foreach (['-1', '1.5', 'abc', '', ' ', '1e3', -2, 1.0, null, ['1']] as $raw) {
            $this->assertNull(\cartNormalizeQuantity($raw), 'Ожидался null для ' . var_export($raw, true));
        }
    }

    public function testNormalizeIdRequiresPositive(): void
    {
        $this->assertSame(42, \cartNormalizeId('42'));
        $this->assertNull(\cartNormalizeId('0'));
        $this->assertNull(\cartNormalizeId('-3'));
        $this->assertNull(\cartNormalizeId('x'));
    }

    public function testAvailableQuantityIsStockMinusReserved(): void
    {
        $this->assertSame(3, \cartAvailableQuantity(5, 2));
        $this->assertSame(0, \cartAvailableQuantity(2, 2));
        $this->assertSame(0, \cartAvailableQuantity(1, 4));
    }

    public function testClampQuantityLimitsByAvailable(): void
    {
        $this->assertSame(3, \cartClampQuantity(5, 3));
        $this->assertSame(2, \cartClampQuantity(2, 3));
        $this->assertSame(0, \cartClampQuantity(0, 3));
        $this->assertSame(0, \cartClampQuantity(4, 0));
        $this->assertSame(3, \cartClampQuantity(5, \cartAvailableQuantity(5, 2)));
    }

    public function testSummarizeUsesDiscountPriceAndSumsLines(): void
    {
        $summary = \cartSummarize([
            ['price' => '1000.00', 'discount_price' => null, 'quantity' => 2, 'stock_quantity' => 10, 'reserved_quantity' => 0, 'price_seen' => '1.00'],
            ['price' => '500.00', 'discount_price' => '350.50', 'quantity' => 3, 'stock_quantity' => 5, 'reserved_quantity' => 1, 'price_seen' => '500.00'],
        ]);

        $this->assertSame('2000.00', $summary['items'][0]['line_total']);
        $this->assertSame('1051.50', $summary['items'][1]['line_total']);
        $this->assertSame(4, $summary['items'][1]['available']);
        // price_seen в расчёт не входит (ADR-016)
        $this->assertSame('3051.50', $summary['subtotal']);
    }

    public function testSummarizeEmptyCart(): void
    {
        $this->assertSame(['items' => [], 'subtotal' => '0.00'], \cartSummarize([]));
    }

    public function testFormatMoneyShowsKopecksOnlyWhenPresent(): void
    {
        $this->assertSame('1500', \cartFormatMoney('1500.00'));
        $this->assertSame('1500,50', \cartFormatMoney('1500.50'));
        $this->assertSame('0,05', \cartFormatMoney('0.05'));
    }

    public function testOwnerIsCustomerUserId(): void
    {
        $_SESSION = ['user_id' => 7, 'user_role' => 'customer'];

        $this->assertSame(['user_id' => 7, 'session_id' => null], \cartOwner());
    }

    public function testOwnerGuestGetsStableToken(): void
    {
        $first = \cartOwner();

        $this->assertNull($first['user_id']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', (string) $first['session_id']);
        $this->assertSame($first, \cartOwner());
    }

    public function testOwnerStaffAndBrokenTokenFallBackToFreshGuestToken(): void
    {
        $_SESSION = ['user_id' => 3, 'user_role' => 'owner'];
        $_COOKIE[CART_GUEST_COOKIE_NAME] = 'not-a-token';

        $owner = \cartOwner();

        $this->assertNull($owner['user_id']);
        $this->assertNotSame('not-a-token', $owner['session_id']);
        $this->assertSame($owner['session_id'], $_COOKIE[CART_GUEST_COOKIE_NAME]);
    }

    public function testOwnerGuestTokenSurvivesAcrossCallsViaCookie(): void
    {
        $first = \cartOwner();

        // Новый вызов cartOwner() имитирует новый HTTP-запрос — $_SESSION
        // из предыдущего вызова сохраняется в рамках теста, но токен
        // Гостя теперь источником правды имеет cookie (ADR-015), не сессию.
        $this->assertSame($first['session_id'], $_COOKIE[CART_GUEST_COOKIE_NAME]);
    }
}
