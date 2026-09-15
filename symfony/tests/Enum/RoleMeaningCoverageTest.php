<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Enum\RoleMeaning;
use App\Tests\AbstractDatabaseKernelTestCase;

/**
 * Role::getVyznamRole() is typed as RoleMeaning and hydrated by Doctrine, so a value
 * present in role_seznam but missing from the enum is not a gap — it is a ValueError
 * the moment anything loads a user holding that role. Four values were missing when
 * this test was written, affecting twelve users.
 *
 * Roles are created through the admin UI, so the enum drifts on its own unless
 * something checks.
 */
class RoleMeaningCoverageTest extends AbstractDatabaseKernelTestCase
{
    public function testEveryRoleMeaningInDatabaseHasACase(): void
    {
        $storedMeanings = $this->connection()->fetchFirstColumn(
            "SELECT DISTINCT vyznam_role FROM role_seznam
             WHERE vyznam_role IS NOT NULL AND vyznam_role != ''",
        );
        $this->assertNotEmpty($storedMeanings, 'No role meanings in the database — the check would pass vacuously');

        $missing = array_filter(
            $storedMeanings,
            static fn (string $meaning): bool => RoleMeaning::tryFrom($meaning) === null,
        );

        $this->assertSame(
            [],
            array_values($missing),
            'role_seznam.vyznam_role values with no RoleMeaning case. Loading a user with such a '
            . 'role throws a ValueError, so add the missing case(s) — and decide whether each one '
            . 'belongs in organizerMeanings().',
        );
    }
}
