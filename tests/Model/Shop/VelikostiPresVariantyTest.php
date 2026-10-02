<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Shop;

use Gamecon\Shop\Polozka;
use Gamecon\Shop\Shop;
use Gamecon\Statistiky\Statistiky;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\SystemoveNastaveni\ZdrojRocniku;
use Gamecon\Tests\Db\AbstractTestDb;

/**
 * A size is bought as a variant of its model: the purchase points at the model and names the
 * size through its variant. Sizes have no catalog rows of their own.
 */
class VelikostiPresVariantyTest extends AbstractTestDb
{
    private const UZIVATEL = 448;
    private const MODEL = 44801;
    // A year no other fixture buys in, so the statistics count only this purchase.
    private const ROK_STATISTIK = 2098;

    protected static array $initQueries = [
        <<<SQL
INSERT INTO uzivatele_hodnoty SET id_uzivatele = 448, login_uzivatele = 'TestVelikostiVarianty', jmeno_uzivatele = 'Test', prijmeni_uzivatele = 'Velikosti', email1_uzivatele = 'test.velikosti.varianty@example.org', pohlavi = 'f'
SQL,
        <<<SQL
INSERT INTO shop_predmety (id_predmetu, nazev, kod_predmetu, cena_aktualni, stav, popis)
VALUES (44801, 'Tričko velikostní', 'tricko_velikosti', 250, 1, '')
SQL,
        "INSERT INTO product_product_tag (product_id, tag_id) SELECT id_predmetu, (SELECT id FROM product_tag WHERE code = 'tricko') FROM shop_predmety WHERE id_predmetu = 44801",
        <<<SQL
INSERT INTO product_variant (product_id, name, code, capacity, position, state)
VALUES (44801, 'S', 'tricko_velikosti_S', 10, 0, 1),
       (44801, 'XL', 'tricko_velikosti_XL', 10, 1, 1)
SQL,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->kupXl(ROCNIK);
    }

    /**
     * The desk sells a size as a variant of its model, as the cart writes it.
     *
     * @test
     */
    public function prodejVelikostiZapiseModelAVariantu(): void
    {
        $uzivatel = \Uzivatel::zIdUrcite(self::UZIVATEL);

        (new Shop($uzivatel, $uzivatel, SystemoveNastaveni::zGlobals()))->prodat($this->idVarianty('tricko_velikosti_XL'));

        self::assertSame(
            [[
                'id_predmetu' => (string) self::MODEL,
                'code'        => 'tricko_velikosti_XL',
            ]],
            dbFetchAll(
                'SELECT product_variant.product_id AS id_predmetu, product_variant.code
                 FROM shop_nakupy
                 JOIN product_variant ON product_variant.id = shop_nakupy.variant_id
                 WHERE shop_nakupy.id_uzivatele = $0 AND shop_nakupy.rok = $1 AND shop_nakupy.order_id IS NOT NULL',
                [
                    0 => self::UZIVATEL,
                    1 => ROCNIK,
                ],
            ),
        );
    }

    /**
     * @test
     */
    public function rychlyProdejOdecteProdanouVelikost(): void
    {
        $zbyva = array_column(Shop::polozkyRychlehoProdeje(ROCNIK), 'zbyva', 'id_varianty');

        self::assertSame(10, (int) $zbyva[$this->idVarianty('tricko_velikosti_S')], 'S');
        self::assertSame(9, (int) $zbyva[$this->idVarianty('tricko_velikosti_XL')], 'XL');
    }

    /**
     * The model's own row is also its first size; listed by its bare name, it read as the model.
     *
     * @test
     */
    public function rychlyProdejJmenujeKazdouVelikost(): void
    {
        $nazvy = array_column(Shop::polozkyRychlehoProdeje(ROCNIK), 'nazev', 'id_varianty');

        self::assertSame('Tričko velikostní S ' . ROCNIK, $nazvy[$this->idVarianty('tricko_velikosti_S')] ?? null);
        self::assertSame('Tričko velikostní XL ' . ROCNIK, $nazvy[$this->idVarianty('tricko_velikosti_XL')] ?? null);
    }

    /**
     * The model's own row is also its first size; the desk's overview named it as the model.
     *
     * @test
     */
    public function prehledObjednavekJmenujeKoupenouVelikost(): void
    {
        dbQuery(
            'INSERT INTO shop_nakupy (id_uzivatele, variant_id, rok, cena_nakupni) VALUES ($0, $2, $3, 250)',
            [
                0 => self::UZIVATEL,
                1 => self::MODEL,
                2 => $this->idVarianty('tricko_velikosti_S'),
                3 => ROCNIK,
            ],
        );
        $uzivatel = \Uzivatel::zIdUrcite(self::UZIVATEL);

        self::assertSame(
            ['Trička: Tričko velikostní S, Tričko velikostní XL'],
            (new Shop($uzivatel, $uzivatel, SystemoveNastaveni::zGlobals()))->prehledObjednavekProInfopult(),
        );
    }

    private function idVarianty(string $kod): int
    {
        $idVarianty = (int) dbOneCol('SELECT id FROM product_variant WHERE code = $0', [
            0 => $kod,
        ]);
        self::assertNotSame(0, $idVarianty, "Varianta {$kod} neexistuje");

        return $idVarianty;
    }

    /**
     * @test
     */
    public function letosniPolozkyPocitajiProdaneVelikostiModelu(): void
    {
        $prodano = [];
        foreach (Shop::letosniPolozky(ROCNIK, [self::MODEL]) as $polozka) {
            /** @var Polozka $polozka */
            $prodano[$polozka->idPredmetu()] = $polozka->prodanoKusu();
        }

        self::assertSame([
            self::MODEL => 1.0,
        ], $prodano);
    }

    /**
     * @test
     */
    public function statistikyPocitajiKazdouVelikost(): void
    {
        $this->kupXl(self::ROK_STATISTIK);
        $rok = self::ROK_STATISTIK;
        $statistiky = new Statistiky([$rok], new class($rok) implements ZdrojRocniku {
            public function __construct(
                private readonly int $rok,
            ) {
            }

            public function rocnik(): int
            {
                return $this->rok;
            }
        });

        $dokument = new \DOMDocument();
        @$dokument->loadHTML('<?xml encoding="utf-8"?>' . $statistiky->tabulkaPredmetuHtml());
        $radky = [];
        foreach ($dokument->getElementsByTagName('tr') as $radek) {
            $bunky = [];
            foreach ($radek->getElementsByTagName('td') as $bunka) {
                $bunky[] = trim($bunka->textContent);
            }
            if ($bunky !== []) {
                $radky[$bunky[0]] = $bunky[2];
            }
        }

        self::assertSame([
            'Tričko velikostní XL' => '1',
        ], $radky);
    }

    private function kupXl(int $rok): void
    {
        dbQuery(
            'INSERT INTO shop_nakupy (id_uzivatele, variant_id, rok, cena_nakupni)
             VALUES ($0, (SELECT id FROM product_variant WHERE code = $2), $3, 250)',
            [
                0 => self::UZIVATEL,
                1 => self::MODEL,
                2 => 'tricko_velikosti_XL',
                3 => $rok,
            ],
        );
    }
}
