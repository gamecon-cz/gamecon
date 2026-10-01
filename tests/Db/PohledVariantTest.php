<?php

declare(strict_types=1);

namespace Gamecon\Tests\Db;

use App\Enum\ProductStateEnum;

/**
 * Legacy reads what a purchase bought through `shop_varianty_s_typem`, one row per variant, so
 * a night or a size reads the same whether its catalog row is the group's product or a leftover.
 */
class PohledVariantTest extends AbstractTestDb
{
    protected static array $initQueries = [
        // A room type nobody buys, suspended, owning a night that is on sale.
        <<<SQL
INSERT INTO shop_predmety (id_predmetu, nazev, kod_predmetu, cena_aktualni, stav, popis)
VALUES (88901, 'Postel testovací', 'test_pokoj-typ', 400, 3, ''),
       (88902, 'Postel testovací pátek', 'test_pokoj_pa', 400, 1, '')
SQL,
        // A shirt group: the owner is the S row, L is a leftover row of the legacy layout.
        <<<SQL
INSERT INTO shop_predmety (id_predmetu, nazev, kod_predmetu, cena_aktualni, stav, popis)
VALUES (88911, 'Tričko testovací', 'tricko_test_S_2026', 250, 1, ''),
       (88912, 'Tričko testovací L', 'tricko_test_L_2026', 250, 1, ''),
       (88921, 'Kostka testovací', 'kostka_test', 50, 1, '')
SQL,
        <<<SQL
INSERT INTO product_product_tag (product_id, tag_id)
SELECT produkty.id_predmetu, product_tag.id
FROM product_tag
INNER JOIN (
    SELECT 88901 AS id_predmetu, 'ubytovani' AS tag
    UNION ALL SELECT 88911, 'tricko'
    UNION ALL SELECT 88921, 'predmet'
) AS produkty ON produkty.tag = product_tag.code
SQL,
        <<<SQL
INSERT INTO product_variant (product_id, name, code, price, capacity, accommodation_day, position, state)
VALUES (88901, 'pátek', 'test_pokoj_pa', NULL, 5, 2, 0, 1),
       (88911, 'S', 'tricko_test_S_2026', NULL, 10, NULL, 0, 1),
       (88911, 'L', 'tricko_test_L_2026', 300, 8, NULL, 1, 3),
       (88921, 'Kostka testovací', 'kostka_test', NULL, NULL, NULL, 0, 1)
SQL,
    ];

    /**
     * @test
     */
    public function nocNeseDenAStavSveVarianty(): void
    {
        self::assertSame(
            [
                'id_predmetu'   => '88901',
                'nazev'         => 'Postel testovací pátek',
                'stav'          => (string) ProductStateEnum::PUBLIC->value,
                'ubytovani_den' => '2',
                'typ'           => '2',
                'kusu_vyrobeno' => '5',
            ],
            $this->radek('test_pokoj_pa', ['id_predmetu', 'nazev', 'stav', 'ubytovani_den', 'typ', 'kusu_vyrobeno']),
        );
    }

    /**
     * @test
     */
    public function velikostSeCteStejneUVlastnikaIUZbylehoRadku(): void
    {
        self::assertSame(
            [
                'id_predmetu'   => '88911',
                'nazev'         => 'Tričko testovací S',
                'cena_aktualni' => '250.00',
                'stav'          => (string) ProductStateEnum::PUBLIC->value,
                'typ'           => '3',
            ],
            $this->radek('tricko_test_S_2026', ['id_predmetu', 'nazev', 'cena_aktualni', 'stav', 'typ']),
        );
        self::assertSame(
            [
                'id_predmetu'   => '88911',
                'nazev'         => 'Tričko testovací L',
                'cena_aktualni' => '300.00',
                'stav'          => (string) ProductStateEnum::SUSPENDED->value,
                'typ'           => '3',
            ],
            $this->radek('tricko_test_L_2026', ['id_predmetu', 'nazev', 'cena_aktualni', 'stav', 'typ']),
            'Vlastní cena a stav varianty mají přednost před produktem',
        );
    }

    /**
     * @test
     */
    public function jednovariantovyProduktSeNeopakujeVNazvu(): void
    {
        self::assertSame(
            'Kostka testovací',
            $this->radek('kostka_test', ['nazev'])['nazev'],
        );
    }

    /**
     * @test
     */
    public function kazdaVariantaMaPraveJedenRadek(): void
    {
        self::assertSame(
            '4',
            (string) dbOneCol(<<<SQL
                SELECT COUNT(*)
                FROM shop_varianty_s_typem
                WHERE id_predmetu IN (88901, 88911, 88921)
                SQL,
            ),
        );
    }

    /**
     * @param list<string> $sloupce
     *
     * @return array<string, string|null>
     */
    private function radek(string $kod, array $sloupce): array
    {
        $radek = dbOneLine('SELECT * FROM shop_varianty_s_typem WHERE kod_predmetu = $0', [
            0 => $kod,
        ]);
        self::assertNotNull($radek, "Varianta {$kod} v pohledu chybí");

        $vybrane = [];
        foreach ($sloupce as $sloupec) {
            $vybrane[$sloupec] = $radek[$sloupec] === null ? null : (string) $radek[$sloupec];
        }

        return $vybrane;
    }
}
