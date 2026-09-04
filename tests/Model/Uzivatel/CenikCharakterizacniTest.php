<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Uzivatel;

use Gamecon\Cas\DateTimeImmutableStrict;
use Gamecon\Pravo;
use Gamecon\Shop\SqlStruktura\PredmetSqlStruktura as PredmetySql;
use Gamecon\Shop\TypPredmetu;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Uzivatel\Cenik;
use Gamecon\Uzivatel\Finance;

/**
 * Pins the behaviour of Cenik before the discount rules move into data.
 *
 * Cenik decides what every participant pays and had no tests of its own — the only
 * coverage went through Finance end to end. These tests are deliberately written
 * against the current behaviour rather than against a specification: they exist to
 * make the rewrite provable, so a change in the numbers below is a real change in
 * what somebody is charged, not a test that needs adjusting.
 *
 * Six discount kinds, all keyed on a Pravo rather than a role:
 *   dice / badge   100 % off, one each
 *   t-shirt        100 % off from an earned bonus, or 1–2 by right
 *   accommodation  100 % off, whole stay or a single named night
 *   meal           100 % off, or a fixed amount from system settings
 */
class CenikCharakterizacniTest extends AbstractTestDb
{
    private const ID_UZIVATELE = 335;
    private const ID_ROLE = -335335;

    private const ID_KOSTKA = 33500;
    private const ID_PLACKA = 33501;
    private const ID_TRICKO_LEVNE = 33502;
    private const ID_TRICKO_DRAHE = 33503;
    private const ID_UBYTOVANI_STREDA = 33504;
    private const ID_UBYTOVANI_CTVRTEK = 33505;
    private const ID_JIDLO = 33506;
    private const ID_OBYCEJNY_PREDMET = 33507;

