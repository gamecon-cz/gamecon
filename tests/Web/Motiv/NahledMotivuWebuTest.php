<?php

declare(strict_types=1);

namespace Gamecon\Tests\Web\Motiv;

use Gamecon\Web\Motiv\MotivWebuEnum;
use Gamecon\Web\Motiv\NahledMotivuWebu;
use PHPUnit\Framework\TestCase;

class NahledMotivuWebuTest extends TestCase
{
    public function testBezCookieNicNenahlizi(): void
    {
        $nahled = new NahledMotivuWebu([]);

        self::assertFalse($nahled->jeNavrhBarev());
        self::assertNull($nahled->getMotiv());
    }

    public function testNahledNavrhuBarev(): void
    {
        $nahled = new NahledMotivuWebu([
            NahledMotivuWebu::COOKIE => NahledMotivuWebu::NAVRH_BAREV,
        ]);

        self::assertTrue($nahled->jeNavrhBarev());
        self::assertNull($nahled->getMotiv());
    }

    public function testNahledMotivu(): void
    {
        $nahled = new NahledMotivuWebu([
            NahledMotivuWebu::COOKIE => MotivWebuEnum::APRIL->value,
        ]);

        self::assertFalse($nahled->jeNavrhBarev());
        self::assertSame(MotivWebuEnum::APRIL, $nahled->getMotiv());
    }

    public function testNeznamaHodnotaVCookieNicNenahlizi(): void
    {
        $nahled = new NahledMotivuWebu([
            NahledMotivuWebu::COOKIE => 'z-budoucnosti',
        ]);

        self::assertFalse($nahled->jeNavrhBarev());
        self::assertNull($nahled->getMotiv());
        self::assertFalse($nahled->zpracujVolbu('z-budoucnosti'));
    }

    public function testNahledPrepneNaModerniVzhled(): void
    {
        self::assertSame(
            'https://gamecon.cz/?vzhled=moderni&nahledMotivu=april',
            NahledMotivuWebu::getUrlNahledu('https://gamecon.cz', MotivWebuEnum::APRIL->value),
        );
    }

    public function testKonecNahleduZachovaQuery(): void
    {
        self::assertSame(
            'https://gamecon.cz/program?den=patek&nahledMotivu=konec',
            NahledMotivuWebu::getUrlKonce('https://gamecon.cz/program', [
                'den' => 'patek',
            ]),
        );
    }

    public function testNahlizetSmiJenSPravemNaWeb(): void
    {
        self::assertFalse(NahledMotivuWebu::smiNahlizet(null));
    }
}
