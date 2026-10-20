<?php

declare(strict_types=1);

/**
 * Potomek testu KvsDatabazeTest: otevře každou z databází tak, jak to dělá kvs(), a zapíše do ní.
 * Každý výsledek vypíše na jeden řádek, bez chyby `OK`.
 */

require_once __DIR__ . '/../../model/funkce/funkce.php';

$zacatek = $argv[1];
$databaze = array_slice($argv, 2);

foreach ($databaze as $poradi => $soubor) {
    // všechny procesy otevírají tutéž databázi ve stejný okamžik
    while (microtime(true) < (float) $zacatek + $poradi * 0.05) {
    }
    try {
        $db = kvsOtevriDatabazi($soubor);
        $db->enableExceptions(true);
        $db->exec('CREATE TABLE IF NOT EXISTS kvs (k INTEGER PRIMARY KEY, v TEXT)');
        $db->exec('INSERT OR REPLACE INTO kvs (k, v) VALUES (' . getmypid() . ", 'x')");
        $db->close();
        echo "OK\n";
    } catch (Throwable $throwable) {
        echo basename($soubor) . ': ' . $throwable->getMessage() . "\n";
    }
}
