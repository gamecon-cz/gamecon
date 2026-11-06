<?php

declare(strict_types=1);

namespace Gamecon\Tests\Uzivatel;

use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Uzivatel\ObnovaHeslaLog;
use Gamecon\Uzivatel\SqlStruktura\ObnovaHeslaLogSqlStruktura as Sql;
use Gamecon\Uzivatel\SqlStruktura\UzivateleHodnotySqlStruktura as UzivatelSql;
use PHPUnit\Framework\Attributes\DataProvider;

class ObnovaHeslaLogTest extends AbstractTestDb
{
    private const string IP = '192.0.2.1';

    public function testLimitUctuPocitaJenOdeslaneMailyZaPosledniHodinu(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        $obnovaHeslaLog = new ObnovaHeslaLog();
        $this->zapisZadosti(self::IP, $idUzivatele, true, ObnovaHeslaLog::MAX_MAILU_NA_UCET, ObnovaHeslaLog::OKNO_MINUT + 1);
        $this->zapisZadosti(self::IP, $idUzivatele, false, ObnovaHeslaLog::MAX_MAILU_NA_UCET, 0);
        $this->zapisZadosti(self::IP, $idUzivatele, true, ObnovaHeslaLog::MAX_MAILU_NA_UCET, 0);

        self::assertFalse(
            $obnovaHeslaLog->jePrekrocenLimitUctu($idUzivatele),
            'Starší maily a žádosti, po kterých mail neodešel, se do limitu účtu nepočítají, a limit dovolí právě MAX_MAILU_NA_UCET mailů',
        );

        $obnovaHeslaLog->zaznamenej(self::IP, $idUzivatele, true);

        self::assertTrue($obnovaHeslaLog->jePrekrocenLimitUctu($idUzivatele));
    }

    public function testLimitIpPocitaVsechnyZadostiZaPosledniHodinu(): void
    {
        $obnovaHeslaLog = new ObnovaHeslaLog();
        $this->zapisZadosti(self::IP, null, false, ObnovaHeslaLog::MAX_ZADOSTI_Z_IP, ObnovaHeslaLog::OKNO_MINUT + 1);
        $this->zapisZadosti(self::IP, null, false, ObnovaHeslaLog::MAX_ZADOSTI_Z_IP - 1, 0);
        $this->zapisZadosti('192.0.2.2', null, false, ObnovaHeslaLog::MAX_ZADOSTI_Z_IP, 0);

        self::assertFalse(
            $obnovaHeslaLog->jePrekrocenLimitIp($obnovaHeslaLog->zaznamenej(self::IP, null, false)),
            'Starší žádosti a žádosti z jiné IP se do limitu nepočítají, a limit dovolí právě MAX_ZADOSTI_Z_IP žádostí',
        );
        self::assertTrue($obnovaHeslaLog->jePrekrocenLimitIp($obnovaHeslaLog->zaznamenej(self::IP, null, false)));
    }

    public function testLimitIpPocitaIpv6PoSitich64(): void
    {
        $obnovaHeslaLog = new ObnovaHeslaLog();
        // Každá žádost z jiné adresy téže sítě /64, jak by je posílal klient, který si adresu mění.
        for ($i = 1; $i < ObnovaHeslaLog::MAX_ZADOSTI_Z_IP; ++$i) {
            $obnovaHeslaLog->zaznamenej(sprintf('2001:db8:1:2::%x', $i), null, false);
        }
        $this->zapisZadosti('2001:db8:1:3::1', null, false, ObnovaHeslaLog::MAX_ZADOSTI_Z_IP, 0);

        self::assertFalse(
            $obnovaHeslaLog->jePrekrocenLimitIp($obnovaHeslaLog->zaznamenej('2001:db8:1:2:ffff:ffff:ffff:ffff', null, false)),
            'Žádosti ze sousední sítě /64 se do limitu nepočítají',
        );
        self::assertTrue(
            $obnovaHeslaLog->jePrekrocenLimitIp($obnovaHeslaLog->zaznamenej('2001:db8:1:2:aaaa:bbbb:cccc:dddd', null, false)),
            'Změnou adresy uvnitř své sítě /64 limit obejít nejde',
        );
    }

    #[DataProvider('provideRozsahyIp')]
    public function testRozsahIpSeSpocitaZAdresy(
        string $ip,
        string $ocekavanyRozsah,
    ): void {
        $idZadosti = (new ObnovaHeslaLog())->zaznamenej($ip, null, false);

        self::assertSame(
            $ocekavanyRozsah,
            dbOneCol(<<<'SQL'
SELECT ip_rozsah
FROM obnova_hesla_log
WHERE id_log = $0
SQL,
                [
                    0 => $idZadosti,
                ],
            ),
        );
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function provideRozsahyIp(): array
    {
        return [
            'IPv4 sama za sebe'                   => ['192.0.2.1', '192.0.2.1'],
            'IPv6 na síť /64'                     => ['2001:db8:1:2:aaaa:bbbb:cccc:dddd', '2001:db8:1:2::'],
            'IPv6 v nezkráceném zápisu'           => ['2001:0db8:0001:0002:0000:0000:0000:0001', '2001:db8:1:2::'],
            'IPv6 velkými písmeny'                => ['2001:DB8:1:2::1', '2001:db8:1:2::'],
            'IPv4 zapsaná jako IPv6 jako ta IPv4' => ['::ffff:192.0.2.1', '192.0.2.1'],
            'bez adresy (getClientIp() null)'     => ['', ''],
            'neplatná adresa'                     => ['neni-ip', 'neni-ip'],
        ];
    }

    public function testZapisSmazeZadostiStarsiNezDobaUchovani(): void
    {
        $this->zapisZadosti(self::IP, null, false, 1, (ObnovaHeslaLog::UCHOVAVAT_DNI + 1) * 24 * 60);
        $this->zapisZadosti(self::IP, null, false, 1, (ObnovaHeslaLog::UCHOVAVAT_DNI - 1) * 24 * 60);

        (new ObnovaHeslaLog())->zaznamenej(self::IP, null, false);

        self::assertSame(2, (int) dbOneCol('SELECT COUNT(*) FROM obnova_hesla_log WHERE ip = $0', [self::IP]));
    }

    private function vytvorUzivatele(): int
    {
        return (int) \Uzivatel::rychloregistrace(
            SystemoveNastaveni::zGlobals(),
            [
                UzivatelSql::EMAIL1_UZIVATELE => 'obnova.hesla.log.' . uniqid('', true) . '@example.test',
            ],
        );
    }

    private function zapisZadosti(
        string $ip,
        ?int $idUzivatele,
        bool $odeslano,
        int $pocet,
        int $predMinutami,
    ): void {
        for ($i = 0; $i < $pocet; ++$i) {
            dbInsert(Sql::OBNOVA_HESLA_LOG_TABULKA, [
                Sql::IP           => $ip,
                Sql::ID_UZIVATELE => $idUzivatele,
                Sql::ODESLANO     => (int) $odeslano,
                Sql::KDY          => dbOneCol('SELECT NOW() - INTERVAL $0 MINUTE', [$predMinutami]),
            ]);
        }
    }
}
