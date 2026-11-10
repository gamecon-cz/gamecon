<?php

declare(strict_types=1);

use Gamecon\ArchivPrednasek\Import\Arcitabulka;
use Gamecon\ArchivPrednasek\Import\ImportArcitabulky;
use Symfony\Component\Filesystem\Filesystem;

/** @var Godric\DbMigrations\Migration $this */

// Naplní archiv přednášek z „Arcitabulky přednášek“ (beze změny v migrace/pomocne/archiv-prednasek)
// a přidá přednášky z programu, které v ní chyběly. Co se s daty děje (sjednocení, párování, napojení
// přednášejících), je v Gamecon\ArchivPrednasek\Import\DataArcitabulky. Zapisuje přes spojení migrace,
// takže se při chybě vrátí celé.
$zpravy = (new ImportArcitabulky($this->connection))->importuj(
    (new Arcitabulka())->nactiSoubor(__DIR__ . '/pomocne/archiv-prednasek/arcitabulka-prednasek.tsv'),
);

// co zůstalo nespárované nebo nenapojené (aktivita nebo uživatel s jiným ID než v kopii ostré databáze)
if ($zpravy !== []) {
    (new Filesystem())->mkdir(LOGY, 0775);
    file_put_contents(
        LOGY . '/archiv-prednasek-import.log',
        date('Y-m-d H:i:s') . "\n" . implode("\n", $zpravy) . "\n",
        FILE_APPEND,
    );
}
