<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StaffPermissionsTest extends TestCase
{
    /** @return array<string, array{string, list<string>}> */
    public static function creatableRoles(): array
    {
        return [
            'владелец'            => ['owner', ['specialist', 'shift_admin', 'content_editor']],
            'администратор смены' => ['shift_admin', ['specialist', 'shift_admin']],
            'специалист'          => ['specialist', []],
            'фрилансер'           => ['content_editor', []],
            'покупатель'          => ['customer', []],
            'неизвестная роль'    => ['hacker', []],
        ];
    }

    /** @param list<string> $expected */
    #[DataProvider('creatableRoles')]
    public function testStaffRolesCreatableBy(string $actorRole, array $expected): void
    {
        $this->assertSame($expected, staffRolesCreatableBy($actorRole));
    }

    public function testNobodyCreatesOwner(): void
    {
        foreach (['owner', 'shift_admin'] as $actorRole) {
            $this->assertNotContains('owner', staffRolesCreatableBy($actorRole));
            $this->assertNotContains('customer', staffRolesCreatableBy($actorRole));
        }
    }

    /** @return array<string, array{string, int, int, string, bool}> */
    public static function manageCases(): array
    {
        return [
            'владелец — Специалист'       => ['owner', 1, 2, 'specialist', true],
            'владелец — Администратор'    => ['owner', 1, 2, 'shift_admin', true],
            'владелец — Фрилансер'        => ['owner', 1, 2, 'content_editor', true],
            'владелец — сам себя'         => ['owner', 1, 1, 'owner', false],
            'владелец — другой Владелец'  => ['owner', 1, 2, 'owner', false],
            'владелец — Покупатель'       => ['owner', 1, 2, 'customer', false],
            'администратор смены'         => ['shift_admin', 1, 2, 'specialist', false],
            'специалист'                  => ['specialist', 1, 2, 'specialist', false],
            'фрилансер'                   => ['content_editor', 1, 2, 'specialist', false],
        ];
    }

    #[DataProvider('manageCases')]
    public function testCanManageStaff(string $actorRole, int $actorId, int $targetId, string $targetRole, bool $expected): void
    {
        $this->assertSame($expected, canManageStaff($actorRole, $actorId, $targetId, $targetRole));
    }
}
