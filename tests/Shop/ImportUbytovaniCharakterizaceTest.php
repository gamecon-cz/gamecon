<?php

declare(strict_types=1);

namespace Gamecon\Tests\Shop;

use Gamecon\Cas\DateTimeGamecon;
use Gamecon\Shop\ShopUbytovani;
use Gamecon\Shop\StavPredmetu;
use Gamecon\Tests\Db\AbstractTestDb;

/**
 * Charakterizace legacy zápisu, na kterém stojí import ubytování z Excelu
 * (`_ubytovani-a-dalsi-obcasne-infopultakoviny-import-ubytovani.php`). Sám import je skript
 * nad `$_FILES` a globálním `$u`, takže z testu volat nejde — testovatelné jsou ty čtyři
 * statické metody, které dělají veškerý zápis.
 *
 * Testy netvrdí, že je chování správné. Tvrdí, **jaké je**, aby se při převodu do Symfony
 * dalo změřit, co se změnilo — jinak se přepisuje zápis do `ubytovani` a osobních údajů
 * poslepu.
 */
class ImportUbytovaniCharakterizaceTest extends AbstractTestDb
{
    protected static function keepTestClassDbChangesInTransaction(): bool
    {
        return true;
    }

    private function ucastnik(): \Uzivatel
    {
        $suffix = uniqid('', false);
        dbQuery(<<<SQL
INSERT INTO uzivatele_hodnoty SET
    login_uzivatele = $0,
    email1_uzivatele = $1,
    jmeno_uzivatele = 'Import',
    prijmeni_uzivatele = 'Test'
SQL,
            [
                0 => 'import_' . $suffix,
                1 => 'import.' . $suffix . '@example.org',
            ],
        );

        return \Uzivatel::zIdUrcite(dbInsertId());
    }

    /**
     * Noc daného dne. Kód předmětu si import dohledává sám podle „typu" z reportu, proto
     * musí sedět tvar `<typ>_<3 znaky dne>`.
     */
    private function vytvorNoc(string $kodTypu, int $den, int $kusuVyrobeno = 10): int
    {
        $pripony = ['_st', '_ct', '_pa', '_so', '_ne'];
        dbQuery(<<<SQL
INSERT INTO shop_predmety SET
    nazev = $0,
    kod_predmetu = $1,
    cena_aktualni = 400,
    stav = $2,
    kusu_vyrobeno = $3,
    ubytovani_den = $4
SQL,
            [
                0 => $kodTypu . ' den ' . $den,
                1 => $kodTypu . $pripony[$den],
                2 => StavPredmetu::VEREJNY,
                3 => $kusuVyrobeno,
                4 => $den,
            ],
        );
        $idPredmetu = dbInsertId();
        dbQuery(
            "INSERT INTO product_product_tag (product_id, tag_id) SELECT $0, id FROM product_tag WHERE code = 'ubytovani'",
            [
                0 => $idPredmetu,
            ],
        );

        return $idPredmetu;
    }

    /**
     * @return array<int, string> pokoj podle dne
     */
    private function pokojePodleDnu(\Uzivatel $ucastnik): array
    {
        $radky = dbFetchAll(
            'SELECT den, pokoj FROM ubytovani WHERE id_uzivatele = $0 AND rok = $1 ORDER BY den',
            [$ucastnik->id(), ROCNIK],
        );
        $podleDnu = [];
        foreach ($radky as $radek) {
            $podleDnu[(int) $radek['den']] = $radek['pokoj'];
        }

        return $podleDnu;
    }

    /**
     * @test
     */
    public function pokojSeZapiseNaKazdouNocRozsahu(): void
    {
        $ucastnik = $this->ucastnik();

        ShopUbytovani::ulozPokojUzivatele('B301', 1, 3, $ucastnik);

        self::assertSame(
            [
                1 => 'B301',
                2 => 'B301',
                3 => 'B301',
            ],
            $this->pokojePodleDnu($ucastnik),
            'Pokoj se zapisuje na každý den rozsahu zvlášť',
        );
    }

