<?php

declare(strict_types=1);

namespace Gamecon\Tests\Shop;

use App\Enum\ProductTagCode;
use Gamecon\Shop\Predmet;
use Gamecon\Tests\Db\AbstractTestDb;

/**
 * `letosniPredmet()` si výsledek pamatuje ve statickém poli klíčovaném kódem a ročníkem.
 * Statika přežije reset databáze, takže bez úklidu vrací předmět, který v ní už není.
 */
class PredmetSmazCacheTest extends AbstractTestDb
{
    private const ROCNIK = 2026;

    protected static array $initQueries = [
        <<<SQL
INSERT INTO shop_predmety SET id_predmetu = 46630, nazev = 'Placka na smazani', kod_predmetu = 'placka_smazana_46630', cena_aktualni = 40, stav = 1, nabizet_do = NOW(), kusu_vyrobeno = 100
SQL,
        [
            <<<SQL
INSERT INTO product_product_tag (product_id, tag_id)
SELECT shop_predmety.id_predmetu, product_tag.id
FROM shop_predmety
JOIN product_tag ON product_tag.code = $0
WHERE shop_predmety.id_predmetu = 46630
SQL,
            [
                0 => ProductTagCode::PREDMET->value,
            ],
        ],
    ];

    /**
     * @test
     */
    public function smazCacheZahodiIZapamatovanyLetosniPredmet(): void
    {
        self::assertNotNull(Predmet::letosniPlacka(self::ROCNIK), 'Placka se má najít');

        dbQuery('DELETE FROM product_product_tag WHERE product_id = 46630');
        dbQuery('DELETE FROM shop_predmety WHERE id_predmetu = 46630');

        Predmet::smazCache();

        self::assertNull(
            Predmet::letosniPlacka(self::ROCNIK),
            'Po smazání z databáze a smazCache() se placka nesmí vrátit z cache',
        );
    }
}
