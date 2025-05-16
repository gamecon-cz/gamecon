<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Uzivatel;

use Gamecon\Exceptions\NeznamyTypPredmetu;
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
INSERT INTO shop_predmety SET id_predmetu = 33313, nazev = 'nesmysl', model_rok = $0, cena_aktualni = 0, stav = 1, nabizet_do = NOW(), kusu_vyrobeno = 0, typ = 8888
SQL,
            [
                0 => ROCNIK,
            ],
        ],
        [
            <<<SQL
INSERT INTO shop_nakupy(id_uzivatele, id_predmetu, rok, cena_nakupni)
SELECT 334, id_predmetu, $0, 0 FROM shop_predmety WHERE id_predmetu IN (33313)
SQL,
            [0 => ROCNIK],
        ],
    ];

    /**
     * @test
     */
    public function Neznamy_typ_predmetu_hodi_excepton()
    {
        $this->expectException(NeznamyTypPredmetu::class);
        $finance = new Finance($this->dejUzivateleSNeznamymTypemPredmetu(), 0, SystemoveNastaveni::vytvorZGlobals());
        $finance->cenaPredmetu();
    }

    private function dejUzivateleSNeznamymTypemPredmetu(): \Uzivatel
    {
        return \Uzivatel::zIdUrcite(334);
    }
}
