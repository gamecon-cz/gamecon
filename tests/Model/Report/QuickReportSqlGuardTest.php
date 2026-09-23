<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Report;

use Gamecon\Report\Exceptions\QuickReportSqlNotAllowed;
use Gamecon\Report\QuickReportSqlGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class QuickReportSqlGuardTest extends TestCase
{
    #[DataProvider('povoleneDotazy')]
    public function testPovoliJedenCteciDotaz(string $sql): void
    {
        (new QuickReportSqlGuard())->assertSingleReadStatement($sql);

        $this->expectNotToPerformAssertions();
    }

    public static function povoleneDotazy(): array
    {
        return [
            'select'                        => ['SELECT * FROM akce_seznam'],
            'malá písmena'                  => ['select 1'],
            'středník na konci'             => ["SELECT 1\r\n;\r\n"],
            'středník v řetězci'            => ["SELECT CONCAT('ruční kontrola; poznámka: ', 'x')"],
            'středník v uvozovkách'         => ['SELECT "a;b"'],
            'středník v identifikátoru'     => ['SELECT 1 AS `a;b`'],
            'lomítko v řetězci'             => ["SELECT 'C:\\\\temp'"],
            'zdvojený apostrof'             => ["SELECT 'it''s; fine'"],
            'středník v komentáři'          => ['SELECT 1 /* a; b */'],
            'středník v řádkovém komentáři' => ["SELECT 1 -- a; b\n"],
            'středník v hash komentáři'     => ["SELECT 1 # a; b\n"],
            'komentář před dotazem'         => ["-- počet lidí\nSELECT 1"],
            'závorky a union'               => ['(SELECT 1) UNION (SELECT 2)'],
            'common table expression'       => ['WITH x AS (SELECT 1 AS a) SELECT a FROM x'],
            'show'                          => ['SHOW CREATE TABLE akce_seznam'],
            'explain'                       => ['EXPLAIN SELECT * FROM akce_seznam'],
            'describe'                      => ['DESCRIBE akce_seznam'],
            'slovo update ve jménu sloupce' => ['SELECT posledni_update FROM x'],
        ];
    }

    #[DataProvider('zakazaneDotazy')]
    public function testOdmitneDotazKteryMuzeZapisovat(string $sql): void
    {
        $this->expectException(QuickReportSqlNotAllowed::class);

        (new QuickReportSqlGuard())->assertSingleReadStatement($sql);
    }

    public static function zakazaneDotazy(): array
    {
        return [
            'dva dotazy'                          => ['SELECT 1; SELECT 2'],
            'vypnutí read-only a zápis'           => ['SET SESSION TRANSACTION READ WRITE; DELETE FROM akce_seznam'],
            'select a pak zápis'                  => ['SELECT 1; DELETE FROM akce_seznam'],
            'zápis za řetězcem končícím lomítkem' => ["SELECT 'a\\\\'; DELETE FROM akce_seznam"],
            'set statement v jednom dotazu'       => ['SET STATEMENT tx_read_only = 0 FOR DELETE FROM akce_seznam'],
            'transakce pro zápis'                 => ['START TRANSACTION READ WRITE'],
            'execute immediate'                   => ["EXECUTE IMMEDIATE CONCAT('UPD', 'ATE akce_seznam SET rok = 1')"],
            'update'                              => ['UPDATE akce_seznam SET rok = 1'],
            'analyze spouští dotaz'               => ['ANALYZE UPDATE akce_seznam SET rok = 1'],
            'zápis schovaný za komentářem'        => ['/* SELECT */ DELETE FROM akce_seznam'],
            'spustitelný komentář'                => ['SELECT 1 /*!, (SELECT 2) */'],
            'spustitelný komentář MariaDB'        => ['SELECT 1 /*M!100000 , 2 */'],
            'zápis do souboru'                    => ["SELECT * FROM uzivatele_hodnoty INTO OUTFILE '/tmp/x'"],
            'zápis dumpu do souboru'              => ["SELECT 1 INTO DUMPFILE '/tmp/x'"],
            'prázdný dotaz'                       => ['  ;  '],
            // whether \' ends a string depends on the server's sql_mode, a report must not rely on either
            'escapovaný apostrof'              => ["SELECT 'it\\'s; fine'"],
            'zápis bez escapování lomítkem'    => ["SELECT '\\' , 1 INTO OUTFILE '/x' -- '"],
            'zápis s escapováním lomítkem'     => ["SELECT 'a\\'' ; DELETE FROM akce_seznam '"],
            'komentář ukončený řídicím znakem' => ["SELECT * FROM uzivatele_hodnoty --\x01 '\nINTO OUTFILE '/x' -- '"],
        ];
    }
}
