<?php

declare(strict_types=1);

namespace Gamecon\Tests\Db;

use Gamecon\Role\Role;

abstract class UzivatelDbTest extends AbstractDbTest
{
    /**isEndless
     * @return \Uzivatel vrátí nového testovacího uživatele přihlášeného na GC
     */
    public static function prihlasenyUzivatel(): \Uzivatel {
        static::zkontrolujRoliKPrihlaseniNaLetosniGc();

        $cislo = self::unikatniCislo();
        dbInsert('uzivatele_hodnoty', [
            'login_uzivatele'  => 'test_' . $cislo,
            'email1_uzivatele' => 'godric.cz+gc_test_' . $cislo . '@gmail.com',
        ]);
        $idUzivatele = dbInsertId();
        dbInsert('uzivatele_role', [
            'id_uzivatele' => $idUzivatele,
            'id_role'     => Role::PRIHLASEN_NA_LETOSNI_GC,
        ]);
        $uzivatel = \Uzivatel::zId($idUzivatele);
        self::assertNotNull($uzivatel);
        self::assertTrue($uzivatel->gcPrihlasen(), 'Testovací uživatel "přihlášený na GC" není přihlášený');

        return $uzivatel;
    }

    protected static function zkontrolujRoliKPrihlaseniNaLetosniGc() {
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
