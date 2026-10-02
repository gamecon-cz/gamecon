<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Shop;

use Gamecon\Shop\Shop;
use Gamecon\Statistiky\Statistiky;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\SystemoveNastaveni\ZdrojRocniku;
use Gamecon\Tests\Db\AbstractTestDb;

/**
 * A night is bought as a variant of its room type: the purchase points at the room type and
 * names the night through its variant. Whatever reads nights has to find them there.
 */
class UbytovaniPresVariantyTest extends AbstractTestDb
{
    private const UZIVATEL = 447;
    private const TYP_POKOJE = 44711;
    // A year no other fixture buys in, so the statistics count only these nights.
    private const ROK_STATISTIK = 2099;

    protected static array $initQueries = [
        <<<SQL
INSERT INTO uzivatele_hodnoty SET id_uzivatele = 447, login_uzivatele = 'TestUbytovaniVarianty', jmeno_uzivatele = 'Test', prijmeni_uzivatele = 'Ubytovani', email1_uzivatele = 'test.ubytovani.varianty@example.org', pohlavi = 'f'
SQL,
        <<<SQL
INSERT INTO shop_predmety (id_predmetu, nazev, kod_predmetu, cena_aktualni, stav, ubytovani_den, popis)
VALUES (44711, 'Spacák testovací', 'spacak_varianty-typ', 100, 3, NULL, ''),
       (44712, 'Spacák testovací čtvrtek', 'spacak_varianty_ct', 100, 1, 1, ''),
       (44713, 'Spacák testovací pátek', 'spacak_varianty_pa', 100, 1, 2, '')
SQL,
        "INSERT INTO product_product_tag (product_id, tag_id) SELECT id_predmetu, (SELECT id FROM product_tag WHERE code = 'ubytovani') FROM shop_predmety WHERE id_predmetu IN (44711, 44712, 44713)",
        <<<SQL
INSERT INTO product_variant (product_id, name, code, accommodation_day, position, state)
VALUES (44711, 'čtvrtek', 'spacak_varianty_ct', 1, 0, 1),
       (44711, 'pátek', 'spacak_varianty_pa', 2, 1, 1)
SQL,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->kupNoci(ROCNIK);
    }

    /**
     * @test
     */
    public function shopVidiKazdouKoupenouNoc(): void
    {
        $shop = $this->shop();

        $dny = $shop->veKterychDnechJeUbytovan();
        sort($dny);
        self::assertSame([1, 2], array_map('intval', $dny));
        self::assertStringContainsString('Spacák testovací: čt,pá', $shop->dejPopisUbytovani());
    }

    /**
     * @test
     */
    public function pokojUbytujeNaKazdouKoupenouNoc(): void
    {
        \Pokoj::ubytujNaCislo(\Uzivatel::zIdUrcite(self::UZIVATEL), '101');

        self::assertSame(['1', '2'], array_map('strval', dbOneArray(
            'SELECT den FROM ubytovani WHERE id_uzivatele = $0 AND rok = $1 ORDER BY den',
            [
                0 => self::UZIVATEL,
                1 => ROCNIK,
            ],
        )));
    }

    /**
     * The mail to a mass-unregistered non-payer lists what was cancelled.
     *
     * @test
     */
    public function zrusenaNocSeJmenujeSvouNoci(): void
    {
        dbQuery(
            'INSERT INTO shop_nakupy_zrusene (id_nakupu, id_uzivatele, variant_id, rocnik, cena_nakupni, datum_nakupu, zdroj_zruseni, product_name, product_code)
             VALUES (447001, $0, (SELECT id FROM product_variant WHERE code = $5), $2, 100, NOW(), $3, $4, $5)',
            [
                0 => self::UZIVATEL,
                1 => self::TYP_POKOJE,
                2 => ROCNIK,
                3 => 'test-neplatic',
                4 => 'Spacák testovací pátek',
                5 => 'spacak_varianty_pa',
            ],
        );

        self::assertSame(['Spacák testovací pátek'], $this->shop()->dejNazvyZrusenychNakupu('test-neplatic'));
    }

    /**
     * @test
     */
    public function statistikyPocitajiKazdouNoc(): void
    {
        $this->kupNoci(self::ROK_STATISTIK);
        $statistiky = $this->statistiky();

        $ubytovani = array_column($this->radkyTabulky($statistiky->tabulkaUbytovaniHtml()), 1, 0);
        self::assertSame('1', $ubytovani['Spacák testovací čtvrtek'] ?? null);
        self::assertSame('1', $ubytovani['Spacák testovací pátek'] ?? null);

        $poDnech = array_column(array_slice($this->radkyTabulky($statistiky->tabulkaUbytovaniKratce()), 1), 1);
        self::assertSame(['1', '1', '0'], $poDnech, 'čtvrtek, pátek, neubytovaní');

        $historie = $this->radkyTabulky($statistiky->tabulkaHistorieUbytovaniHtml());
        $sloupecRoku = array_search((string) self::ROK_STATISTIK, $historie[0], true);
        self::assertNotFalse($sloupecRoku);
        [, , , , , , , $spacak, $spacakStreda, $spacakCtvrtek, $spacakPatek] = $historie;
        self::assertSame('2', $spacak[$sloupecRoku]);
        self::assertSame('0', $spacakStreda[$sloupecRoku]);
        self::assertSame('1', $spacakCtvrtek[$sloupecRoku]);
        self::assertSame('1', $spacakPatek[$sloupecRoku]);
    }

    private function kupNoci(int $rok): void
    {
        foreach (['spacak_varianty_ct', 'spacak_varianty_pa'] as $kodNoci) {
            dbQuery(
                'INSERT INTO shop_nakupy (id_uzivatele, variant_id, rok, cena_nakupni)
                 VALUES ($0, (SELECT id FROM product_variant WHERE code = $2), $3, 100)',
                [
                    0 => self::UZIVATEL,
                    1 => self::TYP_POKOJE,
                    2 => $kodNoci,
                    3 => $rok,
                ],
            );
        }
    }

    private function shop(): Shop
    {
        $uzivatel = \Uzivatel::zIdUrcite(self::UZIVATEL);

        return new Shop($uzivatel, $uzivatel, SystemoveNastaveni::zGlobals());
    }

    private function statistiky(): Statistiky
    {
        $rok = self::ROK_STATISTIK;

        return new Statistiky([$rok], new class($rok) implements ZdrojRocniku {
            public function __construct(
                private readonly int $rok,
            ) {
            }

            public function rocnik(): int
            {
                return $this->rok;
            }
        });
    }

    /**
     * @return list<list<string>>
     */
    private function radkyTabulky(string $html): array
    {
        $dokument = new \DOMDocument();
        @$dokument->loadHTML('<?xml encoding="utf-8"?>' . $html);
        $radky = [];
        foreach ($dokument->getElementsByTagName('tr') as $radek) {
            $bunky = [];
            foreach ($radek->childNodes as $bunka) {
                if ($bunka instanceof \DOMElement) {
                    $bunky[] = trim($bunka->textContent);
                }
            }
            $radky[] = $bunky;
        }

        return $radky;
    }
}
