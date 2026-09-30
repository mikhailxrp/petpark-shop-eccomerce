<?php

declare(strict_types=1);

namespace Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class OrderTest extends TestCase
{
    private const FREE_THRESHOLD = '2000.00';
    private const COURIER_COST = '300.00';

    public function testMoneyToKopecksParsesDecimalStrings(): void
    {
        $this->assertSame(150000, \orderMoneyToKopecks('1500.00'));
        $this->assertSame(150050, \orderMoneyToKopecks('1500.5'));
        $this->assertSame(150000, \orderMoneyToKopecks('1500'));
        $this->assertSame(10, \orderMoneyToKopecks('0.10'));
    }

    public function testMoneyToKopecksRejectsInvalidAmounts(): void
    {
        foreach (['-1.00', '1.005', 'abc', '', '1,50'] as $amount) {
            try {
                \orderMoneyToKopecks($amount);
                $this->fail("Ожидалось исключение для «{$amount}»");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testKopecksToMoneyFormatsAsDecimal(): void
    {
        $this->assertSame('1500.50', \orderKopecksToMoney(150050));
        $this->assertSame('0.05', \orderKopecksToMoney(5));
        $this->assertSame('0.00', \orderKopecksToMoney(0));
    }

    public function testLineTotalUsesDiscountPriceWhenSet(): void
    {
        $this->assertSame('1797.00', \orderLineTotal('999.00', '599.00', 3));
        $this->assertSame('1998.00', \orderLineTotal('999.00', null, 2));
    }

    public function testLineTotalHasNoRoundingError(): void
    {
        $this->assertSame('0.30', \orderLineTotal('0.10', null, 3));
    }

    public function testLineTotalRejectsNonPositiveQuantity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        \orderLineTotal('100.00', null, 0);
    }

    public function testCartSubtotalSumsLines(): void
    {
        $lines = [
            ['price' => '999.00', 'discount_price' => '599.00', 'quantity' => 2],
            ['price' => '0.10', 'discount_price' => null, 'quantity' => 3],
            ['price' => '150.20', 'discount_price' => null, 'quantity' => 1],
        ];

        $this->assertSame('1348.50', \orderCartSubtotal($lines));
        $this->assertSame('0.00', \orderCartSubtotal([]));
    }

    public function testDeliveryCostPickupIsAlwaysFree(): void
    {
        $this->assertSame('0.00', \orderDeliveryCost('pickup', '100.00', self::FREE_THRESHOLD, self::COURIER_COST));
        $this->assertSame('0.00', \orderDeliveryCost('pickup', '5000.00', self::FREE_THRESHOLD, self::COURIER_COST));
    }

    public function testDeliveryCostCourierBelowThreshold(): void
    {
        $this->assertSame('300.00', \orderDeliveryCost('courier', '1500.00', self::FREE_THRESHOLD, self::COURIER_COST));
        $this->assertSame('300.00', \orderDeliveryCost('courier', '1999.99', self::FREE_THRESHOLD, self::COURIER_COST));
    }

    public function testDeliveryCostCourierAtOrAboveThresholdIsFree(): void
    {
        $this->assertSame('0.00', \orderDeliveryCost('courier', '2000.00', self::FREE_THRESHOLD, self::COURIER_COST));
        $this->assertSame('0.00', \orderDeliveryCost('courier', '2500.00', self::FREE_THRESHOLD, self::COURIER_COST));
    }

    public function testDeliveryCostRejectsUnknownMethod(): void
    {
        $this->expectException(InvalidArgumentException::class);
        \orderDeliveryCost('drone', '100.00', self::FREE_THRESHOLD, self::COURIER_COST);
    }

    public function testAllowedStatusTransitions(): void
    {
        $this->assertTrue(\orderCanTransition('new', 'confirmed'));
        $this->assertTrue(\orderCanTransition('new', 'cancelled'));
        $this->assertTrue(\orderCanTransition('assembled', 'ready_for_pickup'));
        $this->assertTrue(\orderCanTransition('ready_for_pickup', 'cancelled'));
        $this->assertTrue(\orderCanTransition('shipped', 'cancelled'));
    }

    public function testForbiddenStatusTransitions(): void
    {
        $this->assertFalse(\orderCanTransition('delivered', 'new'));
        $this->assertFalse(\orderCanTransition('cancelled', 'confirmed'));
        $this->assertFalse(\orderCanTransition('new', 'shipped'));
        $this->assertFalse(\orderCanTransition('new', 'new'));
    }

    public function testUnknownStatusIsNotAllowed(): void
    {
        $this->assertFalse(\orderCanTransition('paid', 'confirmed'));
        $this->assertFalse(\orderCanTransition('new', 'paid'));
    }

    public function testStockActionForEveryAllowedTransition(): void
    {
        $expected = [
            'new'              => ['confirmed' => 'stock_deduct', 'cancelled' => 'reserve_release'],
            'confirmed'        => ['assembled' => 'none', 'cancelled' => 'stock_restore'],
            'assembled'        => ['shipped' => 'none', 'ready_for_pickup' => 'none', 'cancelled' => 'stock_restore'],
            'shipped'          => ['delivered' => 'none', 'cancelled' => 'stock_restore'],
            'ready_for_pickup' => ['picked_up' => 'none', 'cancelled' => 'stock_restore'],
        ];

        $checked = 0;
        foreach (ORDER_STATUS_TRANSITIONS as $from => $targets) {
            foreach ($targets as $to) {
                $this->assertSame($expected[$from][$to], \orderStockAction($from, $to), "{$from} → {$to}");
                $checked++;
            }
        }

        // Карта и ожидания разошлись — тест не должен молча пропустить пару.
        $this->assertSame(array_sum(array_map('count', $expected)), $checked);
    }

    public function testStockActionRejectsForbiddenTransition(): void
    {
        $this->expectException(InvalidArgumentException::class);
        \orderStockAction('delivered', 'new');
    }

    public function testStockActionRejectsTransitionFromCancelled(): void
    {
        $this->expectException(InvalidArgumentException::class);
        \orderStockAction('cancelled', 'confirmed');
    }

    public function testBuildDeliveryAddressFull(): void
    {
        $this->assertSame(
            'Ростов-на-Дону, ул. Садовая, д. 10, кв. 5. Комментарий курьеру: код домофона 5К',
            \orderBuildDeliveryAddress('Ростов-на-Дону', ' ул. Садовая ', '10', '5', 'код домофона 5К')
        );
    }

    public function testBuildDeliveryAddressSkipsEmptyParts(): void
    {
        $this->assertSame(
            'Ростов-на-Дону, ул. Садовая, д. 10',
            \orderBuildDeliveryAddress('Ростов-на-Дону', 'ул. Садовая', '10', '  ', '')
        );
    }

    public function testBuildDeliveryAddressIsTruncatedToColumnLength(): void
    {
        $address = \orderBuildDeliveryAddress('Ростов-на-Дону', 'ул. Садовая', '10', '5', str_repeat('я', 400));

        $this->assertSame(255, mb_strlen($address));
    }

    public function testOrderIsViewableBySessionThatPlacedIt(): void
    {
        $_SESSION = ['checkout_order_ids' => [5]];

        $this->assertTrue(\orderCanBeViewedBySession(['id' => 5, 'user_id' => null]));
        $this->assertFalse(\orderCanBeViewedBySession(['id' => 6, 'user_id' => null]));
    }

    public function testOrderIsViewableByItsCustomerOwnerOnly(): void
    {
        $_SESSION = ['user_id' => 9, 'user_role' => 'customer'];

        $this->assertTrue(\orderCanBeViewedBySession(['id' => 5, 'user_id' => 9]));
        $this->assertFalse(\orderCanBeViewedBySession(['id' => 5, 'user_id' => 10]));
        $this->assertFalse(\orderCanBeViewedBySession(['id' => 5, 'user_id' => null]));

        $_SESSION = ['user_id' => 9, 'user_role' => 'owner'];
        $this->assertFalse(\orderCanBeViewedBySession(['id' => 5, 'user_id' => 9]));
    }
}
