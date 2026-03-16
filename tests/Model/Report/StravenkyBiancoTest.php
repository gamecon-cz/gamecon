<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Report;

use Gamecon\Cas\DateTimeGamecon;
use Gamecon\Shop\TypPredmetu;
use Gamecon\Tests\Db\AbstractTestDb;

class StravenkyBiancoTest extends AbstractTestDb
{
    protected static array $initQueries = [
        [
            <<<SQL
INSERT INTO shop_predmety SET nazev = 'Snídaně čtvrtek', model_rok = $0, kod_predmetu = CONCAT('snidane_ct_test_', $0), cena_aktualni = 90, stav = 1, typ = $1, ubytovani_den = $2
SQL,
            [
                0 => ROCNIK,
                1 => TypPredmetu::JIDLO,
                2 => DateTimeGamecon::PORADI_HERNIHO_DNE_CTVRTEK,
            ],
        ],
        [
            <<<SQL
INSERT INTO shop_predmety SET nazev = 'Oběd čtvrtek', model_rok = $0, kod_predmetu = CONCAT('obed_ct_test_', $0), cena_aktualni = 90, stav = 1, typ = $1, ubytovani_den = $2
SQL,
            [
                0 => ROCNIK,
                1 => TypPredmetu::JIDLO,
                2 => DateTimeGamecon::PORADI_HERNIHO_DNE_CTVRTEK,
            ],
        ],
        [
            <<<SQL
INSERT INTO shop_predmety SET nazev = 'Večeře čtvrtek', model_rok = $0, kod_predmetu = CONCAT('vecere_ct_test_', $0), cena_aktualni = 90, stav = 1, typ = $1, ubytovani_den = $2
SQL,
            [
                0 => ROCNIK,
                1 => TypPredmetu::JIDLO,
                2 => DateTimeGamecon::PORADI_HERNIHO_DNE_CTVRTEK,
            ],
        ],
        [
            <<<SQL
INSERT INTO shop_predmety SET nazev = 'Snídaně pátek', model_rok = $0, kod_predmetu = CONCAT('snidane_pa_test_', $0), cena_aktualni = 90, stav = 1, typ = $1, ubytovani_den = $2
SQL,
            [
                0 => ROCNIK,
                1 => TypPredmetu::JIDLO,
                2 => DateTimeGamecon::PORADI_HERNIHO_DNE_PATEK,
            ],
        ],
        [
            <<<SQL
INSERT INTO shop_predmety SET nazev = 'Oběd pátek', model_rok = $0, kod_predmetu = CONCAT('obed_pa_test_', $0), cena_aktualni = 90, stav = 1, typ = $1, ubytovani_den = $2
SQL,
            [
                0 => ROCNIK,
                1 => TypPredmetu::JIDLO,
                2 => DateTimeGamecon::PORADI_HERNIHO_DNE_PATEK,
            ],
        ],
        [
            <<<SQL
INSERT INTO shop_predmety SET nazev = 'Večeře pátek', model_rok = $0, kod_predmetu = CONCAT('vecere_pa_test_', $0), cena_aktualni = 90, stav = 1, typ = $1, ubytovani_den = $2
SQL,
            [
                0 => ROCNIK,
                1 => TypPredmetu::JIDLO,
                2 => DateTimeGamecon::PORADI_HERNIHO_DNE_PATEK,
            ],
        ],
        [
            <<<SQL
INSERT INTO shop_predmety SET nazev = 'Snídaně sobota', model_rok = $0, kod_predmetu = CONCAT('snidane_so_test_', $0), cena_aktualni = 90, stav = 1, typ = $1, ubytovani_den = $2
SQL,
            [
                0 => ROCNIK,
                1 => TypPredmetu::JIDLO,
                2 => DateTimeGamecon::PORADI_HERNIHO_DNE_SOBOTA,
            ],
        ],
        [
            <<<SQL
INSERT INTO shop_predmety SET nazev = 'Oběd sobota', model_rok = $0, kod_predmetu = CONCAT('obed_so_test_', $0), cena_aktualni = 90, stav = 1, typ = $1, ubytovani_den = $2
SQL,
            [
                0 => ROCNIK,
                1 => TypPredmetu::JIDLO,
                2 => DateTimeGamecon::PORADI_HERNIHO_DNE_SOBOTA,
            ],
        ],
        [
            <<<SQL
INSERT INTO shop_predmety SET nazev = 'Večeře sobota', model_rok = $0, kod_predmetu = CONCAT('vecere_so_test_', $0), cena_aktualni = 90, stav = 1, typ = $1, ubytovani_den = $2
SQL,
            [
                0 => ROCNIK,
                1 => TypPredmetu::JIDLO,
                2 => DateTimeGamecon::PORADI_HERNIHO_DNE_SOBOTA,
            ],
        ],
        [
            <<<SQL
INSERT INTO shop_predmety SET nazev = 'Snídaně neděle', model_rok = $0, kod_predmetu = CONCAT('snidane_ne_test_', $0), cena_aktualni = 90, stav = 1, typ = $1, ubytovani_den = $2
SQL,
            [
                0 => ROCNIK,
                1 => TypPredmetu::JIDLO,
                2 => DateTimeGamecon::PORADI_HERNIHO_DNE_NEDELE,
            ],
        ],
        [
            <<<SQL
INSERT INTO shop_predmety SET nazev = 'Oběd neděle', model_rok = $0, kod_predmetu = CONCAT('obed_ne_test_', $0), cena_aktualni = 90, stav = 1, typ = $1, ubytovani_den = $2
SQL,
            [
                0 => ROCNIK,
                1 => TypPredmetu::JIDLO,
                2 => DateTimeGamecon::PORADI_HERNIHO_DNE_NEDELE,
            ],
        ],
        [
            <<<SQL
INSERT INTO shop_predmety SET nazev = 'Večeře neděle', model_rok = $0, kod_predmetu = CONCAT('vecere_ne_test_', $0), cena_aktualni = 90, stav = 1, typ = $1, ubytovani_den = $2
SQL,
            [
                0 => ROCNIK,
                1 => TypPredmetu::JIDLO,
                2 => DateTimeGamecon::PORADI_HERNIHO_DNE_NEDELE,
            ],
        ],
        // středa meal should be excluded
        [
            <<<SQL
INSERT INTO shop_predmety SET nazev = 'Oběd středa', model_rok = $0, kod_predmetu = CONCAT('obed_st_test_', $0), cena_aktualni = 90, stav = 1, typ = $1, ubytovani_den = $2
SQL,
            [
                0 => ROCNIK,
                1 => TypPredmetu::JIDLO,
                2 => DateTimeGamecon::PORADI_HERNIHO_DNE_STREDA,
            ],
        ],
    ];

