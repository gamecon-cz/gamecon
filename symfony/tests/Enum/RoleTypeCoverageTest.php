<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Enum\RoleTypeEnum;
use App\Tests\AbstractDatabaseKernelTestCase;

/**
 * Role::getTypRole() is typed as RoleTypeEnum and hydrated by Doctrine, so a value present in
 * role_seznam but missing from the enum is a ValueError the moment anything loads that role.
 */
class RoleTypeCoverageTest extends AbstractDatabaseKernelTestCase
{
    public function testEveryRoleTypeInDatabaseHasACase(): void
    {
        // BINARY, because the column collation ignores case and accents, so DISTINCT alone would
        // fold a stored 'Ucast' into 'ucast' while the enum would refuse it.
        $storedTypes = $this->connection()->fetchFirstColumn('SELECT DISTINCT BINARY typ_role FROM role_seznam');
        $this->assertNotEmpty($storedTypes, 'No roles in the database, the check would pass vacuously');

        $missing = array_filter(
            $storedTypes,
            static fn (string $type): bool => RoleTypeEnum::tryFrom($type) === null,
        );

        $this->assertSame(
            [],
            array_values($missing),
            'role_seznam.typ_role values with no RoleTypeEnum case. Loading a role of such a type throws a ValueError.',
        );
    }
}
