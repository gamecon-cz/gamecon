<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Report;

use App\Enum\ProductTagCode;
use Gamecon\Role\Role;
use Gamecon\Tests\Db\AbstractUzivatelTestDb;

/**
 * GROUP_CONCAT cells need a stable order: the nights of a gap are read by day, and the report
 * diff harness would otherwise flag every change of query plan.
 */
class FinanceReportUbytovaniPoradiTest extends AbstractUzivatelTestDb
{
    private const REPORTY = __DIR__ . '/../../../admin/scripts/zvlastni/reporty/';

    /**
     * @test
     */
    public function nociVMezerePobytuJsouPodleDne(): void
    {
        $ucastnik = self::prihlasenyUzivatel();
        $postel = $this->typPokoje('zz-pokoj', 'Postel');
        // Bought Friday first, so the purchase order differs from the day order.
        $this->kupNoc($ucastnik, $postel, 'zz-pokoj-pa', 'pátek', 2);
        $this->kupNoc($ucastnik, $postel, 'zz-pokoj-st', 'středa', 0);

        self::assertSame(
            'Postel středa,Postel pátek',
            $this->radekReportu('finance-report-ubytovani.php', $ucastnik)['mezera_v_ubytovani'],
        );
    }

    /**
     * @test
     *
     * @testWith ["finance-report-ubytovani.php"]
     *           ["finance-report-ubytovani-cizinci.php"]
     */
    public function typyAPokojeJsouSerazene(string $report): void
    {
        $ucastnik = self::prihlasenyUzivatel();
        dbQuery("UPDATE uzivatele_hodnoty SET statni_obcanstvi = 'SVK' WHERE id_uzivatele = $0", [
            0 => $ucastnik->id(),
        ]);
        $this->kupNoc($ucastnik, $this->typPokoje('zz-pokoj', 'Postel zz'), 'zz-pokoj-ct', 'čtvrtek', 1);
        $this->kupNoc($ucastnik, $this->typPokoje('aa-pokoj', 'Postel aa'), 'aa-pokoj-pa', 'pátek', 2);
        $this->pridelPokoj($ucastnik, 1, '202');
        $this->pridelPokoj($ucastnik, 2, '101');

        $radek = $this->radekReportu($report, $ucastnik);

        self::assertSame('aa-pokoj,zz-pokoj', $radek['typ']);
        self::assertSame('101,202', $radek['pokoj']);
    }

    private function typPokoje(string $kodTypu, string $nazev): int
    {
        dbQuery(
            "INSERT INTO shop_predmety SET nazev = $0, kod_predmetu = $1, cena_aktualni = 100, stav = 1, popis = ''",
            [
                0 => $nazev,
                1 => $kodTypu . '-typ',
            ],
        );
        $idProduktu = (int) dbInsertId();
        dbQuery(
            'INSERT INTO product_product_tag (product_id, tag_id) SELECT $0, id FROM product_tag WHERE code = $1',
            [
                0 => $idProduktu,
                1 => ProductTagCode::UBYTOVANI->value,
            ],
        );

        return $idProduktu;
    }

    private function kupNoc(\Uzivatel $ucastnik, int $idTypuPokoje, string $kod, string $noc, int $den): void
    {
        dbQuery(
            'INSERT INTO product_variant (product_id, name, code, accommodation_day, position, state) VALUES ($0, $1, $2, $3, $3, 1)',
            [
                0 => $idTypuPokoje,
                1 => $noc,
                2 => $kod,
                3 => $den,
            ],
        );
        dbQuery(
            'INSERT INTO shop_nakupy (id_uzivatele, variant_id, rok, cena_nakupni, datum) VALUES ($0, $1, $2, 100, NOW())',
            [
                0 => $ucastnik->id(),
                1 => dbInsertId(),
                2 => ROCNIK,
            ],
        );
    }

    private function pridelPokoj(\Uzivatel $ucastnik, int $den, string $pokoj): void
    {
        dbQuery(
            'INSERT INTO ubytovani (id_uzivatele, den, pokoj, rok) VALUES ($0, $1, $2, $3)',
            [
                0 => $ucastnik->id(),
                1 => $den,
                2 => $pokoj,
                3 => ROCNIK,
            ],
        );
    }

    /**
     * Runs the report's own query, so the test follows any later edit of the script.
     *
     * @return array<string, string|null>
     */
    private function radekReportu(string $report, \Uzivatel $ucastnik): array
    {
        $zdroj = file_get_contents(self::REPORTY . $report);
        self::assertNotFalse($zdroj);
        self::assertSame(1, preg_match('~dbQuery\(<<<SQL\n(.*?)\nSQL,~s', $zdroj, $shody), "Dotaz v {$report} nejde najít");

        $vysledek = dbQuery($shody[1], [
            0 => Role::PRIHLASEN_NA_LETOSNI_GC,
            1 => ROCNIK,
            2 => ProductTagCode::UBYTOVANI->value,
        ]);
        while ($radek = $vysledek->fetch(\PDO::FETCH_ASSOC)) {
            if ((int) $radek['id_uzivatele'] === $ucastnik->id()) {
                return $radek;
            }
        }
        self::fail("Účastník chybí v {$report}");
    }
}
