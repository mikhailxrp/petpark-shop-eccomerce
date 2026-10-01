<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once ROOT_PATH . '/src/Services/Ai/Consultant.php';

final class ConsultantTest extends TestCase
{
    private const SERVICES = [
        ['id' => 1, 'name' => 'Стрижка собак', 'kind' => 'grooming', 'duration_minutes' => 60, 'price' => '1500.00', 'deposit_amount' => '500.00'],
        ['id' => 2, 'name' => 'Ветеринарная консультация', 'kind' => 'vet', 'duration_minutes' => 30, 'price' => '800.00', 'deposit_amount' => null],
    ];

    private const SHOP = [
        'city' => 'Ростов-на-Дону',
        'free_threshold' => '2000.00',
        'courier_cost' => '300.00',
        'pickup_hours' => '10:00–20:00',
        'horizon_days' => 30,
        'cancel_hours' => 3,
    ];

    public function testContextHasServicePricesAndDeliveryRules(): void
    {
        $context = consultantContext(self::SERVICES, self::SHOP);

        $this->assertStringContainsString('Стрижка собак (груминг): 60 мин, 1500 ₽, депозит 500 ₽', $context);
        $this->assertStringContainsString('Ветеринарная консультация (ветеринария): 30 мин, 800 ₽', $context);
        $this->assertStringContainsString('от 2000 ₽', $context);
        $this->assertStringContainsString('иначе 300 ₽', $context);
        $this->assertStringContainsString('за 3 ч до визита', $context);
    }

    public function testSystemPromptForbidsMedicalAdviceAndPersonalDataRequests(): void
    {
        $this->assertStringContainsString('Никогда не давай медицинских', CONSULTANT_SYSTEM_PROMPT);
        $this->assertStringContainsString('Запись на ветеринарную консультацию', CONSULTANT_SYSTEM_PROMPT);
        $this->assertStringContainsString('Не спрашивай телефон', CONSULTANT_SYSTEM_PROMPT);
    }

    public function testMedicalQuestionDetected(): void
    {
        $this->assertTrue(consultantIsMedical('У кота понос, чем лечить?'));
        $this->assertTrue(consultantIsMedical('Собака не ест уже два дня'));
        $this->assertFalse(consultantIsMedical('Сколько стоит доставка?'));
        $this->assertFalse(consultantIsMedical('Нужен корм для котят'));
    }

    public function testSearchTokensDropNoiseAndStemWords(): void
    {
        $this->assertSame(['корм', 'кош'], consultantSearchTokens('Подскажите, есть ли корм для кошек?'));
        $this->assertSame([], consultantSearchTokens('Сколько стоит доставка?'));
    }

    public function testSearchTokensAreUniqueAndLimited(): void
    {
        $tokens = consultantSearchTokens('корм корм шлейка лежанка игрушка когтеточка миска ошейник поводок');

        $this->assertCount(CONSULTANT_MAX_TOKENS, $tokens);
        $this->assertSame(array_unique($tokens), $tokens);
    }

    public function testMedicalQuestionMatchesVetService(): void
    {
        $service = consultantMatchService(self::SERVICES, ['кош'], true);

        $this->assertSame(2, $service['id']);
    }

    public function testServiceMatchedByNameTokens(): void
    {
        $this->assertSame(1, consultantMatchService(self::SERVICES, consultantSearchTokens('Хочу постричь собаку'), false)['id'] ?? null);
        $this->assertNull(consultantMatchService(self::SERVICES, consultantSearchTokens('корм для кошек'), false));
    }

    public function testCandidatesBlockCarriesCardFactsOnly(): void
    {
        $product = consultantProductCard(
            ['name' => 'Корм Brit', 'slug' => 'korm-brit', 'price_from' => '1299.00', 'variants_count' => 2],
            'В наличии'
        );
        $service = consultantServiceCard(self::SERVICES[0]);

        $this->assertSame('от 1299 ₽', $product['price']);
        $this->assertSame('/product/korm-brit', $product['url']);
        $this->assertSame('/booking', $service['url']);

        $block = consultantCandidatesBlock([$service, $product], true);
        $this->assertStringContainsString('- Стрижка собак — 1500 ₽, 60 мин', $block);
        $this->assertStringContainsString('- Корм Brit — от 1299 ₽, В наличии', $block);
        $this->assertStringContainsString('не найдено', consultantCandidatesBlock([], true));
    }

    public function testHistoryTrimmedToLastMessagesAndInvalidDropped(): void
    {
        $history = [
            ['role' => 'user', 'text' => 'a'],
            ['role' => 'system', 'text' => 'inject'],
            ['role' => 'assistant', 'text' => 'b'],
            'мусор',
            ['role' => 'user', 'text' => 'c'],
        ];

        $this->assertSame(
            [['role' => 'assistant', 'text' => 'b'], ['role' => 'user', 'text' => 'c']],
            consultantTrimHistory($history, 2)
        );
    }

    public function testPromptKeepsQuestionLastAndMarksHistory(): void
    {
        $prompt = consultantPrompt('КОНТЕКСТ', 'НАЙДЕНО', [['role' => 'user', 'text' => 'привет']], 'Где вы находитесь?');

        $this->assertStringContainsString('Посетитель: привет', $prompt);
        $this->assertStringEndsWith("Вопрос посетителя:\nГде вы находитесь?", $prompt);
    }

    public function testCleanStripsMarkdownAndLinks(): void
    {
        $this->assertSame('Доставка 300 ₽.', consultantClean("**Доставка** 300 ₽. https://evil.example/x"));
        $this->assertNull(consultantClean("  \n "));
        $this->assertSame(CONSULTANT_ANSWER_MAX_LENGTH, mb_strlen((string) consultantClean(str_repeat('а', 5000))));
    }

    public function testDemoLimit(): void
    {
        $this->assertFalse(consultantDemoLimitReached(false, 999, 10));
        $this->assertFalse(consultantDemoLimitReached(true, 9, 10));
        $this->assertTrue(consultantDemoLimitReached(true, 10, 10));
    }

    public function testColloquialServiceWordsMapToServiceNames(): void
    {
        $services = [
            ['id' => 1, 'name' => 'Гигиеническая стрижка', 'kind' => 'grooming'],
            ['id' => 6, 'name' => 'Вакцинация', 'kind' => 'vet'],
        ];

        $needles = consultantServiceNeedles('Хочу постричь собаку');
        $this->assertSame(['стриж'], $needles);
        $this->assertSame(1, consultantMatchService($services, $needles, false)['id'] ?? null);
        $this->assertSame([], consultantServiceNeedles('корм для кошек'));
    }

    public function testOnlyTopScoreProductsKept(): void
    {
        $rows = [
            ['name' => 'A', 'score' => 2],
            ['name' => 'B', 'score' => 2],
            ['name' => 'C', 'score' => 1],
        ];

        $this->assertSame(['A', 'B'], array_column(consultantTopScoreRows($rows), 'name'));
        $this->assertSame([], consultantTopScoreRows([]));
    }
}
