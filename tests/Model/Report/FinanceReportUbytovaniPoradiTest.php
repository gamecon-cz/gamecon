<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Report;

use Gamecon\Role\Role;
use Gamecon\Shop\TypPredmetu;
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
        // Bought Friday first, so the purchase order differs from the day order.
        $this->kupNoc($ucastnik, 'zz-pokoj-pa', 'Postel pátek', 2);
        $this->kupNoc($ucastnik, 'zz-pokoj-st', 'Postel středa', 0);

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
        $this->kupNoc($ucastnik, 'zz-pokoj-ct', 'Postel čtvrtek', 1);
        $this->kupNoc($ucastnik, 'aa-pokoj-pa', 'Postel pátek', 2);
        $this->pridelPokoj($ucastnik, 1, '202');
        $this->pridelPokoj($ucastnik, 2, '101');

        $radek = $this->radekReportu($report, $ucastnik);

        self::assertSame('aa-pokoj,zz-pokoj', $radek['typ']);
        self::assertSame('101,202', $radek['pokoj']);
    }

    private function kupNoc(\Uzivatel $ucastnik, string $kod, string $nazev, int $den): void
    {
        dbQuery(
            'INSERT INTO shop_predmety SET nazev = $0, model_rok = $1, kod_predmetu = $2, cena_aktualni = 100, stav = 1, typ = $3, ubytovani_den = $4',
            [
                0 => $nazev,
                1 => ROCNIK,
                2 => $kod,
                3 => TypPredmetu::UBYTOVANI,
                4 => $den,
            ],
        );
        dbQuery(
            'INSERT INTO shop_nakupy (id_uzivatele, id_predmetu, rok, cena_nakupni, datum) VALUES ($0, $1, $2, 100, NOW())',
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
            2 => TypPredmetu::UBYTOVANI,
        ]);
        while ($radek = mysqli_fetch_assoc($vysledek)) {
            if ((int) $radek['id_uzivatele'] === $ucastnik->id()) {
                return $radek;
            }
        }
        self::fail("Účastník chybí v {$report}");
    }
}
