<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Report;

use App\Enum\ProductTagCode;
use Gamecon\Shop\Predmet;
use Gamecon\Tests\Db\AbstractTestDb;

/**
 * Rozpad placek na letošní a staré stojí na tom, že se letošní dá najít. Test volá
 * hledání doopravdy, ne přes předem podstrčené id — jinak projde i ve chvíli, kdy
 * metoda vůbec neexistuje a report padá na fatální chybu.
 */
class PredmetLetosniPlackaTest extends AbstractTestDb
{
    private const ROCNIK = 2026;

    private const ID_LETOSNI_PLACKY = 46620;

    private const ID_STARE_PLACKY = 46621;

    /**
     * Obě placky mají shodný `model_rok`: staré kolekce se každý rok přeregistrují,
     * aby se daly doprodat. Letošní se pozná tím, že ji v dřívějších ročnících nikdo
     * neměl koupenou — proto má stará placka nákup z loňska.
     */
    protected static array $initQueries = [
        <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 46620, nazev = 'Placka letošní', kod_predmetu = 'placka_letosni_46620', cena_aktualni = 40, stav = 1, nabizet_do = NOW(), kusu_vyrobeno = 100
SQL,
        <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 46621, nazev = 'Placka stará', kod_predmetu = 'placka_stara_46621', cena_aktualni = 40, stav = 1, nabizet_do = NOW(), kusu_vyrobeno = 100
SQL,
        [
            <<<SQL
INSERT INTO product_product_tag (product_id, tag_id)
SELECT shop_predmety.id_predmetu, product_tag.id
FROM shop_predmety
JOIN product_tag ON product_tag.code = $0
WHERE shop_predmety.id_predmetu BETWEEN 46620 AND 46621
SQL,
            [
                0 => ProductTagCode::PREDMET->value,
            ],
        ],
        <<<SQL
INSERT INTO shop_nakupy(id_uzivatele, id_predmetu, rok, cena_nakupni) VALUES (1, 46621, 2025, 40)
SQL,
    ];

    /**
     * @test
     */
    public function letosniPlackaSeNajdePodleModeluARoku(): void
    {
        $placka = Predmet::letosniPlacka(self::ROCNIK);

        self::assertNotNull($placka, 'Pro letošní ročník se nenašla žádná placka');
        self::assertSame(
            self::ID_LETOSNI_PLACKY,
            (int) $placka->id(),
            'Vybrala se placka, kterou už někdo koupil v dřívějším ročníku',
        );
        self::assertNotSame(self::ID_STARE_PLACKY, (int) $placka->id());
    }
}
