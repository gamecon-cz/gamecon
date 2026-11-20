<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Cas;

use Gamecon\Cas\DatumVJazyce;
use Gamecon\Jazyk\AktualniJazyk;
use Gamecon\Jazyk\Jazyk;
use PHPUnit\Framework\TestCase;

class DatumVJazyceTest extends TestCase
{
    private string $puvodniCasovaZona;

    protected function setUp(): void
    {
        $this->puvodniCasovaZona = date_default_timezone_get();
        date_default_timezone_set('Europe/Prague');
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->puvodniCasovaZona);
        AktualniJazyk::reset();
    }

    public function testCeskyDatumJeStejneJakoDrivDatum3ADatum4(): void
    {
        $datum = new \DateTimeImmutable('2026-07-01 14:05:00');

        self::assertSame('1. července', DatumVJazyce::denAMesic($datum, Jazyk::CESTINA));
        self::assertSame('1. července 14:05', DatumVJazyce::denAMesicSCasem($datum, Jazyk::CESTINA));
        self::assertSame('středa', DatumVJazyce::denVTydnu($datum, Jazyk::CESTINA));
    }

    public function testAnglickyDatum(): void
    {
        $datum = new \DateTimeImmutable('2026-07-01 14:05:00');

        self::assertSame('1 July', DatumVJazyce::denAMesic($datum, Jazyk::ANGLICTINA));
        self::assertSame('1 July 14:05', DatumVJazyce::denAMesicSCasem($datum, Jazyk::ANGLICTINA));
        self::assertSame('Wednesday', DatumVJazyce::denVTydnu($datum, Jazyk::ANGLICTINA));
    }

    public function testFormatujeVeVychoziCasoveZoneJakoDrivDate(): void
    {
        $vUtc = new \DateTimeImmutable('2026-07-01 23:30:00', new \DateTimeZone('UTC'));

        self::assertSame('2. července 01:30', DatumVJazyce::denAMesicSCasem($vUtc, Jazyk::CESTINA));
        self::assertSame('2. července', DatumVJazyce::denAMesic($vUtc, Jazyk::CESTINA));
        self::assertSame('Thursday', DatumVJazyce::denVTydnu($vUtc, Jazyk::ANGLICTINA));
    }

    public function testBezJazykaBereJazykPozadavku(): void
    {
        AktualniJazyk::nastav(Jazyk::ANGLICTINA);

        self::assertSame('17 July', datum3('2026-07-17'));
        self::assertSame('17 July 08:30', datum4(new \DateTimeImmutable('2026-07-17 08:30')));
    }

    public function testFormatDateTimeCzZustavaCesky(): void
    {
        AktualniJazyk::nastav(Jazyk::ANGLICTINA);

        self::assertSame(
            'pátek',
            (new \Gamecon\Cas\DateTimeCz('2026-07-17'))->format('l'),
            'format() vrací dny česky i v angličtině, jeho výstup slouží jako klíč (slugy dnů programu).',
        );
    }
}
