<?php

declare(strict_types=1);

namespace Gamecon\Tests\Admin;

use Gamecon\Admin\FormularPracovnihoUzivatele;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Skryté pole pracovního uživatele se doplní do každého POST formuláře stránky, a jen do nich.
 * Bez pracovního uživatele má pole hodnotu 0.
 */
class DoplneniPolePracovnihoUzivateleTest extends TestCase
{
    private const string POLE = '<input type="hidden" name="idPracovnihoUzivatele" value="0">';

    #[DataProvider('formulare')]
    public function testDoplniPoleJenDoPostFormularu(string $html, string $ocekavane): void
    {
        self::assertSame($ocekavane, FormularPracovnihoUzivatele::doplnDoFormularu($html, null));
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function formulare(): \Generator
    {
        yield 'POST formulář' => [
            '<form method="post" class="clearfix"><input name="a"></form>',
            '<form method="post" class="clearfix">' . self::POLE . '<input name="a"></form>',
        ];
        yield 'POST velkými písmeny, apostrofy' => [
            "<form id='x' METHOD='POST'></form>",
            "<form id='x' METHOD='POST'>" . self::POLE . '</form>',
        ];
        yield 'znak > v hodnotě atributu' => [
            '<form method="post" onsubmit="return castka > 0">',
            '<form method="post" onsubmit="return castka > 0">' . self::POLE,
        ];
        yield 'GET formulář zůstane' => [
            '<form method="get"><input name="pokoj"></form>',
            '<form method="get"><input name="pokoj"></form>',
        ];
        yield 'formulář bez method je GET' => [
            '<form action="hledat"></form>',
            '<form action="hledat"></form>',
        ];
        yield 'data-method není method' => [
            '<form method="get" data-method="post"></form>',
            '<form method="get" data-method="post"></form>',
        ];
        yield 'značka, která jen začíná na form' => [
            '<formular method="post"></formular>',
            '<formular method="post"></formular>',
        ];
        yield 'všechny formuláře stránky' => [
            '<form method="post"></form><p>mezi</p><form method="post"></form>',
            '<form method="post">' . self::POLE . '</form><p>mezi</p><form method="post">' . self::POLE . '</form>',
        ];
        // prohlížeč hodnotu ukončí první uvozovkou a apostrof za ní čte jako název atributu, ne jako začátek hodnoty
        yield 'uvozovka navíc v hodnotě atributu' => [
            '<form method="post" onsubmit="return confirm(\'Placka 2"\')"><input name="a"></form>'
            . '<script>if (castka > 0) {}</script><form method=\'post\'></form>',
            '<form method="post" onsubmit="return confirm(\'Placka 2"\')">' . self::POLE . '<input name="a"></form>'
            . '<script>if (castka > 0) {}</script><form method=\'post\'>' . self::POLE . '</form>',
        ];
        yield 'method jen v hodnotě jiného atributu' => [
            '<form method="get" action="?method=post" onsubmit="this.method=\'post\'"></form>',
            '<form method="get" action="?method=post" onsubmit="this.method=\'post\'"></form>',
        ];
        yield 'method bez uvozovek a s entitou' => [
            '<form method=post></form><form method="&#112;ost"></form>',
            '<form method=post>' . self::POLE . '</form><form method="&#112;ost">' . self::POLE . '</form>',
        ];
        yield 'z opakovaného atributu platí první' => [
            '<form method="get" method="post"></form>',
            '<form method="get" method="post"></form>',
        ];
        yield 'method s mezerou není post' => [
            '<form method=" post"></form>',
            '<form method=" post"></form>',
        ];
        yield 'tlačítko s formmethod pošle POST i GET formulář' => [
            '<form method="get"><button formmethod="post">Uložit</button><button>Hledat</button></form>',
            '<form method="get">' . self::POLE . '<button formmethod="post">Uložit</button><button>Hledat</button></form>',
        ];
        yield 'tlačítko jiného formuláře' => [
            '<input type="submit" form="jinde" formmethod="POST">',
            '<input type="hidden" name="idPracovnihoUzivatele" value="0" form="jinde"><input type="submit" form="jinde" formmethod="POST">',
        ];
        yield 'formulář, který s pracovním uživatelem nepracuje' => [
            '<form method="post" data-bez-pracovniho-uzivatele></form>',
            '<form method="post" data-bez-pracovniho-uzivatele></form>',
        ];
        yield 'značka v komentáři, skriptu, textovém poli nebo atributu není značka' => [
            '<!-- <form method="post"> --><script>const f = "<form method=\'post\'>";</script>'
            . '<textarea><form method="post"></textarea><div title="<form method=post>"></div>',
            '<!-- <form method="post"> --><script>const f = "<form method=\'post\'>";</script>'
            . '<textarea><form method="post"></textarea><div title="<form method=post>"></div>',
        ];
        yield 'za skriptem se hledá dál' => [
            '<SCRIPT type="module">x = "</scriptx>"</SCRIPT ><form method="post">',
            '<SCRIPT type="module">x = "</scriptx>"</SCRIPT ><form method="post">' . self::POLE,
        ];
    }

    public function testPoleMimoFormularNeseAtributForm(): void
    {
        self::assertSame(
            '<input type="hidden" name="idPracovnihoUzivatele" value="0" form="prodej-mrizka-form">',
            FormularPracovnihoUzivatele::getSkrytePole(null, 'prodej-mrizka-form'),
        );
    }
}
