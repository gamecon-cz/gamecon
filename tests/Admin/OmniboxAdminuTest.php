<?php

declare(strict_types=1);

namespace Gamecon\Tests\Admin;

use Gamecon\Admin\OmniboxAdminu;
use Gamecon\Pravo;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Omnibox vydá kontakty a zůstatky kohokoli, proto jen tomu, komu ho admin nabízí: výběr uživatele pro práci, reporty,
 * přepnutí na uživatele, a infopulťákovi jen vypravěče a partnery. Přes administraci to ověřit nejde: testy běží na
 * locale, kde se na kohokoli smí přepnout každý, a tak omnibox smí každý.
 */
class OmniboxAdminuTest extends TestCase
{
    public function testNeprihlasenyOmniboxNesmi(): void
    {
        self::assertFalse(OmniboxAdminu::isPovolen(null));
    }

    /**
     * @param list<int> $prava
     */
    #[DataProvider('prihlaseni')]
    public function testOmniboxSmiJenTenKomuHoAdminNabizi(
        array $prava,
        bool $muzePrepnout,
        bool $jeInfopultak,
        bool $ocekavanePovolen,
        ?array $ocekavaneRole,
    ): void {
        $prihlaseny = $this->createPartialMock(\Uzivatel::class, ['prava', 'muzePrepnoutNaJinehoUzivatele', 'jeInfopultak']);
        $prihlaseny->method('prava')->willReturn($prava);
        $prihlaseny->method('muzePrepnoutNaJinehoUzivatele')->willReturn($muzePrepnout);
        $prihlaseny->method('jeInfopultak')->willReturn($jeInfopultak);

        self::assertSame($ocekavanePovolen, OmniboxAdminu::isPovolen($prihlaseny));
        if ($ocekavanePovolen) {
            self::assertSame($ocekavaneRole, OmniboxAdminu::getRoleHledanych($prihlaseny));
        }
    }

    /**
     * @return \Generator<string, array{list<int>, bool, bool, bool, list<int>|null}>
     */
    public static function prihlaseni(): \Generator
    {
        yield 'účastník bez práv' => [[], false, false, false, null];
        yield 'vypravěč s Moje aktivity' => [[Pravo::ADMINISTRACE_MOJE_AKTIVITY], false, false, false, null];
        yield 'jen loga' => [[Pravo::ADMINISTRACE_WEB_LOGA], false, false, false, null];
        yield 'infopult' => [[Pravo::ADMINISTRACE_INFOPULT], false, false, true, null];
        yield 'finance' => [[Pravo::ADMINISTRACE_FINANCE], false, false, true, null];
        yield 'reporty' => [[Pravo::ADMINISTRACE_REPORTY], false, false, true, null];
        yield 'přepnutí na uživatele bez práva stránky' => [[], true, false, true, null];
        yield 'infopulťák bez práv najde jen vypravěče a partnery' => [
            [],
            false,
            true,
            true,
            OmniboxAdminu::ROLE_PRO_INFOPULTAKA,
        ];
        yield 'infopulťák s právem infopultu najde kohokoli' => [[Pravo::ADMINISTRACE_INFOPULT], false, true, true, null];
    }
}
