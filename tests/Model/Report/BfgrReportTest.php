<?php

namespace Gamecon\Tests\Model\Report;

use Gamecon\Report\BfgrReport;
use Gamecon\Stat;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Uzivatel\Pohlavi;
use Gamecon\Uzivatel\SqlStruktura\UzivateleHodnotySqlStruktura as UzivatelSql;

class BfgrReportTest extends AbstractTestDb
{
    private const ID_UZDLUZNIKA = 2;

    protected static function getInitCallbacks(): array
    {
        return [
            function () {
                dbInsert(UzivatelSql::UZIVATELE_HODNOTY_TABULKA,
                    [
                        UzivatelSql::ID_UZIVATELE                        => self::ID_UZDLUZNIKA,
                        UzivatelSql::OP                                  => '123456789',
                        UzivatelSql::POHLAVI                             => Pohlavi::MUZ_KOD,
                        UzivatelSql::LOGIN_UZIVATELE                     => 'Šmajdalf',
                        UzivatelSql::JMENO_UZIVATELE                     => 'Shmay',
                        UzivatelSql::PRIJMENI_UZIVATELE                  => 'Dalph',
                        UzivatelSql::ULICE_A_CP_UZIVATELE                => 'Tady a teď 1',
                        UzivatelSql::MESTO_UZIVATELE                     => 'Hůlkovice',
                        UzivatelSql::STAT_UZIVATELE                      => Stat::CZ_ID,
                        UzivatelSql::PSC_UZIVATELE                       => '007',
                        UzivatelSql::TELEFON_UZIVATELE                   => '0609 222 111',
                        UzivatelSql::DATUM_NAROZENI                      => '1980-01-02 03:04:05',
                        UzivatelSql::HESLO_MD5                           => '',
                        UzivatelSql::FUNKCE_UZIVATELE                    => 0,
                        UzivatelSql::EMAIL1_UZIVATELE                    => 'gandalf@soutechrep.gov',
                        UzivatelSql::EMAIL2_UZIVATELE                    => '',
                        UzivatelSql::JINE_UZIVATELE                      => '',
                        UzivatelSql::NECHCE_MAILY                        => '0',
                        UzivatelSql::MRTVY_MAIL                          => '0',
                        UzivatelSql::FORUM_RAZENI                        => '',
                        UzivatelSql::RANDOM                              => '',
                        UzivatelSql::ZUSTATEK                            => -999.99,
                        UzivatelSql::REGISTROVAN                         => '2020-07-15 14:15:16',
                        UzivatelSql::UBYTOVAN_S                          => '',
                        UzivatelSql::SKOLA                               => '',
                        UzivatelSql::POZNAMKA                            => '',
                        UzivatelSql::POMOC_TYP                           => '',
                        UzivatelSql::POMOC_VICE                          => '',
                        UzivatelSql::TYP_DOKLADU_TOTOZNOSTI              => '',
                        UzivatelSql::STATNI_OBCANSTVI                    => 'ČR',
                        UzivatelSql::POTVRZENI_ZAKONNEHO_ZASTUPCE        => null,
                        UzivatelSql::POTVRZENI_PROTI_COVID19_PRIDANO_KDY => null,
                        UzivatelSql::POTVRZENI_PROTI_COVID19_OVERENO_KDY => null,
                        UzivatelSql::INFOPULT_POZNAMKA                   => '',
                        UzivatelSql::Z_RYCHLOREGISTRACE                  => 0,
                    ],
                );
            },
        ];
    }

    /**
     * @test
     */
    public function Bfgr_report_odpovida_ocekavani()
    {
        $tmpFile = sys_get_temp_dir() . '/' . uniqid('BFGR_test_', true);
        $bfgr    = new BfgrReport(SystemoveNastaveni::zGlobals());
        $bfgr->exportuj('csv', true, $tmpFile);
        self::assertFileExists($tmpFile, 'BFGR nebyl exportován do souboru');
        return;
        $data = file_get_contents($tmpFile);
        $data = str_getcsv($data);
        self::assertGreaterThan(0, count($data), 'BFGR je prázdný');
    }
}
