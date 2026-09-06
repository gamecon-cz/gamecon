<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Uzivatel;

use Gamecon\Cas\DateTimeImmutableStrict;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Uzivatel\Cenik;
use Gamecon\Uzivatel\Finance;

/**
 * discount_rule je editovatelná data, ale čítače nároků jsou v Ceníku natvrdo. Nové
 * omezené pravidlo bez čítače by se uplatnilo na každou položku znovu — tiše, bez
 * chyby. Radši spadnout než rozdat slevu vícekrát.
 */
class CenikNeznamePravidloTest extends AbstractTestDb
{
    protected static array $initQueries = [
        <<<SQL
INSERT INTO uzivatele_hodnoty SET id_uzivatele = 339, login_uzivatele = 'ProbeGuard', jmeno_uzivatele = 'Probe', prijmeni_uzivatele = 'Guard', email1_uzivatele = 'probe.guard@bio.org'
SQL,
        [
            <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 33900, nazev = 'Probe jidlo', kod_predmetu = CONCAT('probe_jidlo_', $0), cena_aktualni = 150, stav = 1, nabizet_do = NOW(), kusu_vyrobeno = 10, ubytovani_den = 1
SQL,
            [
                0 => ROCNIK,
            ],
        ],
        "INSERT INTO product_product_tag (product_id, tag_id) SELECT 33900, id FROM product_tag WHERE code = 'jidlo'",
    ];

    /**
     * @test
     */
    public function neznamePravidloSOmezenimSpadne(): void
    {
        \dbQuery(
            "INSERT INTO discount_rule (code, name, required_right, priority, parameters, year, active)
             VALUES ('probe_bez_citace', 'Probe', 1003, 5,
                     '{\"scope\":\"tag\",\"effect\":\"free\",\"tag\":\"jidlo\",\"maxQuantity\":1}', \$0, 1)",
            [
                0 => ROCNIK,
            ],
        );

        $systemoveNastaveni = SystemoveNastaveni::zGlobals(ROCNIK, new DateTimeImmutableStrict());
        $uzivatel = \Uzivatel::zIdUrcite(339);
        $cenik = new Cenik($uzivatel, new Finance($uzivatel, 0, $systemoveNastaveni), $systemoveNastaveni);

        $radek = \dbOneLine('SELECT * FROM shop_predmety_s_typem WHERE id_predmetu = 33900');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('nemá čítač');
        $cenik->cena($radek);
    }
}
