<?php

declare(strict_types=1);

namespace Gamecon\Tests\Db\Migrace;

use Gamecon\Role\Role;
use Gamecon\Tests\Db\AbstractTestDb;
use Godric\DbMigrations\Migration;

/**
 * The endless migrations take the year from `rocnik_z_promenne_mysql()`, so a test can run them for a year other
 * than ROCNIK. The roles they create have to belong to that one year, in their id as well as in their name and `rocnik_role`.
 */
class LetosniRoleEndlessMigraceTest extends AbstractTestDb
{
    private const int JINY_ROCNIK = 2092;

    /**
     * @test
     */
    public function chybejiciRocnikoveRoleSeZalozi(): void
    {
        $role = Role::vsechnyRocnikoveRole();
        $this->smazRole(array_keys($role));

        $this->spustMigraci('9999_01-letosni-role-krome-ucasti-endless.php');

        self::assertEquals($role, $this->getNazvyRoli(array_keys($role)));
        self::assertEquals(array_fill_keys(array_keys($role), ROCNIK), $this->getRocnikyRoli(array_keys($role)));
    }

    /**
     * @test
     */
    public function chybejiciRoleUcastiSeZalozi(): void
    {
        $role = Role::vsechnyRoleUcastiProRocnik();
        $this->smazRole(array_keys($role));

        $this->spustMigraci('9999_02-letosni-role-ucasti-endless.php');

        self::assertEquals($role, $this->getNazvyRoli(array_keys($role)));
        self::assertEquals(array_fill_keys(array_keys($role), ROCNIK), $this->getRocnikyRoli(array_keys($role)));
    }

    /**
     * @test
     */
    public function roleUcastiSeZakladajiProRocnikZPromenneMysql(): void
    {
        $role = Role::vsechnyRoleUcastiProRocnik(self::JINY_ROCNIK);

        $this->spustMigraci('9999_02-letosni-role-ucasti-endless.php', self::JINY_ROCNIK);

        self::assertEquals($role, $this->getNazvyRoli(array_keys($role)));
        self::assertEquals(array_fill_keys(array_keys($role), self::JINY_ROCNIK), $this->getRocnikyRoli(array_keys($role)));
    }

    /**
     * @test
     */
    public function idRoliUcastiSeLisiPodleRocniku(): void
    {
        self::assertNotEquals(
            array_keys(Role::vsechnyRoleUcastiProRocnik(ROCNIK)),
            array_keys(Role::vsechnyRoleUcastiProRocnik(self::JINY_ROCNIK)),
        );
    }

    private function spustMigraci(string $souborMigrace, ?int $rocnikZPromenne = null): void
    {
        $puvodniRocnik = dbFetchSingle('SELECT @rocnik');
        if ($rocnikZPromenne !== null) {
            dbQuery('SET @rocnik = $0', [
                0 => $rocnikZPromenne,
            ]);
        }
        try {
            (new Migration(__DIR__ . '/../../../migrace/' . $souborMigrace, $souborMigrace, dbConnect()))->apply();
        } finally {
            dbQuery('SET @rocnik = $0', [
                0 => $puvodniRocnik,
            ]);
        }
    }

    /**
     * @param list<int> $idRoli
     */
    private function smazRole(array $idRoli): void
    {
        dbQuery('DELETE FROM prava_role WHERE id_role IN ($0)', [
            0 => $idRoli,
        ]);
        dbQuery('DELETE FROM uzivatele_role WHERE id_role IN ($0)', [
            0 => $idRoli,
        ]);
        dbQuery('DELETE FROM role_seznam WHERE id_role IN ($0)', [
            0 => $idRoli,
        ]);
    }

    /**
     * @param list<int> $idRoli
     *
     * @return array<int, string> role id => role name
     */
    private function getNazvyRoli(array $idRoli): array
    {
        return dbFetchPairs('SELECT id_role, nazev_role FROM role_seznam WHERE id_role IN ($0)', [
            0 => $idRoli,
        ]);
    }

    /**
     * @param list<int> $idRoli
     *
     * @return array<int, int> role id => rocnik_role
     */
    private function getRocnikyRoli(array $idRoli): array
    {
        $rocniky = dbFetchPairs('SELECT id_role, rocnik_role FROM role_seznam WHERE id_role IN ($0)', [
            0 => $idRoli,
        ]);

        return array_map('intval', $rocniky);
    }
}
