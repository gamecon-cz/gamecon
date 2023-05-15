<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Uzivatel;

use Gamecon\Shop\TypPredmetu;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Uzivatel\Finance;

class FinanceCenaPredmetuTest extends AbstractTestDb
{
    protected static array $initQueries = [
        <<<SQL
INSERT INTO uzivatele_hodnoty SET id_uzivatele = 333, login_uzivatele = 'LeiNo', jmeno_uzivatele = 'Lei', prijmeni_uzivatele = 'No', email1_uzivatele = 'lei.no@bio.org'
SQL,
        [
            <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 33311, nazev = 'nějaký předmět', model_rok = $0, cena_aktualni = 123, stav = 1, auto = 0, nabizet_do = NOW(), kusu_vyrobeno = 100, typ = $1
SQL,
            [
                0 => ROCNIK,
                1 => TypPredmetu::PREDMET,
            ],
        ],
        [
            <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 33312, nazev = 'další předmět', model_rok = $0, cena_aktualni = 234, stav = 1, auto = 0, nabizet_do = NOW(), kusu_vyrobeno = 100, typ = $1
SQL,
            [
                0 => ROCNIK,
                1 => TypPredmetu::PREDMET,
            ],
        ],
        [
            <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 33313, nazev = 'nějaké ubytování', model_rok = $0, cena_aktualni = 345, stav = 1, auto = 0, nabizet_do = NOW(), kusu_vyrobeno = 100, typ = $1
SQL,
            [
                0 => ROCNIK,
                1 => TypPredmetu::UBYTOVANI,
            ],
        ],
        [
            <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 33314, nazev = 'další ubytování', model_rok = $0, cena_aktualni = 456, stav = 1, auto = 0, nabizet_do = NOW(), kusu_vyrobeno = 100, typ = $1
SQL,
            [
                0 => ROCNIK,
                1 => TypPredmetu::UBYTOVANI,
            ],
        ],
        [
            <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 33315, nazev = 'nějaké jídlo', model_rok = $0, cena_aktualni = 567, stav = 1, auto = 0, nabizet_do = NOW(), kusu_vyrobeno = 100, typ = $1
SQL,
            [
                0 => ROCNIK,
                1 => TypPredmetu::JIDLO,
            ],
        ],
        [
            <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 33316, nazev = 'další jídlo', model_rok = $0, cena_aktualni = 567, stav = 1, auto = 0, nabizet_do = NOW(), kusu_vyrobeno = 100, typ = $1
SQL,
            [
                0 => ROCNIK,
                1 => TypPredmetu::JIDLO,
            ],
        ],
        [
            <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 33317, nazev = 'nějaké tričko', model_rok = $0, cena_aktualni = 678, stav = 1, auto = 0, nabizet_do = NOW(), kusu_vyrobeno = 100, typ = $1
SQL,
            [
                0 => ROCNIK,
                1 => TypPredmetu::TRICKO,
            ],
        ],
        [
            <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 33318, nazev = 'další tričko', model_rok = $0, cena_aktualni = 890, stav = 1, auto = 0, nabizet_do = NOW(), kusu_vyrobeno = 100, typ = $1
SQL,
            [
                0 => ROCNIK,
                1 => TypPredmetu::TRICKO,
            ],
        ],
        [
            <<<SQL
INSERT INTO shop_nakupy(id_uzivatele, id_predmetu, rok, cena_nakupni)
SELECT 333, id_predmetu, $0, (id_predmetu / 100) AS cena_nakupni FROM shop_predmety WHERE id_predmetu BETWEEN 33311 AND 33318
SQL,
            [0 => ROCNIK],
        ],
    ];

    /**
     * @test
     */
    public function Cena_nakupu_odpovida() {
        $cenaPredmetuBezTricek = 333.11 + 333.12;
        $cenaTricek            = 333.17 + 333.18;
        $cenavsechPredmetu     = $cenaPredmetuBezTricek + $cenaTricek;
        define('MODRE_TRICKO_ZDARMA_OD', 0);
        $finance = new Finance($this->dejUzivateleSNakupy(), 0, SystemoveNastaveni::vytvorZGlobals());
        self::assertSame(
            round($cenavsechPredmetu, 2),
            round($finance->cenaPredmetu(), 2)
        );
        self::assertSame(
            round(333.15 + 333.16, 2),
            round($finance->cenaStravy(), 2)
        );
        self::assertSame(
            round($cenavsechPredmetu + 333.15 + 333.16, 2),
            round($finance->cenaPredmetyAStrava(), 2)
        );
        self::assertSame(
            round(333.13 + 333.140, 2),
            round($finance->cenaUbytovani(), 2)
        );
    }

    private function dejUzivateleSNakupy(): \Uzivatel {
        return \Uzivatel::zIdUrcite(333);
    }

}
