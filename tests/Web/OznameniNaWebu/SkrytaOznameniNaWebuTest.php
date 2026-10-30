<?php

declare(strict_types=1);

namespace Gamecon\Tests\Web\OznameniNaWebu;

use Gamecon\Web\OznameniNaWebu\OznameniNaWebu;
use Gamecon\Web\OznameniNaWebu\SkrytaOznameniNaWebu;
use Gamecon\Web\OznameniNaWebu\TypOznameniNaWebuEnum;
use PHPUnit\Framework\TestCase;

class SkrytaOznameniNaWebuTest extends TestCase
{
    public function testBezCookieNicNeniSkryte(): void
    {
        self::assertFalse((new SkrytaOznameniNaWebu([]))->jeSkryte(self::oznameni(1, 'Bouřka')));
    }

    public function testZavreneOznameniJeSkryte(): void
    {
        $oznameni = self::oznameni(1, 'Bouřka');
        $skryta = new SkrytaOznameniNaWebu([
            SkrytaOznameniNaWebu::COOKIE => $oznameni->getKlicSkryti(),
        ]);

        self::assertTrue($skryta->jeSkryte($oznameni));
        self::assertSame([], $skryta->neskryta([$oznameni]));
    }

    public function testPoZmeneTextuSeOznameniUkazeZnovu(): void
    {
        $puvodni = self::oznameni(1, 'Bouřka, venkovní hry se ruší');
        $skryta = new SkrytaOznameniNaWebu([
            SkrytaOznameniNaWebu::COOKIE => $puvodni->getKlicSkryti(),
        ]);

        self::assertFalse($skryta->jeSkryte(self::oznameni(1, 'Bouřka přešla, venkovní hry jedou')));
        self::assertFalse($skryta->jeSkryte(self::oznameni(1, 'Bouřka, venkovní hry se ruší', TypOznameniNaWebuEnum::UPOZORNENI)));
    }

    public function testNalehaveOznameniNejdeSkryt(): void
    {
        $nalehave = self::oznameni(1, 'Evakuace haly B', TypOznameniNaWebuEnum::NALEHAVE);
        $skryta = new SkrytaOznameniNaWebu([
            SkrytaOznameniNaWebu::COOKIE => $nalehave->getKlicSkryti(),
        ]);

        self::assertFalse($skryta->jeSkryte($nalehave));
    }

    public function testCookieSVicOznamenimiANesmysly(): void
    {
        $prvni = self::oznameni(1, 'První');
        $druhe = self::oznameni(2, 'Druhé');
        $treti = self::oznameni(3, 'Třetí');
        $skryta = new SkrytaOznameniNaWebu([
            SkrytaOznameniNaWebu::COOKIE => $prvni->getKlicSkryti() . '.nesmysl..' . $treti->getKlicSkryti(),
        ]);

        self::assertSame([$druhe], $skryta->neskryta([$prvni, $druhe, $treti]));
    }

    public function testNeplatnyKlicNicNezmeni(): void
    {
        self::assertFalse((new SkrytaOznameniNaWebu([]))->zpracujSkryti('1-<script>'));
    }

    public function testUrlSkrytiZachovaQuery(): void
    {
        $oznameni = self::oznameni(7, 'Bouřka');

        self::assertSame(
            'https://gamecon.cz/program?den=patek&skrytOznameni=' . $oznameni->getKlicSkryti(),
            SkrytaOznameniNaWebu::getUrlSkryti('https://gamecon.cz/program', [
                'den' => 'patek',
            ], $oznameni),
        );
    }

    private static function oznameni(
        int $id,
        string $text,
        TypOznameniNaWebuEnum $typ = TypOznameniNaWebuEnum::INFO,
    ): OznameniNaWebu {
        return new OznameniNaWebu($id, $text, $typ, null, null, null, null);
    }
}
