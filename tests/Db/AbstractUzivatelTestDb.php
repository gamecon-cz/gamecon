<?php

declare(strict_types=1);

namespace Gamecon\Tests\Db;

use Gamecon\Role\Role;
use App\Structure\Sql\UserSqlStructure as UserSql;
use App\Structure\Sql\UserRoleSqlStructure as UserRoleSql;
use Gamecon\Uzivatel\Pohlavi;

abstract class AbstractUzivatelTestDb extends AbstractTestDb
{
    /**
     * @return \Uzivatel vrátí nového testovacího uživatele přihlášeného na GC
     */
    public static function prihlasenyUzivatel(): \Uzivatel {
        static::zkontrolujRoliProPrihlaseniNaLetosniGc();

        $cislo = self::unikatniCislo();
        dbInsert(UserSql::_table, [
            UserSql::login_uzivatele  => 'test_' . $cislo,
            UserSql::email1_uzivatele => 'godric.cz+gc_test_' . $cislo . '@gmail.com',
            UserSql::pohlavi => Pohlavi::MUZ_KOD,
        ]);
        $idUzivatele = dbInsertId();
        dbInsert(UserRoleSql::_table, [
            UserRoleSql::id_uzivatele => $idUzivatele,
            UserRoleSql::id_role      => Role::PRIHLASEN_NA_LETOSNI_GC,
        ]);
        $uzivatel = \Uzivatel::zId($idUzivatele);
        self::assertNotNull($uzivatel);
        self::assertTrue($uzivatel->gcPrihlasen(), 'Testovací uživatel "přihlášený na GC" není přihlášený');

        return $uzivatel;
    }

    protected static function zkontrolujRoliProPrihlaseniNaLetosniGc() {
        self::assertNotNull(
            Role::zId(Role::PRIHLASEN_NA_LETOSNI_GC),
            sprintf(
                "Chybí role 'Přihlášen pro rok %d' s ID %d",
                ROCNIK,
                Role::PRIHLASEN_NA_LETOSNI_GC
            )
        );
    }

    private static function unikatniCislo(): int {
        static $pouzitaCisla = [];
        do {
            $cislo = rand(1000, 9999);
        } while (in_array($cislo, $pouzitaCisla));
        $pouzitaCisla[] = $cislo;
        return $cislo;
    }
}
