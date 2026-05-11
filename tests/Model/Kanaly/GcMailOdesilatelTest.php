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
            odesilatel: new Address('info@gamecon.cz', 'GameCon'),
            prefix: '',
            kontaktniEmail: 'kontakt@gamecon.cz',
        );

        self::assertSame('info@gamecon.cz', $vystup->getAddress());
        self::assertSame('GameCon', $vystup->getName());
    }

    /**
     * @test
     */
    public function odesilatelBezJmenaProjdeNaProstrediSPrefixem(): void
    {
        $vystup = $this->vyvolejPridejPrefix(
            odesilatel: new Address('info@gamecon.cz'),
            prefix: 'β',
            kontaktniEmail: 'kontakt@gamecon.cz',
        );

        self::assertSame('info@gamecon.cz', $vystup->getAddress());
        self::assertSame('β', $vystup->getName());
    }

    /**
     * @test
     */
    public function odesilatelSeJmenemDostanePrefixVeJmene(): void
    {
        $vystup = $this->vyvolejPridejPrefix(
            odesilatel: new Address('info@gamecon.cz', 'GameCon'),
            prefix: 'β',
            kontaktniEmail: 'kontakt@gamecon.cz',
        );

        self::assertSame('info@gamecon.cz', $vystup->getAddress());
        self::assertSame('β GameCon', $vystup->getName());
    }

    /**
     * @test
     */
    public function emailovaAdresaNikdyNedostanePrefix(): void
    {
        $vystup = $this->vyvolejPridejPrefix(
            odesilatel: new Address('info@gamecon.cz', 'GameCon'),
            prefix: 'άλφα',
            kontaktniEmail: 'kontakt@gamecon.cz',
        );

        self::assertSame('info@gamecon.cz', $vystup->getAddress());
        self::assertStringNotContainsString('@', $vystup->getName());
    }

    /**
     * @test
     */
    public function vychoziOdesilatelPouzijeKontaktniEmailZNastaveni(): void
    {
        $vystup = $this->vyvolejPridejPrefix(
            odesilatel: null,
            prefix: '',
            kontaktniEmail: 'gamecon.fallback@seznam.cz',
        );

        self::assertSame('gamecon.fallback@seznam.cz', $vystup->getAddress());
        self::assertSame('GameCon', $vystup->getName());
    }

    /**
     * @test
     */
    public function vychoziOdesilatelPouzijeFallbackKdyzKontaktniEmailJePrazdny(): void
    {
        $vystup = $this->vyvolejPridejPrefix(
            odesilatel: null,
            prefix: '',
            kontaktniEmail: '',
        );

        self::assertSame('info@gamecon.cz', $vystup->getAddress());
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
            kontaktniEmail: 'kontakt@gamecon.cz',
        );

        self::assertSame('kontakt@gamecon.cz', $vystup->getAddress());
        self::assertSame('β GameCon', $vystup->getName());
    }

    private function vyvolejPridejPrefix(
        ?Address $odesilatel,
        string $prefix,
        string $kontaktniEmail,
    ): Address {
        $systemoveNastaveni = $this->fakeSystemoveNastaveni($prefix, $kontaktniEmail);
        $gcMail = new GcMail($systemoveNastaveni);
        if ($odesilatel !== null) {
            $gcMail->odesilatel($odesilatel);
        }

        $metoda = new \ReflectionMethod(GcMail::class, 'odesilatelSPrefixemProstredi');
        $metoda->setAccessible(true);

        return $metoda->invoke($gcMail);
    }

    private function fakeSystemoveNastaveni(string $prefix, string $kontaktniEmail): SystemoveNastaveni
    {
        return new class($prefix, $kontaktniEmail) extends SystemoveNastaveni {
            public function __construct(
                private readonly string $prefix,
                private readonly string $kontaktniEmail,
            ) {
            }

            public function prefixPodleProstredi(): string
            {
                return $this->prefix;
            }

            public function kontaktniEmailGc(): string
            {
                return $this->kontaktniEmail;
            }
        };
    }
}
