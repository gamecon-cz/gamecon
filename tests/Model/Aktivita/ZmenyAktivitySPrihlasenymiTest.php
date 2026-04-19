<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Aktivita;

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\SqlStruktura\AkceSeznamSqlStruktura as Sql;
use Gamecon\Aktivita\ZmenyAktivitySPrihlasenymi;
use Gamecon\Cas\DateTimeCz;
use PHPUnit\Framework\TestCase;

class ZmenyAktivitySPrihlasenymiTest extends TestCase
{
    private function vytvorAktivituSMocky(
        int $pocetPrihlasenych,
        ?string $den,
        ?string $zacatek,
        ?string $konec,
        int $cena,
        array $rawDbData = [],
    ): Aktivita {
        $aktivitaMock = $this->createMock(Aktivita::class);

        $aktivitaMock->method('pocetPrihlasenych')
            ->willReturn($pocetPrihlasenych);

        $denDateTime = $den ? new DateTimeCz($den) : null;
        $aktivitaMock->method('denProgramu')
            ->willReturn($denDateTime);

        $zacatekDateTime = $zacatek ? new DateTimeCz($zacatek) : null;
        $aktivitaMock->method('zacatek')
            ->willReturn($zacatekDateTime);

        $konecDateTime = $konec ? new DateTimeCz($konec) : null;
        $aktivitaMock->method('konec')
            ->willReturn($konecDateTime);

        $aktivitaMock->method('rawDb')
            ->willReturn(array_merge([
                Sql::CENA       => $cena,
                Sql::TEAMOVA    => 0,
                Sql::KAPACITA   => 0,
                Sql::KAPACITA_F => 0,
                Sql::KAPACITA_M => 0,
                Sql::TEAM_MAX   => 0,
                Sql::TEAM_MIN   => 0,
            ], $rawDbData));

        return $aktivitaMock;
    }

    public function testZadniPrihlaseniZadnaZmena(): void
    {
        $aktivita = $this->vytvorAktivituSMocky(0, '2026-07-16', '2026-07-16 10:00:00', '2026-07-16 12:00:00', 100);

        $dataZFormulare = [
            'den'         => '2026-07-17',
            Sql::ZACATEK  => '11',
            Sql::KONEC    => '15',
            Sql::CENA     => '200',
            Sql::KAPACITA => 10,
        ];

        $zmeny = new ZmenyAktivitySPrihlasenymi($aktivita, $dataZFormulare);
        self::assertFalse($zmeny->maZmenySPrihlasenymi());
    }

    public function testSPrihlasenymiZadneZmenyUdaju(): void
    {
        $aktivita = $this->vytvorAktivituSMocky(2, '2026-07-16', '2026-07-16 10:00:00', '2026-07-16 12:00:00', 100, [
            Sql::KAPACITA => 5,
        ]);

        $dataZFormulare = [
            'den'         => '2026-07-16',
            Sql::ZACATEK  => '10', // stejný začátek (v hodinách)
            Sql::KONEC    => '12', // stejný konec
            Sql::CENA     => 100,
            Sql::KAPACITA => 5,
            Sql::TEAMOVA  => 0,
        ];

        $zmeny = new ZmenyAktivitySPrihlasenymi($aktivita, $dataZFormulare);
        self::assertFalse($zmeny->maZmenySPrihlasenymi(), 'Očekáváno bez změn.');
    }

    public function testSPrihlasenymiZmenenDen(): void
    {
        $aktivita = $this->vytvorAktivituSMocky(2, '2026-07-16', '2026-07-16 10:00:00', '2026-07-16 12:00:00', 100);

        $dataZFormulare = [
            'den'        => '2026-07-17',
            Sql::ZACATEK => '10',
            Sql::KONEC   => '12',
            Sql::CENA    => 100,
        ];

        $zmeny = new ZmenyAktivitySPrihlasenymi($aktivita, $dataZFormulare);
        self::assertTrue($zmeny->maZmenySPrihlasenymi());
        self::assertStringContainsString('den', $zmeny->dejTextPotvrzeniZmenyUdajuSPrihlasenymi());
    }

    public function testSPrihlasenymiZmenenCasZacatek(): void
    {
        $aktivita = $this->vytvorAktivituSMocky(2, '2026-07-16', '2026-07-16 10:00:00', '2026-07-16 12:00:00', 100);

        $dataZFormulare = [
            'den'        => '2026-07-16',
            Sql::ZACATEK => '9', // změna z 10 na 9
            Sql::KONEC   => '12',
            Sql::CENA    => 100,
        ];

        $zmeny = new ZmenyAktivitySPrihlasenymi($aktivita, $dataZFormulare);
        self::assertTrue($zmeny->maZmenySPrihlasenymi());
        self::assertStringContainsString('čas', $zmeny->dejTextPotvrzeniZmenyUdajuSPrihlasenymi());
    }

