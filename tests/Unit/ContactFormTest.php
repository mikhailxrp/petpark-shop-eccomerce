<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ContactFormTest extends TestCase
{
    /** @return array<string, string> */
    private static function valid(): array
    {
        return [
            'name'    => 'Иван Петров',
            'phone'   => '8 (900) 000-00-00',
            'email'   => 'ivan@example.ru',
            'message' => 'Здравствуйте! Подскажите часы работы.',
        ];
    }

    public function testValidFormHasNoErrorsAndNormalizesPhone(): void
    {
        $result = contactFormValidate(self::valid());

        $this->assertSame([], $result['errors']);
        $this->assertSame('+79000000000', $result['values']['phone']);
        $this->assertSame('Иван Петров', $result['values']['name']);
    }

    public function testEmptyFormFlagsAllFourFields(): void
    {
        $result = contactFormValidate([]);

        $this->assertSame(['name', 'phone', 'email', 'message'], array_keys($result['errors']));
        $this->assertSame(CONTACT_ERROR_REQUIRED, $result['errors']['name']);
    }

    public function testWhitespaceOnlyCountsAsEmpty(): void
    {
        $result = contactFormValidate(['name' => '   ', 'phone' => ' ', 'email' => "\t", 'message' => "\n "]);

        $this->assertCount(4, $result['errors']);
    }

    /** @return array<string, array{string}> */
    public static function badPhones(): array
    {
        return ['буквы' => ['abc'], 'короткий' => ['+7900'], 'чужой код' => ['+380501234567'], 'ник' => ['@ivan']];
    }

    #[DataProvider('badPhones')]
    public function testBadPhoneRejectedAndValueKept(string $phone): void
    {
        $result = contactFormValidate(['phone' => $phone] + self::valid());

        $this->assertSame(['phone' => CONTACT_ERROR_PHONE], $result['errors']);
        $this->assertSame($phone, $result['values']['phone']);
    }

    /** @return array<string, array{string}> */
    public static function badEmails(): array
    {
        return ['без @' => ['abc'], 'без домена-зоны' => ['a@b'], 'пробел' => ['a b@example.ru'], 'без имени' => ['@example.ru']];
    }

    #[DataProvider('badEmails')]
    public function testBadEmailRejected(string $email): void
    {
        $result = contactFormValidate(['email' => $email] + self::valid());

        $this->assertSame(['email' => CONTACT_ERROR_EMAIL], $result['errors']);
    }

    public function testLengthLimits(): void
    {
        $result = contactFormValidate([
            'name'    => str_repeat('я', CONTACT_NAME_MAX + 1),
            'message' => str_repeat('я', CONTACT_MESSAGE_MAX + 1),
        ] + self::valid());

        $this->assertSame(['name', 'message'], array_keys($result['errors']));
        $this->assertSame(CONTACT_ERROR_TOO_LONG, $result['errors']['name']);

        $atLimit = contactFormValidate(['name' => str_repeat('я', CONTACT_NAME_MAX)] + self::valid());
        $this->assertSame([], $atLimit['errors']);
    }

    public function testSingleLineFieldsLoseLineBreaksButMessageKeepsThem(): void
    {
        $result = contactFormValidate([
            'name'    => "Иван\r\nBcc: evil@example.ru",
            'message' => "строка 1\nстрока 2",
        ] + self::valid());

        $this->assertSame('Иван Bcc: evil@example.ru', $result['values']['name']);
        $this->assertSame("строка 1\nстрока 2", $result['values']['message']);
    }

    public function testNonStringInputTreatedAsEmpty(): void
    {
        $result = contactFormValidate(['name' => ['x'], 'message' => ['y']] + self::valid());

        $this->assertSame(['name', 'message'], array_keys($result['errors']));
    }

    public function testHtmlInMessageIsNotAlteredByValidation(): void
    {
        $result = contactFormValidate(['message' => '<script>alert(1)</script>'] + self::valid());

        $this->assertSame([], $result['errors']);
        $this->assertSame('<script>alert(1)</script>', $result['values']['message']);
    }
}
