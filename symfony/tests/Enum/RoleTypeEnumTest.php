<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Enum\RoleTypeEnum;
use Gamecon\Role\Role;
use PHPUnit\Framework\TestCase;

class RoleTypeEnumTest extends TestCase
{
    /**
     * The enum replaces the legacy constants in raw SQL as well, where a changed value would
     * silently match nothing.
     */
    public function testCasesCarryTheLegacyValues(): void
    {
        $this->assertSame(Role::TYP_ROCNIKOVA, RoleTypeEnum::YEARLY->value);
        $this->assertSame(Role::TYP_UCAST, RoleTypeEnum::PARTICIPATION->value);
        $this->assertSame(Role::TYP_TRVALA, RoleTypeEnum::PERMANENT->value);
    }
}