    public function testSPrihlasenymiZmenenCasKonec(): void
    {
        $aktivita = $this->vytvorAktivituSMocky(2, '2026-07-16', '2026-07-16 10:00:00', '2026-07-16 12:00:00', 100);

        $dataZFormulare = [
            'den'        => '2026-07-16',
            Sql::ZACATEK => '10',
            Sql::KONEC   => '13', // změna z 12 na 13
            Sql::CENA    => 100,
        ];

        $zmeny = new ZmenyAktivitySPrihlasenymi($aktivita, $dataZFormulare);
        self::assertTrue($zmeny->maZmenySPrihlasenymi());
        self::assertStringContainsString('čas', $zmeny->dejTextPotvrzeniZmenyUdajuSPrihlasenymi());
    }

    public function testSPrihlasenymiZmenenaCena(): void
    {
        $aktivita = $this->vytvorAktivituSMocky(2, '2026-07-16', '2026-07-16 10:00:00', '2026-07-16 12:00:00', 100);

        $dataZFormulare = [
            'den'        => '2026-07-16',
            Sql::ZACATEK => '10',
            Sql::KONEC   => '12',
            Sql::CENA    => 150, // změna ze 100 na 150
        ];

        $zmeny = new ZmenyAktivitySPrihlasenymi($aktivita, $dataZFormulare);
        self::assertTrue($zmeny->maZmenySPrihlasenymi());
        self::assertStringContainsString('cenu', $zmeny->dejTextPotvrzeniZmenyUdajuSPrihlasenymi());
    }

    public function testSPrihlasenymiZmenenaKapacitaNeteamova(): void
    {
        $aktivita = $this->vytvorAktivituSMocky(2, '2026-07-16', '2026-07-16 10:00:00', '2026-07-16 12:00:00', 100, [
            Sql::KAPACITA => 5,
        ]);

        $dataZFormulare = [
            'den'         => '2026-07-16',
            Sql::ZACATEK  => '10',
            Sql::KONEC    => '12',
            Sql::CENA     => 100,
            Sql::KAPACITA => 6, // změna z 5 na 6
        ];

        $zmeny = new ZmenyAktivitySPrihlasenymi($aktivita, $dataZFormulare);
        self::assertTrue($zmeny->maZmenySPrihlasenymi());
        self::assertStringContainsString('kapacitu', $zmeny->dejTextPotvrzeniZmenyUdajuSPrihlasenymi());
    }

    public function testSPrihlasenymiZmenenaKapacitaTeamova(): void
    {
        $aktivita = $this->vytvorAktivituSMocky(2, '2026-07-16', '2026-07-16 10:00:00', '2026-07-16 12:00:00', 100, [
            Sql::TEAMOVA  => 1,
            Sql::TEAM_MAX => 5,
            Sql::TEAM_MIN => 2,
        ]);

        $dataZFormulare = [
            'den'         => '2026-07-16',
            Sql::ZACATEK  => '10',
            Sql::KONEC    => '12',
            Sql::CENA     => 100,
            Sql::TEAMOVA  => 1,
            Sql::TEAM_MAX => 6, // změna z 5 na 6
            Sql::TEAM_MIN => 2,
        ];

        $zmeny = new ZmenyAktivitySPrihlasenymi($aktivita, $dataZFormulare);
        self::assertTrue($zmeny->maZmenySPrihlasenymi());
        self::assertStringContainsString('kapacitu', $zmeny->dejTextPotvrzeniZmenyUdajuSPrihlasenymi());
    }

    public function testTeamovaBezZmeny(): void
    {
        $aktivita = $this->vytvorAktivituSMocky(2, '2026-07-16', '2026-07-16 10:00:00', '2026-07-16 12:00:00', 100, [
            Sql::TEAMOVA  => 1,
            Sql::TEAM_MAX => 5,
            Sql::TEAM_MIN => 2,
        ]);

        $dataZFormulare = [
            'den'           => '2026-07-16',
            Sql::ZACATEK    => '10',
            Sql::KONEC      => '12',
            Sql::CENA       => 100,
            Sql::TEAMOVA    => 1,
            Sql::TEAM_MAX   => 5,
            Sql::TEAM_MIN   => 2,
            Sql::KAPACITA_F => 999, // změna neteamové vlastnosti u teamové hry
        ];

        $zmeny = new ZmenyAktivitySPrihlasenymi($aktivita, $dataZFormulare);
        self::assertFalse($zmeny->maZmenySPrihlasenymi());
    }
}
