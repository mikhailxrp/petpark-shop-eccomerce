<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AiTest extends TestCase
{
    public function testUnknownOrMissingTaskIsPersonalClass(): void
    {
        $this->assertSame(AI_CLASS_PERSONAL, aiTaskClass(null));
        $this->assertSame(AI_CLASS_PERSONAL, aiTaskClass('something_new'));
        $this->assertSame(AI_CLASS_ANONYMOUS, aiTaskClass(AI_TASK_ATTRIBUTES));
        $this->assertSame(AI_CLASS_ANONYMOUS, aiTaskClass(AI_TASK_DESCRIPTION));
        $this->assertSame(AI_CLASS_PERSONAL, aiTaskClass(AI_TASK_CONSULTANT));
        $this->assertSame(AI_CLASS_PERSONAL, aiTaskClass(AI_TASK_ORDER_DRAFT));
    }

    public function testProviderIsChosenByTaskClass(): void
    {
        $map = [AI_CLASS_ANONYMOUS => 'openai', AI_CLASS_PERSONAL => 'yandexgpt'];

        $this->assertSame('openai', aiProviderName(AI_TASK_ATTRIBUTES, $map));
        $this->assertSame('yandexgpt', aiProviderName(null, $map));
        $this->assertSame('yandexgpt', aiProviderName('unknown', $map));
    }

    public function testLimitThresholdsBlockByGroup(): void
    {
        // 79% — всё разрешено, уведомления нет
        $this->assertTrue(aiTaskAllowed(AI_TASK_ATTRIBUTES, 79));
        $this->assertFalse(aiShouldNotify(79));

        // 80% — всё разрешено, уведомить
        $this->assertTrue(aiTaskAllowed(AI_TASK_ATTRIBUTES, 80));
        $this->assertTrue(aiTaskAllowed(AI_TASK_CONSULTANT, 80));
        $this->assertTrue(aiShouldNotify(80));

        // 100% — стоят фоновые, живые работают
        $this->assertFalse(aiTaskAllowed(AI_TASK_ATTRIBUTES, 100));
        $this->assertFalse(aiTaskAllowed(AI_TASK_DESCRIPTION, 100));
        $this->assertTrue(aiTaskAllowed(AI_TASK_CONSULTANT, 100));
        $this->assertTrue(aiTaskAllowed(AI_TASK_ORDER_DRAFT, 119));

        // 120% — стоит всё, включая неизвестную задачу
        $this->assertFalse(aiTaskAllowed(AI_TASK_CONSULTANT, 120));
        $this->assertFalse(aiTaskAllowed(AI_TASK_ORDER_DRAFT, 120));
        $this->assertFalse(aiTaskAllowed('unknown', 120));
    }

    public function testLimitPercentUsesIntegerMath(): void
    {
        $this->assertSame(0, aiLimitPercent('0.0000', '10000.00'));
        $this->assertSame(79, aiLimitPercent('7999.9999', '10000.00'));
        $this->assertSame(80, aiLimitPercent('8000.0000', '10000.00'));
        $this->assertSame(120, aiLimitPercent('12000.0000', '10000.00'));
        $this->assertSame(PHP_INT_MAX, aiLimitPercent('1.0000', '0.00'));
    }

    public function testCostIsDecimalStringRoundedUp(): void
    {
        $this->assertSame('0.8000', aiCallCost(1000, '0.80'));
        $this->assertSame('0.4000', aiCallCost(500, '0.80'));
        $this->assertSame('0.0008', aiCallCost(1, '0.80'));
        $this->assertSame('0.0000', aiCallCost(0, '0.80'));
        $this->assertSame('1.2000', aiCallCost(1500, '0.8000'));
    }

    public function testMoneyUnitsRoundTrip(): void
    {
        $this->assertSame(100000000, aiMoneyToUnits('10000.00'));
        $this->assertSame('10000.0000', aiUnitsToMoney(100000000));
        $this->assertSame('0.0050', aiUnitsToMoney(50));
        $this->assertSame(1, aiMoneyToUnits('0.0001'));
    }
}
