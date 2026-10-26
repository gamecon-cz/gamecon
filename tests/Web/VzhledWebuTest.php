<?php

declare(strict_types=1);

namespace Gamecon\Tests\Web;

use Gamecon\Web\VzhledWebu;
use Gamecon\Web\VzhledWebuEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class VzhledWebuTest extends TestCase
{
    public function testBezVolbyJeKlasicky(): void
    {
        self::assertSame(VzhledWebuEnum::KLASICKY, (new VzhledWebu([]))->getVzhled());
    }

    public function testVzhledJeZCookie(): void
    {
        self::assertSame(
            VzhledWebuEnum::MODERNI,
            (new VzhledWebu([
                VzhledWebu::COOKIE => 'moderni',
            ]))->getVzhled(),
        );
    }

    public function testNeznamaHodnotaVCookieJeKlasicky(): void
    {
        self::assertSame(
            VzhledWebuEnum::KLASICKY,
            (new VzhledWebu([
                VzhledWebu::COOKIE => 'z-budoucnosti',
            ]))->getVzhled(),
        );
    }

    public function testNeznamaVolbaNicNezmeni(): void
    {
        self::assertNull((new VzhledWebu([]))->zpracujVolbu('z-budoucnosti'));
    }

    /**
     * @param array<string, string> $query
     */
    #[DataProvider('provideUrlPrepnuti')]
    public function testUrlPrepnutiZachovaQuery(array $query, string $ocekavana): void
    {
        self::assertSame(
            $ocekavana,
            VzhledWebu::getUrlPrepnuti('https://gamecon.cz/rpg', $query, VzhledWebuEnum::MODERNI),
        );
    }

    public static function provideUrlPrepnuti(): array
    {
        return [
            'bez query' => [[], 'https://gamecon.cz/rpg?vzhled=moderni'],
            's query'   => [[
                'filtr' => 'horor',
            ], 'https://gamecon.cz/rpg?filtr=horor&vzhled=moderni'],
            'přepíše starou volbu' => [[
                'vzhled' => 'klasicky',
            ], 'https://gamecon.cz/rpg?vzhled=moderni'],
        ];
    }

    /**
     * @param array<string, string> $query
     */
    #[DataProvider('provideUrlBezVolby')]
    public function testUrlBezVolbyOdeberePrepinac(array $query, string $ocekavana): void
    {
        self::assertSame($ocekavana, VzhledWebu::getUrlBezVolby('https://gamecon.cz/rpg', $query));
    }

    public static function provideUrlBezVolby(): array
    {
        return [
            'jen volba' => [[
                'vzhled' => 'moderni',
            ], 'https://gamecon.cz/rpg'],
            'volba a query' => [[
                'filtr'  => 'horor',
                'vzhled' => 'moderni',
            ], 'https://gamecon.cz/rpg?filtr=horor'],
        ];
    }
}