    /**
     * @test
     */
    public function biancoStravenkyObsahujiJidlaOdCtvrtka()
    {
        $rocnik = ROCNIK;
        $typJidlo = TypPredmetu::JIDLO;
        $prvniDen = DateTimeGamecon::PORADI_HERNIHO_DNE_CTVRTEK;

        $o = dbQuery(<<<SQL
            SELECT
              shop_predmety.nazev,
              FIELD(SUBSTRING(TRIM(shop_predmety.nazev), POSITION(' ' IN TRIM(shop_predmety.nazev)) + 1), 'středa', 'čtvrtek', 'pátek', 'sobota', 'neděle') AS poradi_dne,
              FIELD(SUBSTRING(TRIM(shop_predmety.nazev), 1, POSITION(' ' IN TRIM(shop_predmety.nazev)) - 1), 'Snídaně', 'Oběd', 'Večeře') AS poradi_jidla
            FROM shop_predmety
            WHERE shop_predmety.model_rok = {$rocnik}
              AND shop_predmety.typ = {$typJidlo}
              AND shop_predmety.ubytovani_den >= {$prvniDen}
            ORDER BY poradi_dne DESC, poradi_jidla DESC
SQL,
        );

        $jidla = [];
        while ($r = mysqli_fetch_assoc($o)) {
            $jidla[] = $r;
        }

        self::assertCount(12, $jidla, 'Mělo by být 12 jídel (3 za den × 4 dny čtvrtek–neděle)');

        $nazvy = array_column($jidla, 'nazev');
        self::assertNotContains('Oběd středa', $nazvy, 'Středeční jídla by měla být vyřazena');

        self::assertContains('Snídaně čtvrtek', $nazvy);
        self::assertContains('Večeře neděle', $nazvy);

        // verify ordering: first item should be neděle (poradi_dne=5), last čtvrtek (poradi_dne=2)
        self::assertStringContainsString('neděle', $jidla[0]['nazev']);
        self::assertStringContainsString('čtvrtek', $jidla[count($jidla) - 1]['nazev']);
    }

    /**
     * @test
     */
    public function biancoStravenkyVyplni24Bunek()
    {
        $rocnik = ROCNIK;
        $typJidlo = TypPredmetu::JIDLO;
        $prvniDen = DateTimeGamecon::PORADI_HERNIHO_DNE_CTVRTEK;

        $o = dbQuery(<<<SQL
            SELECT
              shop_predmety.nazev,
              FIELD(SUBSTRING(TRIM(shop_predmety.nazev), POSITION(' ' IN TRIM(shop_predmety.nazev)) + 1), 'středa', 'čtvrtek', 'pátek', 'sobota', 'neděle') AS poradi_dne,
              FIELD(SUBSTRING(TRIM(shop_predmety.nazev), 1, POSITION(' ' IN TRIM(shop_predmety.nazev)) - 1), 'Snídaně', 'Oběd', 'Večeře') AS poradi_jidla
            FROM shop_predmety
            WHERE shop_predmety.model_rok = {$rocnik}
              AND shop_predmety.typ = {$typJidlo}
              AND shop_predmety.ubytovani_den >= {$prvniDen}
            ORDER BY poradi_dne DESC, poradi_jidla DESC
SQL,
        );

        $jidla = [];
        while ($r = mysqli_fetch_assoc($o)) {
            $jidla[] = $r;
        }

        $pocetJidel = count($jidla);
        $pocetBunek = 24;
        $pocetOpakovani = $pocetJidel > 0 ? (int) ceil($pocetBunek / $pocetJidel) : 0;

        $res = [];
        for ($i = 0; $i < $pocetOpakovani; ++$i) {
            foreach ($jidla as $jidlo) {
                $res[] = [
                    'id_uzivatele'    => (string) $i,
                    'login_uzivatele' => 'Bianco stravenka',
                    'nazev'           => $jidlo['nazev'],
                    'poradi_dne'      => $jidlo['poradi_dne'],
                    'poradi_jidla'    => $jidlo['poradi_jidla'],
                ];
            }
        }

        $res = array_slice($res, 0, $pocetBunek);

        self::assertCount(24, $res, 'Bianco stravenky by měly vyplnit přesně 24 buněk (3×8 stránka)');

        // first 12 have id_uzivatele=0, next 12 have id_uzivatele=1
        self::assertSame('0', $res[0]['id_uzivatele']);
        self::assertSame('1', $res[12]['id_uzivatele']);

        foreach ($res as $row) {
            self::assertSame('Bianco stravenka', $row['login_uzivatele']);
        }
    }
}
