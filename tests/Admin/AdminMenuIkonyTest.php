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

    #[DataProvider('provideSubmenuPolozky')]
    public function testKazdaPolozkaSubmenuMaIkonuZFontAwesome(array $polozka): void
    {
        self::assertNotNull($polozka['ikona'], "Chybí hlavička `ikona:` v {$polozka['soubor']}");
        self::assertTrue(
            self::existujeIkonaFontAwesome($polozka['ikona']),
            "Ikona {$polozka['ikona']} z {$polozka['soubor']} není ve Font Awesome 6.1.1",
        );
    }

    #[DataProvider('provideSubmenuPolozky')]
    public function testNazevPolozkyNeobsahujeEmoji(array $polozka): void
    {
        self::assertDoesNotMatchRegularExpression(
            '~\p{Extended_Pictographic}~u',
            $polozka['nazev'],
            'Název patří do textu tlačítka i do názvu stránky, ikonu dej do hlavičky `ikona:`',
        );
    }

    public function testIkonyVeSablonachAJavaScriptuExistujiVeFontAwesome(): void
    {
        $chybejici = [];
        $soubory = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::ROOT_DIR . '/admin', \FilesystemIterator::SKIP_DOTS));
        $modely = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::ROOT_DIR . '/model', \FilesystemIterator::SKIP_DOTS));
        foreach ([...$soubory, ...$modely] as $soubor) {
            $cesta = $soubor->getPathname();
            if (! preg_match('~\.(xtpl|php|js|html)$~', $cesta) || preg_match('~/(files/ui|font-awesome[^/]*)/|\.min\.~', $cesta)) {
                continue;
            }
            preg_match_all('~\bfa-(?:solid|regular|brands)\s+(?:fa-fw\s+)?fa-(?!fw\b)([a-z0-9]+(?:-[a-z0-9]+)*)(?![a-z0-9{-])~', (string) file_get_contents($cesta), $nalezene);
            foreach (array_unique($nalezene[1]) as $ikona) {
                if (! self::existujeIkonaFontAwesome($ikona)) {
                    $chybejici[] = "{$ikona} v " . substr($cesta, strlen(self::ROOT_DIR) + 1);
                }
            }
        }

        self::assertSame([], $chybejici, 'Tyto ikony nejsou ve Font Awesome 6.1.1 a nevykreslí se');
    }

    private static function existujeIkonaFontAwesome(string $ikona): bool
    {
        self::$fontAwesomeCss ??= (string) file_get_contents(self::FONT_AWESOME_CSS);

        return str_contains(self::$fontAwesomeCss, ".fa-{$ikona}::before");
    }
}
