<?php

declare(strict_types=1);

namespace Gamecon\Tests\funkce;

use PHPUnit\Framework\TestCase;

/**
 * `randHex()` vyrábí tokeny, kterými se člověk přihlašuje bez hesla (cookie „zůstat přihlášený"),
 * takže nesmí jít předpovědět ze stavu `mt_rand()`.
 */
class RandHexTest extends TestCase
{
    protected function tearDown(): void
    {
        // Test seedu nechává `mt_rand()` na pevné hodnotě, kterou by zdědily všechny další testy.
        mt_srand();

        parent::tearDown();
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function delkyProvider(): iterable
    {
        yield 'nula' => [0];
        yield 'jedna' => [1];
        yield 'dvacet jako sloupec random' => [20];
        yield 'maximum' => [32];
    }

    /**
     * @dataProvider delkyProvider
     */
    public function testMaPozadovanouDelkuAJenHexaZnaky(int $delka): void
    {
        $hodnota = randHex($delka);

        self::assertSame($delka, strlen($hodnota));
        self::assertMatchesRegularExpression('~^[0-9a-f]*$~', $hodnota);
    }

    public function testDelkaNadTricetDvaJeVyjimka(): void
    {
        $this->expectException(\Exception::class);

        randHex(33);
    }

    public function testZapornaDelkaJeVyjimka(): void
    {
        $this->expectException(\Exception::class);

        randHex(-1);
    }

    /**
     * Kdo zná nebo odhadne seed `mt_rand()`, by jinak token spočítal. Stejný seed tedy nesmí
     * dát stejný token.
     */
    public function testNezavisiNaSeeduMtRand(): void
    {
        mt_srand(12345);
        $prvni = randHex(20);
        mt_srand(12345);
        $druha = randHex(20);

        self::assertNotSame($prvni, $druha);
    }

    /**
     * `mt_rand()` má jen 2^31 hodnot, takže mezi 200 000 tokeny by se opakování objevilo skoro
     * jistě; u 80 bitů ze `random_bytes` prakticky nikdy.
     */
    public function testNeopakujeSeMeziDvoustoTisicTokeny(): void
    {
        $videno = [];
        for ($poradi = 0; $poradi < 200_000; ++$poradi) {
            $videno[randHex(20)] = true;
        }

        self::assertCount(200_000, $videno);
    }
}
