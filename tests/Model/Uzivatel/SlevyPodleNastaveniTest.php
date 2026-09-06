<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Uzivatel;

use Gamecon\Cas\DateTimeImmutableStrict;
use Gamecon\Pravo;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Uzivatel\Cenik;
use Gamecon\Uzivatel\Finance;

/**
 * Počty nároků jsou nastavení ve skupině Slevy, ne čísla v kódu.
 *
 * Smysl je, že když je admin na stránce nastavení přepíše, změní se, co se reálně
 * účtuje. Kdyby se hodnota jen zobrazovala a cena se dál brala z konstanty, byla by to
 * horší varianta než hardcode — admin by věřil, že něco nastavil.
 */
class SlevyPodleNastaveniTest extends AbstractTestDb
{
    private const ID_UZIVATELE = 337;
    private const ID_ROLE = -337337;
    private const ID_KOSTKA = 33700;
    private const ID_TRICKO = 33701;

    protected static array $initQueries = [
        <<<SQL
INSERT INTO uzivatele_hodnoty SET id_uzivatele = 337, login_uzivatele = 'SlevyNastaveni', jmeno_uzivatele = 'Slevy', prijmeni_uzivatele = 'Nastaveni', email1_uzivatele = 'slevy.nastaveni@bio.org'
SQL,
        [
            <<<SQL
INSERT INTO role_seznam(id_role, kod_role, nazev_role, popis_role, rocnik_role, typ_role, vyznam_role)
VALUES ($0, 'TEST_SLEVY_NASTAVENI', 'Test role slevy', '', -1, 'trvala', '')
SQL,
            [
                0 => self::ID_ROLE,
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
        [
            <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 33700, nazev = 'Kostka nastaveni', kod_predmetu = CONCAT('kostka_nastaveni_', $0), cena_aktualni = 100, stav = 1, nabizet_do = NOW(), kusu_vyrobeno = 100
SQL,
            [
                0 => ROCNIK,
            ],
        ],
        "INSERT INTO product_product_tag (product_id, tag_id) SELECT 33700, id FROM product_tag WHERE code = 'predmet'",
        [
            <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 33701, nazev = 'Tricko nastaveni', kod_predmetu = CONCAT('tricko_nastaveni_', $0), cena_aktualni = 300, stav = 1, nabizet_do = NOW(), kusu_vyrobeno = 100
SQL,
            [
                0 => ROCNIK,
            ],
        ],
        "INSERT INTO product_product_tag (product_id, tag_id) SELECT 33701, id FROM product_tag WHERE code = 'tricko'",
    ];

    private function udelPravo(int $idPrava): void
    {
        \dbQuery(
            'INSERT IGNORE INTO prava_role(id_role, id_prava) VALUES ($0, $1)',
            [
                0 => self::ID_ROLE,
                1 => $idPrava,
            ],
        );
        \Uzivatel::smazCache();
    }

    private function nastav(string $klic, string $hodnota): void
    {
        \dbQuery(
            'UPDATE systemove_nastaveni SET hodnota = $1 WHERE klic = $0',
            [
                0 => $klic,
                1 => $hodnota,
            ],
        );
    }

    private function cenik(): Cenik
    {
        // Čerstvá instance nastavení, aby četla to, co je právě v DB, ne zacachovanou
        // hodnotu z jiného testu.
        $systemoveNastaveni = SystemoveNastaveni::zGlobals(ROCNIK, new DateTimeImmutableStrict());
        $uzivatel = \Uzivatel::zIdUrcite(self::ID_UZIVATELE);

        return new Cenik($uzivatel, new Finance($uzivatel, 0, $systemoveNastaveni), $systemoveNastaveni);
    }

    /**
     * @return array<string, mixed>
     */
    private function radek(int $idPredmetu): array
    {
        return \dbOneLine(
            'SELECT * FROM shop_predmety_s_typem WHERE id_predmetu = $0',
            [
                0 => $idPredmetu,
            ],
        );
    }

    /**
     * @test
     */
    public function vychoziPocetKostekJeJedna(): void
    {
        $this->udelPravo(Pravo::KOSTKA_ZDARMA);
        $cenik = $this->cenik();

        self::assertSame(0.0, $cenik->cena($this->radek(self::ID_KOSTKA))->finalPrice);
        self::assertSame(100.0, $cenik->cena($this->radek(self::ID_KOSTKA))->finalPrice);
    }

    /**
     * @test
     */
    public function zvyseniPoctuKostekVNastaveniZlevniIDruhou(): void
    {
        $this->udelPravo(Pravo::KOSTKA_ZDARMA);
        $this->nastav('SLEVA_KOSTEK_ZDARMA_POCET', '2');
        $cenik = $this->cenik();

        self::assertSame(0.0, $cenik->cena($this->radek(self::ID_KOSTKA))->finalPrice);
        self::assertSame(0.0, $cenik->cena($this->radek(self::ID_KOSTKA))->finalPrice, 'Druhá kostka je taky zdarma');
        self::assertSame(100.0, $cenik->cena($this->radek(self::ID_KOSTKA))->finalPrice, 'Třetí už ne');
    }

    /**
     * @test
     */
    public function vynulovaniPoctuVNastaveniSlevuVypne(): void
    {
        $this->udelPravo(Pravo::KOSTKA_ZDARMA);
        $this->nastav('SLEVA_KOSTEK_ZDARMA_POCET', '0');
        $cenik = $this->cenik();

        self::assertSame(
            100.0,
            $cenik->cena($this->radek(self::ID_KOSTKA))->finalPrice,
            'Nula v nastavení znamená žádná kostka zdarma, i když právo zůstává',
        );
    }

    /**
     * @test
     */
    public function pocetTricekZdarmaJdeZNastaveni(): void
    {
        $this->udelPravo(Pravo::JAKEKOLIV_TRICKO_ZDARMA);
        $this->nastav('SLEVA_TRICEK_ZDARMA_POCET', '2');
        $cenik = $this->cenik();

        self::assertSame(0.0, $cenik->cena($this->radek(self::ID_TRICKO))->finalPrice);
        self::assertSame(0.0, $cenik->cena($this->radek(self::ID_TRICKO))->finalPrice);
        self::assertSame(300.0, $cenik->cena($this->radek(self::ID_TRICKO))->finalPrice);
    }

    /**
     * @test
     */
    public function vsechnyPoctyJsouVeSkupineSlevy(): void
    {
        // Admin má najít všechna čísla slev pohromadě, ne rozházená po skupinách.
        $klice = \dbFetchAll(
            "SELECT klic FROM systemove_nastaveni WHERE skupina = 'Slevy' ORDER BY poradi",
        );
        $klice = array_column($klice, 'klic');

        self::assertContains('SLEVA_ORGU_NA_JIDLO_CASTKA', $klice);
        self::assertContains('SLEVA_KOSTEK_ZDARMA_POCET', $klice);
        self::assertContains('SLEVA_PLACEK_ZDARMA_POCET', $klice);
        self::assertContains('SLEVA_TRICEK_ZDARMA_POCET', $klice);
        self::assertContains('SLEVA_DVOU_TRICEK_ZDARMA_POCET', $klice);
        self::assertContains('SLEVA_BONUSOVYCH_TRICEK_ZDARMA_POCET', $klice);
    }
}
