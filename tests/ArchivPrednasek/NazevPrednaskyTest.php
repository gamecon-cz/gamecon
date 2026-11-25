<?php

declare(strict_types=1);

namespace Gamecon\Tests\ArchivPrednasek;

use Gamecon\ArchivPrednasek\NazevPrednasky;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NazevPrednaskyTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function provideNazvy(): array
    {
        return [
            'spojovník s mezerami je pomlčka' => ['Mistrovství DrD - Rozuzlení', 'Mistrovství DrD – Rozuzlení'],
            'pomlčka zůstane'                 => ['Mistrovství DrD – Rozuzlení', 'Mistrovství DrD – Rozuzlení'],
            'dlouhá pomlčka s mezerami'       => ['Warhammer — Představení světa', 'Warhammer – Představení světa'],
            'spojovník ve slově zůstane'      => ['One-Shot: Role-playing a Křížom-krážom', 'One-Shot: Role-playing a Křížom-krážom'],
            'mezery na okrajích a uvnitř'     => ["  Deskové hry \t v  Československu ", 'Deskové hry v Československu'],
            'pevná mezera'                    => ["Asterion\u{00A0}žije!", 'Asterion žije!'],
            'entity a neviditelné znaky'      => ["War&shy;ham&shy;mer - Před\u{00AD}sta\u{200B}vení světa", 'Warhammer – Představení světa'],
            'ampersand'                       => ['Hack &amp; Slash', 'Hack & Slash'],
        ];
    }

    #[DataProvider('provideNazvy')]
    public function testUpraviNazev(string $nazev, string $ocekavany): void
    {
        self::assertSame($ocekavany, NazevPrednasky::uprav($nazev));
    }
}
