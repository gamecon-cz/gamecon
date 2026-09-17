<?php

declare(strict_types=1);

namespace Gamecon\Tests\Db;

/**
 * Jméno testovací databáze nese PID běhu, který ji vytvořil — podle něj se pozná, co je
 * po havárii zbytek k úklidu a co patří běhu, který zrovna běží.
 *
 * Bez toho úklid zahazoval všechny databáze s prefixem, takže dva souběžné běhy se
 * navzájem zničily: ten, co skončil dřív, vzal databázi pod rukama tomu druhému.
 */
final class TestDbPid
{
    /**
     * Databáze po procesu, který už neběží — nikomu nepatří a jde zahodit.
     *
     * Jméno bez čitelného PID se za opuštěné NEpovažuje. Nechat ležet zbytek stojí kus
     * tmpfs; smazat databázi, kterou někdo právě používá, rozstřílí cizí běh — a přesně
     * to je chyba, kvůli které tahle třída vznikla. Každé jméno testovací databáze proto
     * musí končit PID; kdo skládá odvozené jméno, musí PID nechat na konci.
     */
    public static function jeOpustena(string $dbName): bool
    {
        $pid = self::pid($dbName);

        return $pid !== null && $pid !== getmypid() && ! self::bezi($pid);
    }

    /**
     * Adresář cache pojmenovaný jen PID (`cache/private/tests/5852`) — jiný tvar než
     * databáze, proto vlastní metoda. Apache workery po sobě neuklízejí (nemají shutdown
     * handler), takže jejich adresáře musí sklidit až běh, který je přežije.
     */
    public static function jeOpustenyAdresar(string $cesta): bool
    {
        $pid = basename($cesta);
        if (! preg_match('~^\d+$~', $pid)) {
            return false;
        }

        return (int) $pid !== getmypid() && ! self::bezi((int) $pid);
    }

    /**
     * PID z konce názvu, např. `gamecon_test_6aac0ba3e864b0.80223216_5852` → 5852.
     */
    public static function pid(string $dbName): ?int
    {
        if (! preg_match('~_(\d+)$~', $dbName, $shoda)) {
            return null;
        }

        return (int) $shoda[1];
    }

    private static function bezi(int $pid): bool
    {
        // `/proc` má přednost před `posix_kill()`: ten vrací false i na živý proces cizího
        // uživatele (EPERM k nerozeznání od „neexistuje"), takže běh puštěný bez
        // `--user=www-data` by cizí databázi prohlásil za zbytek. Měřeno na PID 1.
        if (is_dir('/proc')) {
            return is_dir('/proc/' . $pid);
        }

        return function_exists('posix_kill') && posix_kill($pid, 0);
    }
}
