<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once ROOT_PATH . '/src/Services/Ai/AttributeExtractor.php';

final class AttributeExtractorTest extends TestCase
{
    private const NAMES = ['вид_животного', 'возраст', 'назначение'];

    private const DICTIONARY = [
        'вид_животного' => ['кошка', 'собака'],
        'возраст'       => [],
        'назначение'    => ['для стерилизованных'],
    ];

    public function testValueFromDictionaryIsPendingWithCanonicalSpelling(): void
    {
        $drafts = attributeDraftsFromResponse(
            '{"вид_животного": "Кошка", "возраст": null, "назначение": "для стерилизованных"}',
            self::NAMES,
            self::DICTIONARY
        );

        $this->assertSame(['value' => 'кошка', 'status' => ATTRIBUTE_STATUS_PENDING], $drafts['вид_животного']);
        $this->assertSame(['value' => 'для стерилизованных', 'status' => ATTRIBUTE_STATUS_PENDING], $drafts['назначение']);
    }

    public function testMissingValueStaysEmpty(): void
    {
        $drafts = attributeDraftsFromResponse('{"вид_животного": "кошка", "возраст": null}', self::NAMES, self::DICTIONARY);

        $this->assertSame(['value' => null, 'status' => ATTRIBUTE_STATUS_EMPTY], $drafts['возраст']);
        $this->assertSame(['value' => null, 'status' => ATTRIBUTE_STATUS_EMPTY], $drafts['назначение']);
    }

    public function testValueOutsideDictionaryNeedsDecision(): void
    {
        $drafts = attributeDraftsFromResponse('{"возраст": "котёнок", "вид_животного": "хорёк"}', self::NAMES, self::DICTIONARY);

        $this->assertSame(['value' => 'котёнок', 'status' => ATTRIBUTE_STATUS_DECISION], $drafts['возраст']);
        $this->assertSame(['value' => 'хорёк', 'status' => ATTRIBUTE_STATUS_DECISION], $drafts['вид_животного']);
    }

    public function testJsonInsideCodeFenceIsParsed(): void
    {
        $drafts = attributeDraftsFromResponse("```json\n{\"вид_животного\": \"собака\"}\n```", self::NAMES, self::DICTIONARY);

        $this->assertSame('собака', $drafts['вид_животного']['value']);
    }

    public function testNonStringValueIsTreatedAsEmpty(): void
    {
        $drafts = attributeDraftsFromResponse('{"возраст": 3, "вид_животного": ["кошка"]}', self::NAMES, self::DICTIONARY);

        $this->assertSame(ATTRIBUTE_STATUS_EMPTY, $drafts['возраст']['status']);
        $this->assertSame(ATTRIBUTE_STATUS_EMPTY, $drafts['вид_животного']['status']);
    }

    public function testGarbageResponseIsNull(): void
    {
        $this->assertNull(attributeDraftsFromResponse('не знаю', self::NAMES, self::DICTIONARY));
        $this->assertNull(attributeDraftsFromResponse('{сломанный json}', self::NAMES, self::DICTIONARY));
    }

    public function testOnlyRequestedNamesAreReturned(): void
    {
        $drafts = attributeDraftsFromResponse('{"вид_животного": "кошка", "цена": "100"}', ['вид_животного'], self::DICTIONARY);

        $this->assertSame(['вид_животного'], array_keys($drafts));
    }

    public function testPromptContainsOnlyProductTextAndDictionary(): void
    {
        $prompt = attributeExtractPrompt('Корм Pro', 'Беззерновой', self::NAMES, self::DICTIONARY);

        $this->assertStringContainsString('Корм Pro', $prompt);
        $this->assertStringContainsString('Беззерновой', $prompt);
        $this->assertStringContainsString('кошка, собака', $prompt);
    }

    public function testConfirmTakesDraftValueAsAccepted(): void
    {
        $decision = attributeDecisionResolve(ATTRIBUTE_ACTION_CONFIRM, 'кошка', 'игнорируется');

        $this->assertSame(['error' => null, 'value' => 'кошка', 'outcome' => 'accepted'], $decision);
    }

    public function testConfirmWithoutDraftValueIsError(): void
    {
        $this->assertNotNull(attributeDecisionResolve(ATTRIBUTE_ACTION_CONFIRM, null, '')['error']);
        $this->assertNotNull(attributeDecisionResolve(ATTRIBUTE_ACTION_CONFIRM, '  ', '')['error']);
    }

    public function testEditTrimsInputAndIsEdited(): void
    {
        $decision = attributeDecisionResolve(ATTRIBUTE_ACTION_EDIT, 'кошка', '  котёнок ');

        $this->assertSame(['error' => null, 'value' => 'котёнок', 'outcome' => 'edited'], $decision);
    }

    public function testEditRejectsEmptyAndTooLongValue(): void
    {
        $this->assertNotNull(attributeDecisionResolve(ATTRIBUTE_ACTION_EDIT, 'кошка', '   ')['error']);
        $this->assertNotNull(attributeDecisionResolve(ATTRIBUTE_ACTION_EDIT, 'кошка', str_repeat('я', ATTRIBUTE_VALUE_MAX_LENGTH + 1))['error']);
        $this->assertNull(attributeDecisionResolve(ATTRIBUTE_ACTION_EDIT, 'кошка', str_repeat('я', ATTRIBUTE_VALUE_MAX_LENGTH))['error']);
    }

    public function testRejectWritesNothing(): void
    {
        $this->assertSame(['error' => null, 'value' => null, 'outcome' => 'rejected'], attributeDecisionResolve(ATTRIBUTE_ACTION_REJECT, 'кошка', ''));
    }

    public function testUnknownActionIsError(): void
    {
        $this->assertNotNull(attributeDecisionResolve('drop', 'кошка', 'x')['error']);
    }
}
