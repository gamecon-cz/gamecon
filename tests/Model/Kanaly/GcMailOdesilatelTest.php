<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Kanaly;

use Gamecon\Kanaly\GcMail;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Address;

class GcMailOdesilatelTest extends TestCase
{
    /**
     * @test
     */
    public function odesilatelSeJmenemAEmailemProjdeNaOstrem(): void
    {
        $vystup = $this->vyvolejPridejPrefix(
            odesilatel: new Address('gamecon.fallback@seznam.cz', 'GameCon'),
            prefix: '',
        );

        self::assertSame('gamecon.fallback@seznam.cz', $vystup->getAddress());
        self::assertSame('GameCon', $vystup->getName());
    }

    /**
     * @test
     */
    public function odesilatelBezJmenaProjdeNaProstrediSPrefixem(): void
    {
        $vystup = $this->vyvolejPridejPrefix(
            odesilatel: new Address('gamecon.fallback@seznam.cz'),
            prefix: 'β',
        );

        self::assertSame('gamecon.fallback@seznam.cz', $vystup->getAddress());
        self::assertSame('β', $vystup->getName());
    }

    /**
     * @test
     */
    public function odesilatelSeJmenemDostanePrefixVeJmene(): void
    {
        $vystup = $this->vyvolejPridejPrefix(
            odesilatel: new Address('gamecon.fallback@seznam.cz', 'GameCon'),
            prefix: 'β',
        );

        self::assertSame('gamecon.fallback@seznam.cz', $vystup->getAddress());
        self::assertSame('β GameCon', $vystup->getName());
    }

    /**
     * @test
     */
    public function emailovaAdresaNikdyNedostanePrefix(): void
    {
        $vystup = $this->vyvolejPridejPrefix(
            odesilatel: new Address('gamecon.fallback@seznam.cz', 'GameCon'),
            prefix: 'άλφα',
        );

        self::assertSame('gamecon.fallback@seznam.cz', $vystup->getAddress());
        self::assertStringNotContainsString('@', $vystup->getName());
    }

    /**
     * @test
     */
    public function vychoziOdesilatelJeVzdyInfoGamecon(): void
    {
        $vystup = $this->vyvolejPridejPrefix(
            odesilatel: null,
            prefix: '',
        );

        self::assertSame('gamecon.fallback@seznam.cz', $vystup->getAddress());
        self::assertSame('GameCon', $vystup->getName());
    }

    /**
     * @test
     */
    public function vychoziOdesilatelDostanePrefixVeJmene(): void
    {
        $vystup = $this->vyvolejPridejPrefix(
            odesilatel: null,
            prefix: 'β',
        );

        self::assertSame('gamecon.fallback@seznam.cz', $vystup->getAddress());
        self::assertSame('β GameCon', $vystup->getName());
    }

    private function vyvolejPridejPrefix(
        ?Address $odesilatel,
        string $prefix,
    ): Address {
        $systemoveNastaveni = $this->fakeSystemoveNastaveni($prefix);
        $gcMail = new GcMail($systemoveNastaveni);
        if ($odesilatel !== null) {
            $gcMail->odesilatel($odesilatel);
        }

        $metoda = new \ReflectionMethod(GcMail::class, 'odesilatelSPrefixemProstredi');

        return $metoda->invoke($gcMail);
    }

    private function fakeSystemoveNastaveni(string $prefix): SystemoveNastaveni
    {
        return new class($prefix) extends SystemoveNastaveni {
            public function __construct(
                private readonly string $prefix,
            ) {
            }

            public function prefixPodleProstredi(): string
            {
                return $this->prefix;
            }
        };
    }
}
