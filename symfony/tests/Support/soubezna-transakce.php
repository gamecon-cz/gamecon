<?php

declare(strict_types=1);

/*
 * The other side of a race, run as a separate process by SoubeznaTransakce: a deadlock needs
 * both transactions waiting at once, which one PHP thread cannot do. Rolled back at the end
 * unless a `potvrd` step committed it.
 */

$zadani = json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR);

$spojeni = new PDO($zadani['dsn'], $zadani['user'], $zadani['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$spojeni->exec('SET SESSION innodb_lock_wait_timeout = 5');
$spojeni->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
$spojeni->beginTransaction();

try {
    foreach ($zadani['kroky'] as [$druh, $hodnota]) {
        match ($druh) {
            'sql'    => $spojeni->query($hodnota)->fetchAll(),
            'cekej'  => usleep($hodnota * 1000),
            'hlasim' => print $hodnota . "\n",
            'potvrd' => $spojeni->commit(),
        };
        fflush(STDOUT);
    }
    if ($spojeni->inTransaction()) {
        $spojeni->rollBack();
    }
    echo "hotovo\n";
} catch (PDOException $chyba) {
    echo 'chyba ' . ($chyba->errorInfo[1] ?? $chyba->getCode()) . ' ' . $chyba->getMessage() . "\n";
}
