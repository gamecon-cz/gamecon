<?php

declare(strict_types=1);

namespace Gamecon\Tests\Admin;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../admin/scripts/admin-menu.php';

class AdminMenuIkonyTest extends TestCase
{
    private const string ROOT_DIR = __DIR__ . '/../..';
    private const string MODULY_DIR = self::ROOT_DIR . '/admin/scripts/modules';
    private const string FONT_AWESOME_CSS = self::ROOT_DIR . '/admin/files/design/font-awesome-6.1.1/css/all.css';
    private const int DELKA_HLAVICKY = 2048;
    private const string NAZEV_V_HLAVICCE = '~\*[ \t]*(?:submenu_)?nazev:[ \t]*(.*)~';

    private static ?string $fontAwesomeCss = null;

    public static function provideSubmenuPolozky(): iterable
    {
        foreach (glob(self::MODULY_DIR . '/*', GLOB_ONLYDIR) as $moduleDir) {
            $stranka = basename($moduleDir);
            if (str_starts_with($stranka, '_')) {
                continue;
            }
            $submenu = (new \AdminMenu($moduleDir . '/', true))->pole();
            foreach ($submenu as $url => $polozka) {
                yield "{$stranka}/{$url}" => [$polozka];
            }
        }
    }

    /**
     * Hlavní menu moderního vzhledu kreslí ikonu z hlavičky `ikona:`, klasické má vlastní obrázky.
     */
    public static function provideHlavniMenuPolozky(): iterable
    {
        foreach ((new \AdminMenu(self::MODULY_DIR . '/'))->pole() as $url => $polozka) {
            yield $url => [$polozka];
        }
    }

    public static function provideModulySNazvem(): iterable
    {
        foreach (glob(self::MODULY_DIR . '/{*.php,*/*.php}', GLOB_BRACE) as $soubor) {
            if (str_starts_with(basename($soubor), '_') || str_starts_with(basename(dirname($soubor)), '_')) {
                continue;
            }
            if (preg_match_all(self::NAZEV_V_HLAVICCE, self::getHlavicka($soubor), $nazvy) > 0) {
                yield substr($soubor, strlen(self::MODULY_DIR) + 1) => [$nazvy[1]];
            }
        }
    }

    #[DataProvider('provideSubmenuPolozky')]
    public function testKazdaPolozkaSubmenuMaIkonuZFontAwesome(array $polozka): void
    {
        self::assertNotNull($polozka['ikona'], "Chybí hlavička `ikona:` v {$polozka['soubor']}");
        self::assertTrue(
            self::isIkonaVeFontAwesome($polozka['ikona']),
            "Ikona {$polozka['ikona']} z {$polozka['soubor']} není ve Font Awesome 6.1.1",
        );
    }

    #[DataProvider('provideHlavniMenuPolozky')]
    public function testKazdaPolozkaHlavnihoMenuMaIkonuZFontAwesome(array $polozka): void
    {
        self::assertNotNull($polozka['ikona'], "Chybí hlavička `ikona:` v {$polozka['soubor']}");
        self::assertTrue(
            self::isIkonaVeFontAwesome($polozka['ikona']),
            "Ikona {$polozka['ikona']} z {$polozka['soubor']} není ve Font Awesome 6.1.1",
        );
    }

    /**
     * @param list<string> $nazvy values of `nazev:` and `submenu_nazev:` from the module header
     */
    #[DataProvider('provideModulySNazvem')]
    public function testNazvyVHlavicceNeobsahujiEmoji(array $nazvy): void
    {
        foreach ($nazvy as $nazev) {
            self::assertDoesNotMatchRegularExpression(
                '~\p{Extended_Pictographic}~u',
                $nazev,
                'Název patří do textu tlačítka i do názvu stránky, ikonu dej do hlavičky `ikona:`',
            );
        }
    }

    private static function getHlavicka(string $soubor): string
    {
        // mb_strcut does not split a multibyte character, a broken one would make the /u pattern fail silently
        return mb_strcut((string) file_get_contents($soubor), 0, self::DELKA_HLAVICKY);
    }

    private static function isIkonaVeFontAwesome(string $ikona): bool
    {
        self::$fontAwesomeCss ??= (string) file_get_contents(self::FONT_AWESOME_CSS);

        return str_contains(self::$fontAwesomeCss, ".fa-{$ikona}::before");
    }
}
