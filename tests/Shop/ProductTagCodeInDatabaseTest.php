<?php

declare(strict_types=1);

namespace Gamecon\Tests\Shop;

use App\Enum\ProductTagCode;
use Gamecon\Tests\Db\AbstractTestDb;

class ProductTagCodeInDatabaseTest extends AbstractTestDb
{
    /**
     * A code renamed in the enum alone matches no product, and nothing fails: the restriction or
     * the sub-tag just stops applying.
     *
     * @test
     */
    public function kazdyKodStitkuMaRadekVDatabazi(): void
    {
        $kodyVDatabazi = dbOneArray('SELECT code FROM product_tag');
        $kodyVyctu = array_map(static fn (ProductTagCode $kod): string => $kod->value, ProductTagCode::cases());

        self::assertSame([], array_values(array_diff($kodyVyctu, $kodyVDatabazi)));
    }

    /**
     * The shirt tags were renamed in place. New rows next to the old ones would leave the
     * products linked to the old tag, and a restricted shirt without its tag is orderable by anyone.
     *
     * @test
     */
    public function barevnaJmenaTrickovychStitkuUzNeexistuji(): void
    {
        self::assertSame([], dbOneArray("SELECT code FROM product_tag WHERE code IN ('tricko_modre', 'tricko_cervene')"));
    }
}
