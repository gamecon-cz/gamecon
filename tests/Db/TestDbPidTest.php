<?php

declare(strict_types=1);

namespace Gamecon\Tests\Db;

use PHPUnit\Framework\TestCase;

/**
 * Rozhodnutí, kterou testovací databázi smí úklid zahodit. Špatná odpověď v jednom směru
 * nechá ležet zbytky, v druhém rozstřílí běh, který zrovna běží.
 */
class TestDbPidTest extends TestCase
{
    public function testPidSeVyctePrefixuZobouTvaru(): void
    {
        self::assertSame(1234, TestDbPid::pid('gamecon_test_1234'));
        self::assertSame(1234, TestDbPid::pid('gamecon_test_anonym_1234'));
        self::assertSame(99, TestDbPid::pid('gamecon_test_soucasna_99'));
    }

    public function testJmenoBezPiduPidNema(): void
    {
        self::assertNull(TestDbPid::pid('gamecon_test_1_stary_tvar_bez_cisla'));
        self::assertNull(TestDbPid::pid('gamecon_test_anonym'));
    }

    /**
     * Databáze po procesu, který ještě běží, patří jemu — sáhnout na ni znamená vzít mu ji
     * uprostřed běhu.
     */
    public function testDatabazeZijicihoProcesuSeNezahazuje(): void
    {
        self::assertFalse(TestDbPid::jeOpustena('gamecon_test_' . getmypid()));
    }

    public function testDatabazePoMrtvemProcesuSeZahodi(): void
    {
        self::assertTrue(TestDbPid::jeOpustena('gamecon_test_' . self::mrtvyPid()));
        self::assertTrue(TestDbPid::jeOpustena('gamecon_test_anonym_' . self::mrtvyPid()));
    }

    /**
     * Jméno bez čitelného PID se nechá ležet. Zbytek stojí kus místa, ale smazaná
     * databáze živého běhu ho rozstřílí — a odvozená jména vznikají snadno, viz
     * `AnonymizovanaDatabazeTest`, které skládá `DB_NAME . '_anonym_' . getmypid()`.
     */
    public function testJmenoBezPiduSeRadejiNechaLezet(): void
    {
        self::assertFalse(TestDbPid::jeOpustena('gamecon_test_6aac0ae843a9b3.54428808'));
        self::assertFalse(TestDbPid::jeOpustena('gamecon_test_anonym'));
    }

    /**
     * Odvozené jméno si musí PID nechat na konci, jinak ho úklid nepozná a smaže databázi
     * běhu, který ji právě používá.
     */
    public function testOdvozeneJmenoSPidemNaKonciPatriZivemuBehu(): void
    {
        $anonymni = 'gamecon_test_6aac0ae843a9b3.54428808_' . getmypid() . '_anonym_' . getmypid();

        self::assertFalse(TestDbPid::jeOpustena($anonymni));
    }

    /**
     * PID, který nikdo nedrží. Hledá se odshora, protože vysoká čísla se přidělují
     * nejpozději; `/proc` se ptá stejně jako produkční kód, aby test nemohl projít kvůli
     * téže chybě, kterou má hlídat (`posix_kill()` vrací false i na živý cizí proces).
     */
    /**
     * Adresář cache se jmenuje jen PID, tedy jiný tvar než databáze.
     */
    public function testAdresarPoMrtvemProcesuSeUklidi(): void
    {
        self::assertTrue(TestDbPid::jeOpustenyAdresar('/cache/private/tests/' . self::mrtvyPid()));
    }

    public function testAdresarBeziciehoBehuZustane(): void
    {
        self::assertFalse(TestDbPid::jeOpustenyAdresar('/cache/private/tests/' . getmypid()));
        self::assertFalse(TestDbPid::jeOpustenyAdresar('/cache/private/tests/neco'));
    }

    private static function mrtvyPid(): int
    {
        $pidMax = (int) trim(@file_get_contents('/proc/sys/kernel/pid_max') ?: '4194304');
        for ($pid = $pidMax; $pid > 1; --$pid) {
            if (! is_dir('/proc/' . $pid)) {
                return $pid;
            }
        }

        self::fail('Nenašel se volný PID');
    }
}
