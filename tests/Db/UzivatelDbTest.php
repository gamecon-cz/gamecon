<?php

declare(strict_types=1);

namespace Gamecon\Tests\Db;

use Gamecon\Pravo;
use Gamecon\Zidle;

abstract class UzivatelDbTest extends DbTest
{
    /**
     * @return \Uzivatel vrátí nového testovacího uživatele přihlášeného na GC
     */
    public static function prihlasenyUzivatel(): \Uzivatel {
        static::zajistiZidliAPravoKPrihlaseniNaLetosniGc();

        $cislo = self::unikatniCislo();
        dbInsert('uzivatele_hodnoty', [
            'login_uzivatele'  => 'test_' . $cislo,
            'email1_uzivatele' => 'godric.cz+gc_test_' . $cislo . '@gmail.com',
        ]);
        $idUzivatele = dbInsertId();
        dbInsert('r_uzivatele_zidle', [
            'id_uzivatele' => $idUzivatele,
            'id_zidle'     => ZIDLE_PRIHLASEN,
        ]);
        $uzivatel = \Uzivatel::zId($idUzivatele);
        self::assertNotNull($uzivatel);
        self::assertTrue($uzivatel->gcPrihlasen(), 'Testovací uživatel "přihlášený na GC" není přihlášený');

        return $uzivatel;
    }

    protected static function zajistiZidliAPravoKPrihlaseniNaLetosniGc() {
        dbInsertIgnore('r_zidle_soupis', [
            'id_zidle'    => ZIDLE_PRIHLASEN,
            'jmeno_zidle' => 'Jakoby přihlášení pro ' . ROK,
            'popis_zidle' => 'Pojistka po překlopení ročníku kdy židle přihlášen ještě není',
        ]);
        self::assertNotNull(
            Zidle::zId(ZIDLE_PRIHLASEN),
            sprintf(
                "Židle 'Přihlášen pro rok %d' s ID %d není uložená - debugni to uložením přes INSERT bez IGNORE",
                ROK,
                ZIDLE_PRIHLASEN
            )
        );
        dbInsertIgnore('r_prava_soupis', [
            'id_prava'    => ID_PRAVO_PRIHLASEN,
            'jmeno_prava' => 'Jakoby přihlášen pro ' . ROK,
            'popis_prava' => 'Pojistka po překlopení ročníku kdy právo přihlášen ještě není',
        ]);
        self::assertNotNull(
            Pravo::zId(ID_PRAVO_PRIHLASEN),
            sprintf(
                "Právo 'Přihlášen pro rok %d' s ID %d není uložené - debugni to uložením přes INSERT bez IGNORE",
                ROK,
                ID_PRAVO_PRIHLASEN
            )
        );
        dbInsertIgnore('r_prava_zidle', [
            'id_zidle' => ZIDLE_PRIHLASEN,
            'id_prava' => ID_PRAVO_PRIHLASEN,
        ]);
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
