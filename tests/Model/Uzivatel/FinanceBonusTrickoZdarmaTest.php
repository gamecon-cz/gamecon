<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Uzivatel;

use Gamecon\Cas\DateTimeImmutableStrict;
use Gamecon\Pravo;
use Gamecon\Shop\TypPredmetu;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Uzivatel\Finance;

/**
 * Bonus za vedení aktivit (právo {@see Pravo::MODRE_TRICKO_ZDARMA}) dává zdarma
 * libovolné tričko – vždy to nejlevnější v košíku, i když není modré.
 */
class FinanceBonusTrickoZdarmaTest extends AbstractTestDb
{
    private const ID_UZIVATELE = 334;
    private const ID_ROLE = -334334;

    protected static array $initQueries = [
        <<<SQL
INSERT INTO uzivatele_hodnoty SET id_uzivatele = 334, login_uzivatele = 'BonusTricko', jmeno_uzivatele = 'Bonus', prijmeni_uzivatele = 'Tricko', email1_uzivatele = 'bonus.tricko@bio.org'
SQL,
        // právo na tričko zdarma za bonus (id práva 1012)
        [
            <<<SQL
INSERT INTO role_seznam(id_role, kod_role, nazev_role, popis_role, rocnik_role, typ_role, vyznam_role)
VALUES ($0, 'TEST_BONUS_TRICKO', 'Test role bonus tričko', '', -1, 'trvala', '')
SQL,
            [
                0 => self::ID_ROLE,
            ],
        ],
        [
            <<<SQL
INSERT INTO prava_role(id_role, id_prava) VALUES ($0, $1)
SQL,
            [
                0 => self::ID_ROLE,
                1 => Pravo::MODRE_TRICKO_ZDARMA,
            ],
        ],
        [
            <<<SQL
INSERT INTO uzivatele_role(id_uzivatele, id_role, posadil) VALUES ($0, $1, $0)
SQL,
            [
                0 => self::ID_UZIVATELE,
                1 => self::ID_ROLE,
            ],
        ],
        // jeden předmět + dvě NEmodrá trička s různou cenou
        [
            <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 33420, nazev = 'nějaký předmět', model_rok = $0, kod_predmetu = CONCAT('bonus_predmet_', $0), cena_aktualni = 200, stav = 1, nabizet_do = NOW(), kusu_vyrobeno = 100, typ = $1
SQL,
            [
                0 => ROCNIK,
                1 => TypPredmetu::PREDMET,
            ],
        ],
        [
            <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 33421, nazev = 'zelené tričko', model_rok = $0, kod_predmetu = CONCAT('bonus_zelene_tricko_', $0), cena_aktualni = 300, stav = 1, nabizet_do = NOW(), kusu_vyrobeno = 100, typ = $1
SQL,
            [
                0 => ROCNIK,
                1 => TypPredmetu::TRICKO,
            ],
        ],
        [
            <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 33422, nazev = 'žluté tričko', model_rok = $0, kod_predmetu = CONCAT('bonus_zlute_tricko_', $0), cena_aktualni = 500, stav = 1, nabizet_do = NOW(), kusu_vyrobeno = 100, typ = $1
SQL,
            [
                0 => ROCNIK,
                1 => TypPredmetu::TRICKO,
            ],
        ],
        [
            <<<SQL
INSERT INTO shop_nakupy(id_uzivatele, id_predmetu, rok, cena_nakupni)
SELECT 334, id_predmetu, $0, cena_aktualni FROM shop_predmety WHERE id_predmetu BETWEEN 33420 AND 33422
SQL,
            [
                0 => ROCNIK,
            ],
        ],
        // Práh pro tričko zdarma (MODRE_TRICKO_ZDARMA_OD) je odvozený jako
        // 3× BONUS_ZA_STANDARDNI_3H_AZ_5H_AKTIVITU (viz definujOdvozeneKonstanty).
        // Vynulováním základu je práh 0, takže uživatel s nulovým bonusem na tričko
        // dosáhne. Nastavujeme to přes DB (per-test transakce), NE přes globální
        // konstantu MODRE_TRICKO_ZDARMA_OD – ta se v rámci jednoho PHP procesu dá
        // definovat jen jednou, takže by test byl pořadově závislý na cizích testech.
        <<<SQL
UPDATE systemove_nastaveni SET hodnota = '0' WHERE klic = 'BONUS_ZA_STANDARDNI_3H_AZ_5H_AKTIVITU'
SQL,
    ];

    /**
     * @test
     */
    public function bonusUplatniNejlevnejsiTrickoZdarma(): void
    {
        // Práh bonusu je 0 díky vynulování BONUS_ZA_STANDARDNI_3H_AZ_5H_AKTIVITU
        // v $initQueries, takže uživatel bez odvedených aktivit (bonus 0) na tričko dosáhne.
        // Čerstvá instance (ne globální singleton), aby se odvozený práh
        // MODRE_TRICKO_ZDARMA_OD spočítal z aktuálního (per-test) stavu DB, ne
        // z hodnoty zacachované jiným testem.
        $systemoveNastaveni = SystemoveNastaveni::zGlobals(ROCNIK, new DateTimeImmutableStrict());
        // Pojistka: kdyby jiný test v procesu přece jen definoval globální konstantu
        // MODRE_TRICKO_ZDARMA_OD, dejHodnotu() by ji upřednostnil před DB hodnotou a
        // test by tiše počítal s cizím prahem. Tady to spadne jasně a hned.
        self::assertSame(
            0.0,
            $systemoveNastaveni->modreTrickoZdarmaOd(),
            'Práh bonusu musí být 0 (nastaveno přes DB). Nedefinoval někdo konstantu MODRE_TRICKO_ZDARMA_OD?',
        );
        $finance = new Finance(\Uzivatel::zIdUrcite(self::ID_UZIVATELE), 0, $systemoveNastaveni);

        // předmět 200 + trička 300 + 500, zdarma nejlevnější tričko (300)
        self::assertSame(
            round(200 + 300 + 500 - 300, 2),
            round($finance->cenaPredmetu(), 2),
        );
    }
}
