<?php

declare(strict_types=1);

namespace Gamecon\Tests\Shop;

use Gamecon\Shop\Shop;
use Gamecon\Shop\StavPredmetu;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;

/**
 * Prodej na pultu legacy cestou musí zapsat totéž co pokladna KFC: kupujícím je anonymní
 * účet, hotovost se připíše a platba ví, ke které objednávce patří.
 */
class ShopProdejAnonymniTest extends AbstractTestDb
{
    protected static bool $disableStrictTransTables = true;

    protected static function keepTestClassDbChangesInTransaction(): bool
    {
        return false;
    }

    protected static function keepSingleTestMethodDbChangesInTransaction(): bool
    {
        return false;
    }

    protected static function resetDbAfterClass(): bool
    {
        return true;
    }

    protected static array $initQueries = [
        <<<SQL
INSERT INTO uzivatele_hodnoty SET
    id_uzivatele = 88821,
    login_uzivatele = 'test_operator_pult',
    jmeno_uzivatele = 'Test',
    prijmeni_uzivatele = 'Operator',
    email1_uzivatele = 'test.operator.pult@example.org'
SQL,
    ];

    protected static function getBeforeClassInitCallbacks(): array
    {
        return [
            static function () {
                dbQuery('INSERT INTO shop_predmety SET
                    id_predmetu = 88831,
                    nazev = "Pultové tričko",
                    kod_predmetu = "pult_anonym_test",
                    cena_aktualni = 150,
                    stav = ' . StavPredmetu::VEREJNY . ',
                    kusu_vyrobeno = 10,
                    popis = ""');
                dbQuery('INSERT INTO product_product_tag (product_id, tag_id)
                    SELECT 88831, id FROM product_tag WHERE code = "predmet"');
            },
        ];
    }

    /**
     * @return int id objednávky, kterou prodej založil
     */
    private function prodejNaPultu(int $kusu = 1): int
    {
        $operator = \Uzivatel::zIdUrcite(88821);
        $anonym = \Uzivatel::zIdUrcite(\Uzivatel::ANONYM);

        (new Shop($anonym, $operator, SystemoveNastaveni::zGlobals()))->prodat(88831, $kusu);

        // Testovací třída neběží v transakci, takže řádky se mezi metodami kupí — assertiony
        // se proto vážou na objednávku tohohle prodeje, ne na obsah celé tabulky.
        return (int) dbOneCol('SELECT MAX(id) FROM shop_order WHERE customer_id = $0', [
            0 => \Uzivatel::ANONYM,
        ]);
    }

    /**
     * @test
     */
    public function nakupSeZapiseNaAnonymniUcet(): void
    {
        $idObjednavky = $this->prodejNaPultu();

        self::assertSame(
            1,
            (int) dbOneCol(
                'SELECT COUNT(*) FROM shop_nakupy WHERE order_id = $0 AND id_uzivatele = $1',
                [
                    0 => $idObjednavky,
                    1 => \Uzivatel::ANONYM,
                ],
            ),
            'Kupujícím musí být anonymní účet, ne SYSTEM',
        );
    }

    /**
     * @test
     */
    public function nakupPatriDoObjednavky(): void
    {
        $idObjednavky = $this->prodejNaPultu(kusu: 2);

        // Oba kusy do jedné objednávky — jeden prodej je jedna účtenka.
        self::assertSame(
            2,
            (int) dbOneCol(
                'SELECT COUNT(*) FROM shop_nakupy WHERE order_id = $0',
                [
                    0 => $idObjednavky,
                ],
            ),
            'Oba kusy musí patřit do jedné objednávky',
        );
    }

    /**
     * @test
     */
    public function platbaZnaSvouObjednavku(): void
    {
        $idObjednavky = $this->prodejNaPultu();

        $platba = dbOneLine(
            'SELECT castka, order_id, poznamka FROM platby WHERE order_id = $0',
            [
                0 => $idObjednavky,
            ],
        );

        self::assertNotSame([], $platba, 'Anonymní prodej se musí připsat');
        self::assertSame('anonymní prodej', $platba['poznamka']);
        self::assertNotNull(
            $platba['order_id'],
            'Bez vazby na objednávku po nedokončeném prodeji platba osiří',
        );
        self::assertSame(
            $idObjednavky,
            (int) dbOneCol('SELECT order_id FROM shop_nakupy WHERE order_id = $0 LIMIT 1', [
                0 => $idObjednavky,
            ]),
            'Platba musí ukazovat na tutéž objednávku jako nákup',
        );
    }
}