    /**
     * Rozsah je zároveň mazací: dny mimo něj z tabulky zmizí, i když tam byly dřív.
     *
     * @test
     */
    public function uzsiRozsahSmazeDnyMimoNej(): void
    {
        $ucastnik = $this->ucastnik();
        ShopUbytovani::ulozPokojUzivatele('B301', 0, 4, $ucastnik);

        ShopUbytovani::ulozPokojUzivatele('B301', 1, 2, $ucastnik);

        self::assertSame(
            [
                1 => 'B301',
                2 => 'B301',
            ],
            $this->pokojePodleDnu($ucastnik),
            'Dny mimo nový rozsah se mažou',
        );
    }

    /**
     * Prázdný pokoj = smazat přiřazení. Import na tom stojí, prázdná buňka je zrušení.
     *
     * @test
     */
    public function prazdnyPokojSmazeVsechnyNoci(): void
    {
        $ucastnik = $this->ucastnik();
        ShopUbytovani::ulozPokojUzivatele('B301', 0, 2, $ucastnik);

        ShopUbytovani::ulozPokojUzivatele('', null, null, $ucastnik);

        self::assertSame([], $this->pokojePodleDnu($ucastnik));
    }

    /**
     * @test
     */
    public function jednaZadanaNocBezDruheJeChyba(): void
    {
        $ucastnik = $this->ucastnik();

        $this->expectException(\Chyba::class);

        ShopUbytovani::ulozPokojUzivatele('B301', 1, null, $ucastnik);
    }

    /**
     * @test
     */
    public function idsPredmetuSeDohledajiPodleKoduTypuADnu(): void
    {
        $kodTypu = 'TESTTYP' . strtoupper(substr(uniqid('', false), -6));
        $ctvrtek = $this->vytvorNoc($kodTypu, DateTimeGamecon::PORADI_HERNIHO_DNE_CTVRTEK);
        $patek = $this->vytvorNoc($kodTypu, DateTimeGamecon::PORADI_HERNIHO_DNE_PATEK);

        $ids = ShopUbytovani::dejIdsPredmetuUbytovaniPodleKoduTypu(
            $kodTypu,
            [DateTimeGamecon::PORADI_HERNIHO_DNE_CTVRTEK, DateTimeGamecon::PORADI_HERNIHO_DNE_PATEK],
        );

        sort($ids);
        $ocekavano = [$ctvrtek, $patek];
        sort($ocekavano);
        self::assertSame($ocekavano, array_map('intval', $ids));
    }

    /**
     * @test
     */
    public function spolubydliciSeUlozi(): void
    {
        $ucastnik = $this->ucastnik();

        $zmen = ShopUbytovani::ulozSKymChceBytNaPokoji('Pepa z Depa', $ucastnik);

        self::assertSame(1, $zmen, 'Vrací počet zapsaných změn');
        self::assertSame(
            'Pepa z Depa',
            dbOneCol('SELECT ubytovan_s FROM uzivatele_hodnoty WHERE id_uzivatele = $0', [$ucastnik->id()]),
        );
    }

    /**
     * Beze změny se nezapisuje — import tím počítá změněné řádky, takže by jinak hlásil
     * změny, které nenastaly.
     *
     * @test
     */
    public function stejnySpolubydliciNicNezapise(): void
    {
        $ucastnik = $this->ucastnik();
        ShopUbytovani::ulozSKymChceBytNaPokoji('Pepa z Depa', $ucastnik);

        $zmen = ShopUbytovani::ulozSKymChceBytNaPokoji('Pepa z Depa', \Uzivatel::zIdUrcite($ucastnik->id()));

        self::assertSame(0, $zmen);
    }

    /**
     * Změna spolubydlícího je změna osobního údaje a loguje se. Na tom stojí dohledávání,
     * kdo komu co přepsal — při převodu se to nesmí ztratit.
     *
     * @test
     */
    public function zmenaSpolubydlicihoSeZaloguje(): void
    {
        $ucastnik = $this->ucastnik();

        ShopUbytovani::ulozSKymChceBytNaPokoji('Pepa z Depa', $ucastnik);

        $zaznamu = (int) dbOneCol(
            'SELECT COUNT(*) FROM uzivatele_hodnoty_log WHERE id_uzivatele = $0',
            [$ucastnik->id()],
        );
        self::assertGreaterThan(0, $zaznamu, 'Zápis spolubydlícího se musí objevit v logu osobních údajů');
    }
}
