<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Uzivatel;

use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Uzivatel\Finance;

class FinanceNeznamyTypPredmetuTest extends AbstractTestDb
{
    protected static array $initQueries = [
        <<<SQL
INSERT INTO uzivatele_hodnoty SET id_uzivatele = 334, login_uzivatele = 'KoupimDivne', jmeno_uzivatele = 'Koupim', prijmeni_uzivatele = 'Divne', email1_uzivatele = 'koupim.divne@dot.com'
SQL,
        [
            <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 33313, nazev = 'nesmysl', kod_predmetu = CONCAT('nesmysl_', $0), cena_aktualni = 0, stav = 1, nabizet_do = NOW(), kusu_vyrobeno = 0
SQL,
            [
                0 => ROCNIK,
            ],
        ],
        // No product_product_tag INSERT — product has no tag, so view returns typ=NULL
        [
            <<<SQL
INSERT INTO shop_nakupy(id_uzivatele, id_predmetu, rok, cena_nakupni)
SELECT 334, id_predmetu, $0, 0 FROM shop_predmety WHERE id_predmetu IN (33313)
SQL,
            [
                0 => ROCNIK,
            ],
        ],
    ];

    /**
     * @test
     */
    public function neznamyTypPredmetuHodiExcepton()
    {
        $this->expectException(\RuntimeException::class);
        $finance = new Finance($this->dejUzivateleSNeznamymTypemPredmetu(), 0, SystemoveNastaveni::zGlobals());
        $finance->cenaPredmetu();
    }

    private function dejUzivateleSNeznamymTypemPredmetu(): \Uzivatel
    {
        return \Uzivatel::zIdUrcite(334);
    }
}
