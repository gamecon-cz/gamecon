<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Jazyk;

use Gamecon\Cas\DateTimeCz;
use Gamecon\Jazyk\Jazyk;
use Gamecon\Jazyk\Preklad;
use PHPUnit\Framework\TestCase;

/**
 * Data ve větách z katalogů webu, zapsaná vzorem ICU přímo ve zprávě. Česky musí vyjít stejně
 * jako dřív z `DateTimeCz::format()`, anglicky britsky, a mezera za čárkou vzoru se nesmí vypsat.
 */
class DatumVKatalozichTest extends TestCase
{
    protected function setUp(): void
    {
        Preklad::setAdresarKatalogu(null);
    }

    public function testZacatekPrihlasovaniNaTitulce(): void
    {
        $zacatek = new DateTimeCz('2026-05-05 08:05:00');

        self::assertSame(
            'Přihlašování začne ' . $zacatek->format(DateTimeCz::FORMAT_ZACATEK_UDALOSTI),
            Preklad::t('titulka.odpocet.prihlasovani_zacne', [
                'zacatek' => $zacatek,
            ], Jazyk::CESTINA),
        );
        self::assertSame(
            'Registration opens on 5 May 2026 at 08:05',
            Preklad::t('titulka.odpocet.prihlasovani_zacne', [
                'zacatek' => $zacatek,
            ], Jazyk::ANGLICTINA),
        );
    }

    public function testDatumGameconuVHlavickeTitulky(): void
    {
        $zacatek = new DateTimeCz('2026-07-16 08:00:00');
        $konec = new DateTimeCz('2026-07-19 21:00:00');

        self::assertSame($zacatek->format('j.'), Preklad::t('titulka.hlavicka.zacatek', [
            'zacatek' => $zacatek,
        ], Jazyk::CESTINA));
        self::assertSame($konec->format('j. n.'), Preklad::t('titulka.hlavicka.konec', [
            'konec' => $konec,
        ], Jazyk::CESTINA));
        self::assertSame('16', Preklad::t('titulka.hlavicka.zacatek', [
            'zacatek' => $zacatek,
        ], Jazyk::ANGLICTINA));
        self::assertSame('19 July', Preklad::t('titulka.hlavicka.konec', [
            'konec' => $konec,
        ], Jazyk::ANGLICTINA));
    }

    public function testTerminAktivityNaStranceLinie(): void
    {
        $zacatek = new DateTimeCz('2026-07-17 09:30:00');
        $konec = new DateTimeCz('2026-07-17 13:00:00');
        $parametry = [
            'zacatek' => $zacatek,
            'konec'   => $konec,
        ];

        self::assertSame(
            $zacatek->format('l G') . '–' . $konec->format('G'),
            Preklad::t('aktivity.termin', $parametry, Jazyk::CESTINA),
            'Česky jako Aktivita::denCasSkutecny()',
        );
        self::assertSame('Friday 09:30–13:00', Preklad::t('aktivity.termin', $parametry, Jazyk::ANGLICTINA));
    }

    public function testDatumJeVeVychoziCasoveZone(): void
    {
        // 2026-07-17 22:30 UTC je v Praze už sobota 00:30
        $zacatek = new \DateTimeImmutable('2026-07-17 22:30:00', new \DateTimeZone('UTC'));
        $konec = new \DateTimeImmutable('2026-07-18 02:00:00', new \DateTimeZone('UTC'));

        self::assertSame(
            'Saturday 00:30–04:00',
            Preklad::t('aktivity.termin', [
                'zacatek' => $zacatek,
                'konec'   => $konec,
            ], Jazyk::ANGLICTINA),
        );
    }
}
