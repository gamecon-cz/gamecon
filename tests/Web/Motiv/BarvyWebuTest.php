<?php

declare(strict_types=1);

namespace Gamecon\Tests\Web\Motiv;

use Gamecon\Web\Motiv\BarvaWebuEnum;
use Gamecon\Web\Motiv\BarvyWebu;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BarvyWebuTest extends TestCase
{
    #[DataProvider('provideHodnoty')]
    public function testNormalizujeHodnotu(string $hodnota, ?string $ocekavana): void
    {
        self::assertSame($ocekavana, BarvyWebu::normalizujHodnotu($hodnota));
    }

    /**
     * @return array<string, array{0: string, 1: string|null}>
     */
    public static function provideHodnoty(): array
    {
        return [
            'tak jak má být'    => ['#a1b2c3', '#a1b2c3'],
            'velká písmena'     => ['#A1B2C3', '#a1b2c3'],
            'bez mřížky'        => ['a1b2c3', '#a1b2c3'],
            'krátký zápis'      => ['#abc', '#aabbcc'],
            'mezery okolo'      => ['  #a1b2c3 ', '#a1b2c3'],
            'prázdná'           => ['', null],
            'pět znaků'         => ['#a1b2c', null],
            'nehexové znaky'    => ['#zzzzzz', null],
            'průhlednost'       => ['#a1b2c3ff', null],
            'pokus o vložení'   => ['#fff;}body{display:none', null],
            'pojmenovaná barva' => ['red', null],
        ];
    }

    public function testVychoziNicNemeni(): void
    {
        $barvy = BarvyWebu::vychozi();

        self::assertTrue($barvy->jsouVychozi());
        self::assertSame('', $barvy->getCss());
        self::assertSame('{}', $barvy->toJson());
        self::assertSame('#e22630', $barvy->getHodnota(BarvaWebuEnum::CERVENA));
    }

    public function testDrziJenZmeneneBarvy(): void
    {
        $barvy = BarvyWebu::zPole([
            BarvaWebuEnum::CERVENA->value => '#0057B8',
            // the same as the default, so it is not a change
            BarvaWebuEnum::INKOUST->value => '#10111a',
            // not a colour, so the default stays
            BarvaWebuEnum::TEXT->value => 'černá',
            'neznama-barva'            => '#123456',
        ]);

        self::assertFalse($barvy->jsouVychozi());
        self::assertTrue($barvy->jeZmenena(BarvaWebuEnum::CERVENA));
        self::assertFalse($barvy->jeZmenena(BarvaWebuEnum::INKOUST));
        self::assertFalse($barvy->jeZmenena(BarvaWebuEnum::TEXT));
        self::assertSame('#0057b8', $barvy->getHodnota(BarvaWebuEnum::CERVENA));
        self::assertSame('#10111a', $barvy->getHodnota(BarvaWebuEnum::TEXT));
        self::assertSame('{"cervena":"#0057b8"}', $barvy->toJson());
    }

    public function testCssPrepisujeJenZmenenePromenne(): void
    {
        $barvy = BarvyWebu::zPole([
            BarvaWebuEnum::CERVENA->value => '#0057b8',
            BarvaWebuEnum::PLOCHA->value  => '#fafafa',
        ]);

        self::assertSame(':root{--mw-cervena:#0057b8;--mw-plocha:#fafafa;}', $barvy->getCss());
    }

    public function testJsonTamAZpet(): void
    {
        $barvy = BarvyWebu::zPole([
            BarvaWebuEnum::CERVENA->value  => '#0057b8',
            BarvaWebuEnum::ORANZOVA->value => '#ffd500',
        ]);

        self::assertTrue(BarvyWebu::zJson($barvy->toJson())->jeShodnaS($barvy));
    }

    public function testRozbityJsonJsouVychoziBarvy(): void
    {
        self::assertTrue(BarvyWebu::zJson('nejson')->jsouVychozi());
        self::assertTrue(BarvyWebu::zJson('"#123456"')->jsouVychozi());
    }
}
