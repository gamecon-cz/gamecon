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
 * Pins the price the storefront shows, as opposed to the price it charges.
 *
 * Shop asks two different questions of Cenik and they have different answers.
 * renderPredmet() calls cenaKostky()/cenaPlacky() — "what would this cost" — which pass
 * $omezPocet = false and leave the entitlement counters alone, so a rendered page does
 * not consume anything. zapoctiShop() calls cena(), which sells and does consume.
 *
 * CenikCharakterizacniTest covers cena(). This covers the display side, so the two
 * cannot drift apart while the rules move into data.
 */
class CenikZobrazovaciCestaTest extends AbstractTestDb
{
    private const ID_UZIVATELE = 336;
    private const ID_ROLE = -336336;

    private const ID_KOSTKA = 33600;
    private const ID_PLACKA = 33601;
    private const ID_JIDLO = 33602;

    protected static array $initQueries = [
        <<<SQL
INSERT INTO uzivatele_hodnoty SET id_uzivatele = 336, login_uzivatele = 'CenikZobrazeni', jmeno_uzivatele = 'Cenik', prijmeni_uzivatele = 'Zobrazeni', email1_uzivatele = 'cenik.zobrazeni@bio.org'
SQL,
        [
            <<<SQL
INSERT INTO role_seznam(id_role, kod_role, nazev_role, popis_role, rocnik_role, typ_role, vyznam_role)
VALUES ($0, 'TEST_CENIK_ZOBRAZENI', 'Test role ceník zobrazení', '', -1, 'trvala', '')
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
INSERT INTO shop_predmety SET id_predmetu = 33600, nazev = 'Kostka zobrazovaci', kod_predmetu = CONCAT('kostka_zobraz_', $0), cena_aktualni = 100, stav = 1, nabizet_do = NOW(), kusu_vyrobeno = 100
SQL,
            [
                0 => ROCNIK,
            ],
        ],
        "INSERT INTO product_product_tag (product_id, tag_id) SELECT 33600, id FROM product_tag WHERE code = 'predmet'",
        [
            <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 33601, nazev = 'Placka zobrazovaci', kod_predmetu = CONCAT('placka_zobraz_', $0), cena_aktualni = 50, stav = 1, nabizet_do = NOW(), kusu_vyrobeno = 100
SQL,
            [
                0 => ROCNIK,
            ],
        ],
        "INSERT INTO product_product_tag (product_id, tag_id) SELECT 33601, id FROM product_tag WHERE code = 'predmet'",
        [
            <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 33602, nazev = 'Obed zobrazovaci', kod_predmetu = CONCAT('obed_zobraz_', $0), cena_aktualni = 150, stav = 1, nabizet_do = NOW(), kusu_vyrobeno = 100, ubytovani_den = 1
SQL,
            [
                0 => ROCNIK,
            ],
        ],
        "INSERT INTO product_product_tag (product_id, tag_id) SELECT 33602, id FROM product_tag WHERE code = 'jidlo'",
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
    public function zobrazenaCenaKostkyNevycerpaNarok(): void
    {
        $this->udelPravo(Pravo::KOSTKA_ZDARMA);
        $cenik = $this->cenik();

        // Vykreslení stránky se ptá "kolik by to stálo" a nesmí nárok spotřebovat,
        // i kdyby se stejná kostka vykreslila desetkrát.
        for ($i = 0; $i < 10; ++$i) {
            self::assertSame(0, $cenik->cenaKostky($this->radek(self::ID_KOSTKA)));
        }

        // Teprve nákup nárok vyčerpá.
        self::assertSame(0.0, $cenik->cena($this->radek(self::ID_KOSTKA))->finalPrice);
        self::assertSame(100.0, $cenik->cena($this->radek(self::ID_KOSTKA))->finalPrice);
    }

    /**
     * @test
     */
    public function zobrazenaCenaPlackyNevycerpaNarok(): void
    {
        $this->udelPravo(Pravo::PLACKA_ZDARMA);
        $cenik = $this->cenik();

        for ($i = 0; $i < 10; ++$i) {
            self::assertSame(0, $cenik->cenaPlacky($this->radek(self::ID_PLACKA)));
        }

        self::assertSame(0.0, $cenik->cena($this->radek(self::ID_PLACKA))->finalPrice);
        self::assertSame(50.0, $cenik->cena($this->radek(self::ID_PLACKA))->finalPrice);
    }

    /**
     * @test
     */
    public function bezPravaJeZobrazenaCenaPlna(): void
    {
        $cenik = $this->cenik();

        self::assertSame(100, $cenik->cenaKostky($this->radek(self::ID_KOSTKA)));
        self::assertSame(50, $cenik->cenaPlacky($this->radek(self::ID_PLACKA)));
    }

    /**
     * @test
     */
    public function zobrazenaCenaJidlaNemaCitac(): void
    {
        // Jídlo vykresluje Shop::jidloHtml() přes cena(), ne přes zvláštní metodu —
        // je to bezpečné jen proto, že větev pro jídlo žádný čítač nemá.
        $this->udelPravo(Pravo::JIDLO_SE_SLEVOU);
        $systemoveNastaveni = SystemoveNastaveni::zGlobals(ROCNIK, new DateTimeImmutableStrict());
        $sleva = $systemoveNastaveni->slevaOrguNaJidloCastka();
        $cenik = $this->cenik();

        for ($i = 0; $i < 5; ++$i) {
            self::assertSame(
                150.0 - $sleva,
                $cenik->cena($this->radek(self::ID_JIDLO))->finalPrice,
                'Opakované vykreslení ceny jídla musí dávat pořád stejnou částku',
            );
        }
    }

    /**
     * @test
     */
    public function formatDvojiCenyOdpovidaZobrazeni(): void
    {
        // renderPredmet() skládá text "sleva/plná cena" jen když se liší; test drží
        // ta dvě čísla, ze kterých ten text vzniká.
        $this->udelPravo(Pravo::KOSTKA_ZDARMA);
        $cenik = $this->cenik();
        $radek = $this->radek(self::ID_KOSTKA);

        $plnaCena = round((float) $radek['cena_aktualni']);
        $cenaPoSleve = round((float) $cenik->cenaKostky($radek));

        self::assertSame(100.0, $plnaCena);
        self::assertSame(0.0, $cenaPoSleve);
        self::assertNotSame($plnaCena, $cenaPoSleve, 'Liší se, takže se vypíšou obě');
    }
}
