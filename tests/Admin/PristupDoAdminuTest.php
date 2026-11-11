<?php

declare(strict_types=1);

namespace Gamecon\Tests\Admin;

use Gamecon\Admin\PristupDoAdminu;
use Gamecon\Pravo;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../admin/scripts/admin-menu.php';

/**
 * Do adminu (API, zvláštní stránky, výběr vzhledu) smí ten, kdo má právo aspoň jedné stránky. Seznam práv musí
 * odpovídat hlavičkám modulů, jinak by stránka s novým právem pustila dovnitř, ale API a omnibox by ho odmítly.
 */
class PristupDoAdminuTest extends TestCase
{
    private const string MODULY_DIR = __DIR__ . '/../../admin/scripts/modules';

    public function testNeprihlasenyDoAdminuNesmi(): void
    {
        self::assertFalse(PristupDoAdminu::isPovolen(null));
    }

    /**
     * @param list<int> $prava
     */
    #[DataProvider('prava')]
    public function testDoAdminuSmiJenSPravemStranky(array $prava, bool $ocekavane): void
    {
        $prihlaseny = $this->createPartialMock(\Uzivatel::class, ['prava']);
        $prihlaseny->method('prava')->willReturn($prava);

        self::assertSame($ocekavane, PristupDoAdminu::isPovolen($prihlaseny));
    }

    /**
     * @return \Generator<string, array{list<int>, bool}>
     */
    public static function prava(): \Generator
    {
        yield 'účastník bez práv' => [[], false];
        yield 'jen práva mimo admin' => [[Pravo::PORADANI_AKTIVIT, Pravo::JIDLO_ZDARMA], false];
        yield 'přepnutí na uživatele samo stránku neotevře' => [[Pravo::PREPNUTI_NA_UZIVATELE], false];
        yield 'vypravěč s Moje aktivity' => [[Pravo::ADMINISTRACE_MOJE_AKTIVITY], true];
        // loga jsou jen podstránka Webu, hlavní menu je nezobrazí
        yield 'jen loga' => [[Pravo::ADMINISTRACE_WEB_LOGA], true];
        yield 'infopult' => [[Pravo::ADMINISTRACE_INFOPULT], true];
    }

    /**
     * @param list<int> $prava
     */
    #[DataProvider('pravaModulu')]
    public function testPravoKazdeStrankyOtviraAdmin(string $modul, array $prava): void
    {
        foreach ($prava as $pravo) {
            self::assertContains(
                $pravo,
                PristupDoAdminu::PRAVA,
                "Právo {$pravo} z hlavičky modulu {$modul} chybí v PristupDoAdminu::PRAVA",
            );
        }
    }

    /**
     * Práva z hlaviček hlavního menu i podmenu; Nápověda vyjmenuje svá práva hlavičkou `prava:`, takže její `pravo: 999`
     * (jen pozice v menu) se nepočítá.
     *
     * @return \Generator<string, array{string, list<int>}>
     */
    public static function pravaModulu(): \Generator
    {
        foreach ((new \AdminMenu(self::MODULY_DIR . '/'))->pole() as $url => $polozka) {
            yield $url => [$url, $polozka['prava']];
        }
        foreach (glob(self::MODULY_DIR . '/*', GLOB_ONLYDIR) as $adresarStranky) {
            $stranka = basename($adresarStranky);
            if (str_starts_with($stranka, '_')) {
                continue;
            }
            foreach ((new \AdminMenu($adresarStranky . '/', true))->pole() as $url => $polozka) {
                yield "{$stranka}/{$url}" => ["{$stranka}/{$url}", $polozka['prava']];
            }
        }
    }

    public function testVerejneStrankyJsouZvlastniStrankySeZduvodnenim(): void
    {
        foreach (PristupDoAdminu::VEREJNE_STRANKY as $stranka => $duvod) {
            self::assertFileExists(__DIR__ . "/../../admin/scripts/zvlastni/{$stranka}.php");
            self::assertNotSame('', trim($duvod), "Veřejná stránka '{$stranka}' nemá zdůvodnění");
            self::assertTrue(PristupDoAdminu::isVerejnaStranka($stranka));
        }
        self::assertFalse(PristupDoAdminu::isVerejnaStranka('ajax-omnibox'));
        self::assertFalse(PristupDoAdminu::isVerejnaStranka(''));
    }
}
