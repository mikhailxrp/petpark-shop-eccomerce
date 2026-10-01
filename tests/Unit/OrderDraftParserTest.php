<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once ROOT_PATH . '/src/Core/Pii.php';
require_once ROOT_PATH . '/src/Core/Cart.php';
require_once ROOT_PATH . '/src/Services/Ai/OrderDraftParser.php';

final class OrderDraftParserTest extends TestCase
{
    private const CANDIDATES = [
        ['variant_id' => 11, 'name' => 'Корм для стерилизованных кошек', 'label' => '2 кг', 'price' => '1250.00'],
        ['variant_id' => 12, 'name' => 'Корм для стерилизованных кошек', 'label' => '5 кг', 'price' => '2900.00'],
    ];

    public function testItemsAreTakenOnlyFromCandidates(): void
    {
        $draft = orderDraftFromResponse(
            'Вот: {"items": [{"variant_id": 11, "quantity": 2}, {"variant_id": 999, "quantity": 1}], "note": null}',
            self::CANDIDATES
        );

        self::assertNotNull($draft);
        self::assertCount(1, $draft['items']);
        self::assertSame(11, $draft['items'][0]['variant_id']);
        self::assertSame(2, $draft['items'][0]['quantity']);
        self::assertSame('1250.00', $draft['items'][0]['price']);
    }

    public function testQuantityIsClampedAndDuplicatesAreSummed(): void
    {
        $draft = orderDraftFromResponse(
            '{"items": [{"variant_id": 11, "quantity": 15}, {"variant_id": 11, "quantity": 15}, {"variant_id": 12, "quantity": 0}]}',
            self::CANDIDATES
        );

        self::assertNotNull($draft);
        self::assertCount(1, $draft['items']);
        self::assertSame(ORDER_DRAFT_MAX_QUANTITY, $draft['items'][0]['quantity']);
    }

    public function testPhoneAndAddressInNoteAreRemoved(): void
    {
        $draft = orderDraftFromResponse(
            '{"items": [], "note": "Позвонить на +7 900 123-45-67, ул. Ленина, д. 5"}',
            self::CANDIDATES
        );

        self::assertNotNull($draft);
        self::assertStringNotContainsString('900', $draft['note']);
        self::assertStringNotContainsString('Ленина', $draft['note']);
    }

    public function testUnparsableResponseReturnsNull(): void
    {
        self::assertNull(orderDraftFromResponse('не знаю', self::CANDIDATES));
        self::assertNull(orderDraftFromResponse('{"note": "x"}', self::CANDIDATES));
    }

    public function testCustomerTextTakesOnlyIncomingMessages(): void
    {
        $text = orderDraftCustomerText([
            ['direction' => 'in', 'body' => 'Нужен корм'],
            ['direction' => 'out', 'body' => 'Какой?'],
            ['direction' => 'in', 'body' => '2 кг'],
        ]);

        self::assertSame("Нужен корм\n2 кг", $text);
    }

    public function testPromptContainsNoPhoneWhenTextIsRedacted(): void
    {
        $clean = piiRedact('Хочу корм, мой номер 89001234567');
        $prompt = orderDraftPrompt($clean, self::CANDIDATES);

        self::assertStringNotContainsString('9001234567', $prompt);
        self::assertStringContainsString('id 11: Корм для стерилизованных кошек, 2 кг — 1250 ₽', $prompt);
    }
}
