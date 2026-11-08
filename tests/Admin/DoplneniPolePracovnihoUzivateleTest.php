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
    }

    public function testPoleMimoFormularNeseAtributForm(): void
    {
        self::assertSame(
            '<input type="hidden" name="idPracovnihoUzivatele" value="0" form="prodej-mrizka-form">',
            FormularPracovnihoUzivatele::getSkrytePole(null, 'prodej-mrizka-form'),
        );
    }
}