    protected static array $initQueries = [
        <<<SQL
INSERT INTO uzivatele_hodnoty SET id_uzivatele = 335, login_uzivatele = 'CenikTest', jmeno_uzivatele = 'Cenik', prijmeni_uzivatele = 'Test', email1_uzivatele = 'cenik.test@bio.org'
SQL,
        [
            <<<SQL
INSERT INTO role_seznam(id_role, kod_role, nazev_role, popis_role, rocnik_role, typ_role, vyznam_role)
VALUES ($0, 'TEST_CENIK', 'Test role ceník', '', -1, 'trvala', '')
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
INSERT INTO shop_predmety SET id_predmetu = 33500, nazev = 'Kostka testovací', kod_predmetu = CONCAT('kostka_test_', $0), cena_aktualni = 100, stav = 1, nabizet_do = NOW(), kusu_vyrobeno = 100
SQL,
            [
                0 => ROCNIK,
            ],
        ],
        "INSERT INTO product_product_tag (product_id, tag_id) SELECT 33500, id FROM product_tag WHERE code = 'predmet'",
        [
            <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 33501, nazev = 'Placka testovací', kod_predmetu = CONCAT('placka_test_', $0), cena_aktualni = 50, stav = 1, nabizet_do = NOW(), kusu_vyrobeno = 100
SQL,
            [
                0 => ROCNIK,
            ],
        ],
        "INSERT INTO product_product_tag (product_id, tag_id) SELECT 33501, id FROM product_tag WHERE code = 'predmet'",
        [
            <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 33502, nazev = 'Tričko levné testovací', kod_predmetu = CONCAT('tricko_levne_test_', $0), cena_aktualni = 300, stav = 1, nabizet_do = NOW(), kusu_vyrobeno = 100
SQL,
            [
                0 => ROCNIK,
            ],
        ],
        "INSERT INTO product_product_tag (product_id, tag_id) SELECT 33502, id FROM product_tag WHERE code = 'tricko'",
        [
            <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 33503, nazev = 'Tričko drahé testovací', kod_predmetu = CONCAT('tricko_drahe_test_', $0), cena_aktualni = 500, stav = 1, nabizet_do = NOW(), kusu_vyrobeno = 100
SQL,
            [
                0 => ROCNIK,
            ],
        ],
        "INSERT INTO product_product_tag (product_id, tag_id) SELECT 33503, id FROM product_tag WHERE code = 'tricko'",
        [
            <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 33504, nazev = 'Ubytování středa testovací', kod_predmetu = CONCAT('ubytovani_st_test_', $0), cena_aktualni = 400, stav = 1, nabizet_do = NOW(), kusu_vyrobeno = 100, ubytovani_den = 0
SQL,
            [
                0 => ROCNIK,
            ],
        ],
        "INSERT INTO product_product_tag (product_id, tag_id) SELECT 33504, id FROM product_tag WHERE code = 'ubytovani'",
        [
            <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 33505, nazev = 'Ubytování čtvrtek testovací', kod_predmetu = CONCAT('ubytovani_ct_test_', $0), cena_aktualni = 400, stav = 1, nabizet_do = NOW(), kusu_vyrobeno = 100, ubytovani_den = 1
SQL,
            [
                0 => ROCNIK,
            ],
        ],
        "INSERT INTO product_product_tag (product_id, tag_id) SELECT 33505, id FROM product_tag WHERE code = 'ubytovani'",
        [
            <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 33506, nazev = 'Oběd testovací', kod_predmetu = CONCAT('obed_test_', $0), cena_aktualni = 150, stav = 1, nabizet_do = NOW(), kusu_vyrobeno = 100, ubytovani_den = 1
SQL,
            [
                0 => ROCNIK,
            ],
        ],
        "INSERT INTO product_product_tag (product_id, tag_id) SELECT 33506, id FROM product_tag WHERE code = 'jidlo'",
        [
            <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 33507, nazev = 'Obyčejný předmět testovací', kod_predmetu = CONCAT('obycejny_test_', $0), cena_aktualni = 200, stav = 1, nabizet_do = NOW(), kusu_vyrobeno = 100
SQL,
            [
                0 => ROCNIK,
            ],
        ],
        "INSERT INTO product_product_tag (product_id, tag_id) SELECT 33507, id FROM product_tag WHERE code = 'predmet'",
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

    private function cenik(): Cenik
    {
        $systemoveNastaveni = SystemoveNastaveni::zGlobals(ROCNIK, new DateTimeImmutableStrict());
        $uzivatel = \Uzivatel::zIdUrcite(self::ID_UZIVATELE);

        return new Cenik($uzivatel, new Finance($uzivatel, 0, $systemoveNastaveni), $systemoveNastaveni);
    }

    /**
     * @return array<string, mixed> the row shape Cenik::cena() expects
     */
    private function radek(int $idPredmetu): array
    {
        $radek = \dbOneLine(
            'SELECT * FROM shop_predmety_s_typem WHERE id_predmetu = $0',
            [
                0 => $idPredmetu,
            ],
        );
        self::assertNotEmpty($radek, "Předmět {$idPredmetu} nenalezen");

        return $radek;
    }

    /**
     * @test
     */
    public function bezPravJeVsechnoZaPlnouCenu(): void
    {
        $cenik = $this->cenik();

        self::assertSame(100.0, $cenik->cena($this->radek(self::ID_KOSTKA))->finalPrice);
        self::assertSame(50.0, $cenik->cena($this->radek(self::ID_PLACKA))->finalPrice);
        self::assertSame(300.0, $cenik->cena($this->radek(self::ID_TRICKO_LEVNE))->finalPrice);
        self::assertSame(400.0, $cenik->cena($this->radek(self::ID_UBYTOVANI_STREDA))->finalPrice);
        self::assertSame(150.0, $cenik->cena($this->radek(self::ID_JIDLO))->finalPrice);
        self::assertSame(200.0, $cenik->cena($this->radek(self::ID_OBYCEJNY_PREDMET))->finalPrice);
    }

    /**
     * @test
     */
    public function kostkaZdarmaPlatiJenNaPrvniKostku(): void
    {
        $this->udelPravo(Pravo::KOSTKA_ZDARMA);
        $cenik = $this->cenik();

        // Jedna kostka zdarma, druhá už za plnou cenu — čítač je jeden na instanci Ceníku.
        self::assertSame(0.0, $cenik->cena($this->radek(self::ID_KOSTKA))->finalPrice);
        self::assertSame(100.0, $cenik->cena($this->radek(self::ID_KOSTKA))->finalPrice);

        // Placku to neovlivní, ta má vlastní právo.
        self::assertSame(50.0, $cenik->cena($this->radek(self::ID_PLACKA))->finalPrice);
    }

    /**
     * @test
     */
    public function plackaZdarmaPlatiJenNaPrvniPlacku(): void
    {
        $this->udelPravo(Pravo::PLACKA_ZDARMA);
        $cenik = $this->cenik();

        self::assertSame(0.0, $cenik->cena($this->radek(self::ID_PLACKA))->finalPrice);
        self::assertSame(50.0, $cenik->cena($this->radek(self::ID_PLACKA))->finalPrice);
        self::assertSame(100.0, $cenik->cena($this->radek(self::ID_KOSTKA))->finalPrice);
    }

    /**
     * @test
     */
    public function cenaKostkyAPlackyNesnizujeCitac(): void
    {
        $this->udelPravo(Pravo::KOSTKA_ZDARMA);
        $cenik = $this->cenik();

        // cenaKostky() se ptá "kolik by to stálo", ne "prodej mi to" — volá se
        // s $omezPocet = false, takže vrací nulu opakovaně a nevyčerpá nárok.
        self::assertSame(0, $cenik->cenaKostky($this->radek(self::ID_KOSTKA)));
        self::assertSame(0, $cenik->cenaKostky($this->radek(self::ID_KOSTKA)));
        self::assertSame(0.0, $cenik->cena($this->radek(self::ID_KOSTKA))->finalPrice);
    }

    /**
     * @test
     */
    public function jednoJakekolivTrickoZdarma(): void
    {
        $this->udelPravo(Pravo::JAKEKOLIV_TRICKO_ZDARMA);
        $cenik = $this->cenik();

        self::assertSame(0.0, $cenik->cena($this->radek(self::ID_TRICKO_LEVNE))->finalPrice);
        self::assertSame(500.0, $cenik->cena($this->radek(self::ID_TRICKO_DRAHE))->finalPrice);
    }

    /**
     * @test
     */
    public function dveJakakolivTrickaZdarma(): void
    {
        $this->udelPravo(Pravo::DVE_JAKAKOLI_TRICKA_ZDARMA);
        $cenik = $this->cenik();

        self::assertSame(0.0, $cenik->cena($this->radek(self::ID_TRICKO_LEVNE))->finalPrice);
        self::assertSame(0.0, $cenik->cena($this->radek(self::ID_TRICKO_DRAHE))->finalPrice);
        self::assertSame(300.0, $cenik->cena($this->radek(self::ID_TRICKO_LEVNE))->finalPrice);
    }

    /**
     * @test
     */
    public function ubytovaniZdarmaPlatiNaVsechnyDny(): void
    {
        $this->udelPravo(Pravo::UBYTOVANI_ZDARMA);
        $cenik = $this->cenik();

        self::assertSame(0.0, $cenik->cena($this->radek(self::ID_UBYTOVANI_STREDA))->finalPrice);
        self::assertSame(0.0, $cenik->cena($this->radek(self::ID_UBYTOVANI_CTVRTEK))->finalPrice);
    }

    /**
     * @test
     */
    public function ubytovaniZdarmaJenNaJmenovanouNoc(): void
    {
        $this->udelPravo(Pravo::UBYTOVANI_STREDECNI_NOC_ZDARMA);
        $cenik = $this->cenik();

        // Právo je vázané na konkrétní noc, ostatní se platí.
        self::assertSame(0.0, $cenik->cena($this->radek(self::ID_UBYTOVANI_STREDA))->finalPrice);
        self::assertSame(400.0, $cenik->cena($this->radek(self::ID_UBYTOVANI_CTVRTEK))->finalPrice);

        // Na rozdíl od kostky se nárok nevyčerpá — platí na každou středeční noc.
        self::assertSame(0.0, $cenik->cena($this->radek(self::ID_UBYTOVANI_STREDA))->finalPrice);
    }

    /**
     * @test
     */
    public function jidloZdarma(): void
    {
        $this->udelPravo(Pravo::JIDLO_ZDARMA);
        $cenik = $this->cenik();

        self::assertSame(0.0, $cenik->cena($this->radek(self::ID_JIDLO))->finalPrice);
        self::assertSame(0.0, $cenik->cena($this->radek(self::ID_JIDLO))->finalPrice);
    }

    /**
     * @test
     */
    public function jidloSeSlevouOdectePevnouCastku(): void
    {
        $this->udelPravo(Pravo::JIDLO_SE_SLEVOU);
        $systemoveNastaveni = SystemoveNastaveni::zGlobals(ROCNIK, new DateTimeImmutableStrict());
        $sleva = $systemoveNastaveni->slevaOrguNaJidloCastka();
        self::assertGreaterThan(0, $sleva, 'Sleva na jídlo musí být nastavená, jinak test nic neověří');

        $cenik = $this->cenik();

        // Pevná částka, ne procento — jediná sleva, která není 100 %.
        self::assertSame(150.0 - $sleva, $cenik->cena($this->radek(self::ID_JIDLO))->finalPrice);
    }

    /**
     * @test
     */
    public function slevaSeNeaplikujeNaJinyTypPredmetu(): void
    {
        $this->udelPravo(Pravo::JIDLO_ZDARMA);
        $this->udelPravo(Pravo::UBYTOVANI_ZDARMA);
        $cenik = $this->cenik();

        // Práva na jídlo a ubytování nesmí zlevnit obyčejný předmět ani tričko.
        self::assertSame(200.0, $cenik->cena($this->radek(self::ID_OBYCEJNY_PREDMET))->finalPrice);
        self::assertSame(300.0, $cenik->cena($this->radek(self::ID_TRICKO_LEVNE))->finalPrice);
    }

    /**
     * @test
     */
    public function puvodniCenaPreferujeNakupniCenuPredKatalogovou(): void
    {
        $cenik = $this->cenik();
        $radek = $this->radek(self::ID_KOSTKA);

        self::assertSame(100.0, $cenik->puvodniCena($radek));

        // Historický nákup se cení tím, co bylo zaplaceno, ne dnešním ceníkem.
        $radek['cena_nakupni'] = 80;
        self::assertSame(80.0, $cenik->puvodniCena($radek));
    }

    /**
     * @test
     */
    public function aplikujSlevuNepretahujeDoZaporu(): void
    {
        $cena = 100.0;
        $sleva = 30.0;
        $vysledek = Cenik::aplikujSlevu($cena, $sleva);
        self::assertSame(70.0, $vysledek['cena']);
        self::assertSame(0.0, $vysledek['sleva']);

        // Sleva větší než cena vynuluje cenu a zbytek slevy zůstane k použití jinde.
        $cena = 40.0;
        $sleva = 100.0;
        $vysledek = Cenik::aplikujSlevu($cena, $sleva);
        self::assertSame(0.0, $vysledek['cena']);
        self::assertSame(60.0, $vysledek['sleva']);
    }

    /**
     * @test
     */
    public function neznamyTypPredmetuSkonciVyjimkou(): void
    {
        $cenik = $this->cenik();
        $radek = $this->radek(self::ID_KOSTKA);
        $radek[PredmetySql::TYP] = null;

        $this->expectException(\RuntimeException::class);
        $cenik->cena($radek);
    }

    /**
     * @test
     */
    public function typPredmetuRozhodujeOSleve(): void
    {
        $this->udelPravo(Pravo::JAKEKOLIV_TRICKO_ZDARMA);
        $cenik = $this->cenik();

        // Sleva na tričko se řídí typem, ne názvem ani kódem: obyčejný předmět
        // přeznačený na typ TRICKO dostane tričkovou slevu.
        $radek = $this->radek(self::ID_OBYCEJNY_PREDMET);
        $radek[PredmetySql::TYP] = TypPredmetu::TRICKO;

        self::assertSame(0.0, $cenik->cena($radek)->finalPrice);
    }
}
