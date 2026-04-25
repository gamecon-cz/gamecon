<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model;

use Gamecon\Jidlo;
use PHPUnit\Framework\TestCase;

class JidloTest extends TestCase
{
    /**
     * @test
     */
    public function dejJidlaBehemDneVraciKlicovanyArray(): void
    {
        $jidla = Jidlo::dejJidlaBehemDne();

        self::assertSame([
            Jidlo::PORADI_SNIDANE => Jidlo::SNIDANE,
            Jidlo::PORADI_OBED    => Jidlo::OBED,
            Jidlo::PORADI_VECERE  => Jidlo::VECERE,
        ], $jidla);
    }

    /**
     * @test
     */
    public function dejPoradiJidlaBehemDne(): void
    {
        self::assertSame(Jidlo::PORADI_SNIDANE, Jidlo::dejPoradiJidlaBehemDne(Jidlo::SNIDANE));
        self::assertSame(Jidlo::PORADI_OBED, Jidlo::dejPoradiJidlaBehemDne(Jidlo::OBED));
        self::assertSame(Jidlo::PORADI_VECERE, Jidlo::dejPoradiJidlaBehemDne(Jidlo::VECERE));
        self::assertNull(Jidlo::dejPoradiJidlaBehemDne('svačina'));
    }

    /**
     * @test
     */
    public function jeToSnidane(): void
    {
        self::assertTrue(Jidlo::jeToSnidane(Jidlo::SNIDANE));
        self::assertTrue(Jidlo::jeToSnidane('Snídaně'));
        self::assertFalse(Jidlo::jeToSnidane(Jidlo::OBED));
        self::assertFalse(Jidlo::jeToSnidane(Jidlo::VECERE));
    }

    /**
     * @test
     */
    public function jeToObedAVecere(): void
    {
        self::assertTrue(Jidlo::jeToObed(Jidlo::OBED));
        self::assertFalse(Jidlo::jeToObed(Jidlo::SNIDANE));

        self::assertTrue(Jidlo::jeToVecere(Jidlo::VECERE));
        self::assertFalse(Jidlo::jeToVecere(Jidlo::SNIDANE));
    }
}
