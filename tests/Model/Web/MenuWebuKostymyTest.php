<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Web;

use Gamecon\Tests\Db\AbstractUzivatelTestDb;
use Gamecon\Web\MenuWebu;

/**
 * Odkazy na půjčovnu kostýmů v menu webu jen se zveřejněným katalogem, jinak by vedly na 404.
 */
class MenuWebuKostymyTest extends AbstractUzivatelTestDb
{
    protected static bool $disableStrictTransTables = true;

    public function testZverejnenyKatalogMaOdkazVInformacich(): void
    {
        $menu = MenuWebu::html(null, katalogKostymuZverejnen: true);

        self::assertStringContainsString('href="kostymy"', $menu);
        self::assertStringNotContainsString('href="kostymy/moje"', $menu, 'Nepřihlášený vlastní rezervace nemá');
    }

    public function testPrihlasenyMaOdkazNaSvojeKostymy(): void
    {
        $menu = MenuWebu::html(self::prihlasenyUzivatel(), katalogKostymuZverejnen: true);

        self::assertStringContainsString('href="kostymy"', $menu);
        self::assertStringContainsString('href="kostymy/moje"', $menu);
    }

    public function testNezverejnenyKatalogVMenuNeni(): void
    {
        foreach ([
            MenuWebu::html(self::prihlasenyUzivatel(), katalogKostymuZverejnen: false),
            MenuWebu::html(null),
        ] as $menu) {
            self::assertStringNotContainsString('href="kostymy', $menu);
        }
    }
}
