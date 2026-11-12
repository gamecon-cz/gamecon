<?php

declare(strict_types=1);

namespace Gamecon\Tests\Admin;

use Gamecon\Admin\PracovniUzivatel;
use Gamecon\Pravo;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pracovního uživatele smí mít jen přihlášený s právem stránky, která s ním pracuje, tedy ten, komu admin výběr
 * uživatele nabízí. Přes skutečnou administraci to ověřuje @see \Gamecon\Tests\web\PracovniUzivatelVAdminuTest.
 */
class PracovniUzivatelTest extends TestCase
{
    public function testNeprihlasenyPracovnihoUzivateleMitNesmi(): void
    {
        self::assertFalse(PracovniUzivatel::isPovolen(null));
        self::assertNull(PracovniUzivatel::zSession(null));
    }

    /**
     * @param list<int> $prava
     */
    #[DataProvider('prava')]
    public function testPracovnihoUzivateleSmiJenSPravemStrankySNim(array $prava, bool $ocekavane): void
    {
        $prihlaseny = $this->createPartialMock(\Uzivatel::class, ['prava']);
        $prihlaseny->method('prava')->willReturn($prava);

        self::assertSame($ocekavane, PracovniUzivatel::isPovolen($prihlaseny));
    }

    /**
     * @return \Generator<string, array{list<int>, bool}>
     */
    public static function prava(): \Generator
    {
        yield 'účastník bez práv' => [[], false];
        yield 'vypravěč s Moje aktivity' => [[Pravo::ADMINISTRACE_MOJE_AKTIVITY], false];
        yield 'reporty a statistiky' => [[Pravo::ADMINISTRACE_REPORTY, Pravo::ADMINISTRACE_STATISTIKY], false];
        yield 'infopult' => [[Pravo::ADMINISTRACE_INFOPULT], true];
        yield 'ubytování (stránka Uživatel)' => [[Pravo::ADMINISTRACE_UBYTOVANI], true];
        yield 'práva' => [[Pravo::ADMINISTRACE_PRAVA], true];
        // CFO bez infopultu: odkazy z e-mailů a reportů, připsání platby
        yield 'finance a peníze bez infopultu' => [[Pravo::ADMINISTRACE_FINANCE, Pravo::ADMINISTRACE_PENIZE], true];
        yield 'jen peníze' => [[Pravo::ADMINISTRACE_PENIZE], true];
    }

    /**
     * Na aktivity a do týmu za pracovního uživatele přihlašuje jen infopult. Finance, Peníze, Práva a Uživatel
     * pracovního uživatele mají, ale za něj se přihlašovat nemají, i když jim admin otevře jeho program.
     *
     * @param list<int> $prava
     */
    #[DataProvider('pravaProgramu')]
    public function testZaPracovnihoUzivatelePrihlasujeJenInfopult(array $prava, bool $ocekavane): void
    {
        $prihlaseny = $this->createPartialMock(\Uzivatel::class, ['prava']);
        $prihlaseny->method('prava')->willReturn($prava);

        self::assertSame($ocekavane, PracovniUzivatel::isPovolenProgram($prihlaseny));
    }

    /**
     * @return \Generator<string, array{list<int>, bool}>
     */
    public static function pravaProgramu(): \Generator
    {
        yield 'infopult' => [[Pravo::ADMINISTRACE_INFOPULT], true];
        yield 'ubytování (stránka Uživatel)' => [[Pravo::ADMINISTRACE_UBYTOVANI], false];
        yield 'práva' => [[Pravo::ADMINISTRACE_PRAVA], false];
        yield 'finance a peníze' => [[Pravo::ADMINISTRACE_FINANCE, Pravo::ADMINISTRACE_PENIZE], false];
    }

    public function testNeprihlasenyZaNikohoNeprihlasuje(): void
    {
        self::assertFalse(PracovniUzivatel::isPovolenProgram(null));
    }

    #[DataProvider('id')]
    public function testIdZFormulareNeboOdkazu(mixed $hodnota, ?int $ocekavane): void
    {
        self::assertSame($ocekavane, PracovniUzivatel::parseId($hodnota));
    }

    /**
     * @return \Generator<string, array{mixed, ?int}>
     */
    public static function id(): \Generator
    {
        yield 'z odkazu' => ['123', 123];
        yield 'číslo' => [123, 123];
        yield 'chybí' => [null, null];
        yield 'prázdné' => ['', null];
        yield 'nula' => ['0', null];
        yield 'záporné' => ['-5', null];
        yield 'text' => ['abc', null];
        yield 'číslo s textem' => ['12abc', null];
        yield 'pole z ?pracovni_uzivatel[]=' => [['1'], null];
    }
}
