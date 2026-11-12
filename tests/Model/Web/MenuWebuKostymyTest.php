<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Web;

use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
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
        $menu = MenuWebu::html(null, SystemoveNastaveni::zGlobals(), katalogKostymuZverejnen: true);

        self::assertStringContainsString('href="kostymy"', $menu);
        self::assertStringNotContainsString('href="kostymy/moje"', $menu, 'Nepřihlášený vlastní rezervace nemá');
    }

    public function testPrihlasenyMaOdkazNaSvojeKostymy(): void
    {
        $menu = MenuWebu::html(self::prihlasenyUzivatel(), SystemoveNastaveni::zGlobals(), katalogKostymuZverejnen: true);

        self::assertStringContainsString('href="kostymy"', $menu);
        self::assertStringContainsString('href="kostymy/moje"', $menu);
    }

    public function testNezverejnenyKatalogVMenuNeni(): void
    {
        foreach ([
            MenuWebu::html(self::prihlasenyUzivatel(), SystemoveNastaveni::zGlobals(), katalogKostymuZverejnen: false),
            MenuWebu::html(null, SystemoveNastaveni::zGlobals()),
        ] as $menu) {
            self::assertStringNotContainsString('href="kostymy', $menu);
        }
    }
}
