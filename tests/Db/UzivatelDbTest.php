<?php

declare(strict_types=1);

namespace Gamecon\Tests\Db;

abstract class UzivatelDbTest extends DbTest
{
    /**
     * @return \Uzivatel vrátí nového testovacího uživatele přihlášeného na GC
     */
    public static function prihlasenyUzivatel(): \Uzivatel {
        $cislo = self::unikatniCislo();
        dbInsert('uzivatele_hodnoty', [
            'login_uzivatele'  => 'test_' . $cislo,
            'email1_uzivatele' => 'godric.cz+gc_test_' . $cislo . '@gmail.com',
        ]);
        $uid = dbInsertId();
        dbInsert('r_uzivatele_zidle', [
            'id_uzivatele' => $uid,
            'id_zidle'     => ZIDLE_PRIHLASEN,
        ]);
        return \Uzivatel::zId($uid);
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
