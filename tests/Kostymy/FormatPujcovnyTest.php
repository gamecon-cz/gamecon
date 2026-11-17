<?php

declare(strict_types=1);

namespace Gamecon\Tests\Kostymy;

use Gamecon\Kostymy\FormatPujcovny;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FormatPujcovnyTest extends TestCase
{
    /**
     * @return array<string, array{0: int, 1: string}>
     */
    public static function provideSklonovaniPoctuKostymu(): array
    {
        return [
            'nula'       => [0, '0 kostýmů'],
            'jeden'      => [1, '1 kostým'],
            'dva'        => [2, '2 kostýmy'],
            'čtyři'      => [4, '4 kostýmy'],
            'pět'        => [5, '5 kostýmů'],
            'jedenáct'   => [11, '11 kostýmů'],
            'dvacet dva' => [22, '22 kostýmů'],
        ];
    }

    #[DataProvider('provideSklonovaniPoctuKostymu')]
    public function testPocetKostymuSklonujePodlePoctu(int $pocet, string $ocekavany): void
    {
        self::assertSame($ocekavany, FormatPujcovny::pocetKostymu($pocet));
    }
}
