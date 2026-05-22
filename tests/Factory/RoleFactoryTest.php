<?php

declare(strict_types=1);

namespace Gamecon\Tests\Factory;

use Gamecon\Role\Role as LegacyRole;
use Gamecon\Tests\Db\AbstractTestDb;

/**
 * Regression coverage for the flake we hit on
 * {@see \Gamecon\Tests\Symfony\EntityLegacyComparisonTest::testRoleEntityMatchesLegacyRole}:
 * the factory used to roll random ids in 1..5000, which can collide
 * with seeded role_seznam rows (CESTNY_ORGANIZATOR = 15, CFO = 20,
 * the rest of the 20..30 range, etc.).
 *
 * This test pins the contract — the default id must always sit safely
 * above any seeded row. Bumping the range later is fine; shrinking
 * it back into the seeded space would re-introduce the flake and
 * this test would catch it deterministically.
 */
class RoleFactoryTest extends AbstractTestDb
{
    protected static bool $disableStrictTransTables = true;

    protected static function keepTestClassDbChangesInTransaction(): bool
    {
        return true;
    }

    /**
     * @test
     */
    public function defaultIdNeverCollidesWithSeededRoleRows(): void
    {
        // The lowest id the factory may pick. Must be strictly greater
        // than every real role_seznam row known today:
        //   - positive trvala/rocnikova roles: 2..30
        //   - negative rocnikova roles: -902..-202400029 (negative,
        //     can't collide with a positive default)
        // Sliding window: any future hand-curated role id we add via
        // a migration goes near the top of the small positive range.
        // 10000 leaves five orders of magnitude of headroom.
        $minSafeId = 10_000;

        // 50 draws is a cheap way to exercise the random distribution.
        // With the buggy 1..5000 default, ~99% of draws fall under
        // 10000, so this test would fail loudly on the first draw on
        // virtually every seed. With the fixed range it always passes.
        for ($i = 0; $i < 50; ++$i) {
            $role = RoleFactory::createOne()->_save()->_real();
            $id = $role->getId();
            self::assertNotNull($id);
            self::assertGreaterThanOrEqual(
                $minSafeId,
                $id,
                "RoleFactory rolled id {$id} which can collide with seeded role_seznam rows",
            );
            // Sanity check that the value comes back in the expected ballpark.
            self::assertLessThan(1_000_000, $id);
        }

        // Spot-check that LegacyRole::CESTNY_ORGANIZATOR (15) — the
        // historical canary for this flake — is below our safe floor,
        // so this test will always catch a regression that re-opens
        // the collision window.
        self::assertLessThan($minSafeId, LegacyRole::CESTNY_ORGANIZATOR);
    }
}
