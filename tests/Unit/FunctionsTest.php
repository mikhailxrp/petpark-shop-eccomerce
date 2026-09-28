<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class FunctionsTest extends TestCase
{
    protected function tearDown(): void
    {
        $_SESSION = [];
        unset($_SERVER['HTTP_X_REQUESTED_WITH']);
    }

    public function testIsAjaxRequestTrueForXmlHttpRequestHeader(): void
    {
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $this->assertTrue(isAjaxRequest());
    }

    public function testIsAjaxRequestFalseWhenHeaderMissing(): void
    {
        unset($_SERVER['HTTP_X_REQUESTED_WITH']);
        $this->assertFalse(isAjaxRequest());
    }

    public function testNormalizeUserIdAcceptsPositiveInt(): void
    {
        $this->assertSame(5, normalizeUserId(5));
    }

    public function testNormalizeUserIdAcceptsNumericString(): void
    {
        $this->assertSame(5, normalizeUserId('5'));
    }

    public function testNormalizeUserIdRejectsZeroAndNegative(): void
    {
        $this->assertNull(normalizeUserId(0));
        $this->assertNull(normalizeUserId(-1));
    }

    public function testNormalizeUserIdRejectsNonNumericInput(): void
    {
        $this->assertNull(normalizeUserId('abc'));
        $this->assertNull(normalizeUserId(null));
    }

    public function testEEscapesHtmlSpecialChars(): void
    {
        $this->assertSame('&lt;script&gt;', e('<script>'));
    }

    public function testCsrfTokenIsStableWithinSameSession(): void
    {
        $token = csrfToken();

        $this->assertSame($token, csrfToken());
    }

    public function testVerifyCsrfTokenAcceptsMatchingToken(): void
    {
        $token = csrfToken();

        $this->assertTrue(verifyCsrfToken($token));
    }

    public function testVerifyCsrfTokenRejectsWrongToken(): void
    {
        csrfToken();

        $this->assertFalse(verifyCsrfToken('wrong-token'));
    }

    public function testVerifyCsrfTokenRejectsEmptyOrMissingToken(): void
    {
        csrfToken();

        $this->assertFalse(verifyCsrfToken(''));
        $this->assertFalse(verifyCsrfToken(null));
    }

    public function testRegenerateSessionChangesIdAndClearsCsrfToken(): void
    {
        ensureSessionStarted();
        $oldId = session_id();
        csrfToken();

        regenerateSession();

        $this->assertNotSame($oldId, session_id());
        $this->assertArrayNotHasKey('csrf_token', $_SESSION);
    }

    public function testHomePathForRoleReturnsPathForEachRole(): void
    {
        $this->assertSame('/account', homePathForRole('customer'));
        $this->assertSame('/specialist', homePathForRole('specialist'));
        $this->assertSame('/admin', homePathForRole('shift_admin'));
        $this->assertSame('/admin', homePathForRole('content_editor'));
        $this->assertSame('/admin', homePathForRole('owner'));
    }

    public function testHomePathForRoleFallsBackToHomeForUnknownRole(): void
    {
        $this->assertSame('/', homePathForRole('unknown'));
    }

    public function testAdminRoleLabelReturnsLabelForEachStaffRole(): void
    {
        $this->assertSame('Специалист', adminRoleLabel('specialist'));
        $this->assertSame('Администратор смены', adminRoleLabel('shift_admin'));
        $this->assertSame('Контент-редактор', adminRoleLabel('content_editor'));
        $this->assertSame('Владелец', adminRoleLabel('owner'));
    }

    public function testAdminRoleLabelFallsBackForUnknownRole(): void
    {
        $this->assertSame('Персонал', adminRoleLabel('customer'));
        $this->assertSame('Персонал', adminRoleLabel('unknown'));
    }

    public function testRequireRoleAllowsMatchingRole(): void
    {
        $_SESSION['user_id']   = 1;
        $_SESSION['user_role'] = 'owner';

        // Не должно перенаправлять/завершать выполнение — роль разрешена.
        requireRole('shift_admin', 'owner');

        $this->assertTrue(true);
    }

    public function testGeneratePasswordDefaultLength(): void
    {
        $this->assertSame(12, strlen(generatePassword()));
    }

    public function testGeneratePasswordRespectsCustomLength(): void
    {
        $this->assertSame(20, strlen(generatePassword(20)));
    }

    public function testGeneratePasswordUsesOnlyUnambiguousAlnumChars(): void
    {
        $password = generatePassword(64);

        $this->assertMatchesRegularExpression(
            '/^[A-HJ-NP-Za-km-z2-9]+$/',
            $password
        );
    }

    public function testGeneratePasswordIsRandomBetweenCalls(): void
    {
        $this->assertNotSame(generatePassword(), generatePassword());
    }
}
