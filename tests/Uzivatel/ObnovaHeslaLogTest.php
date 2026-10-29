<?php

declare(strict_types=1);

namespace Gamecon\Tests\Uzivatel;

use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Uzivatel\ObnovaHeslaLog;
use Gamecon\Uzivatel\SqlStruktura\ObnovaHeslaLogSqlStruktura as Sql;
use Gamecon\Uzivatel\SqlStruktura\UzivateleHodnotySqlStruktura as UzivatelSql;

class ObnovaHeslaLogTest extends AbstractTestDb
{
    private const IP = '192.0.2.1';

    public function testLimitUctuPocitaJenOdeslaneMailyZaPosledniHodinu(): void
    {
        $idUzivatele = $this->vytvorUzivatele();
        $obnovaHeslaLog = new ObnovaHeslaLog();
        $this->zapisZadosti(self::IP, $idUzivatele, true, ObnovaHeslaLog::MAX_MAILU_NA_UCET, ObnovaHeslaLog::OKNO_MINUT + 1);
        $this->zapisZadosti(self::IP, $idUzivatele, false, ObnovaHeslaLog::MAX_MAILU_NA_UCET, 0);
        $this->zapisZadosti(self::IP, $idUzivatele, true, ObnovaHeslaLog::MAX_MAILU_NA_UCET - 1, 0);

        self::assertFalse(
            $obnovaHeslaLog->jePrekrocenLimitUctu($idUzivatele),
            'Starší maily a žádosti, po kterých mail neodešel, se do limitu účtu nepočítají',
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
            $obnovaHeslaLog->jePrekrocenLimitIp(self::IP),
            'Starší žádosti a žádosti z jiné IP se do limitu nepočítají',
        );

        $obnovaHeslaLog->zaznamenej(self::IP, null, false);

        self::assertTrue($obnovaHeslaLog->jePrekrocenLimitIp(self::IP));
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
