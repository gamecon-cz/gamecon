<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Výchozí collation databáze určuje i dočasné tabulky (migrační runner, importy),
// které se pak joinují se skutečnými sloupci.
$this->q('ALTER DATABASE CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci');

// Sloupce *_bin jsou binární záměrně (Doctrine JSON sloupce drží utf8mb4_bin),
// CONVERT TO by je převedl taky, proto se po převodu vrací jejich původní definice.
$tabulkyKPrevodu = $this->q(<<<SQL
SELECT tables.TABLE_NAME
FROM information_schema.TABLES AS tables
WHERE tables.TABLE_SCHEMA = DATABASE()
    AND tables.TABLE_TYPE = 'BASE TABLE'
    AND (
        tables.TABLE_COLLATION <> 'utf8mb4_czech_ci'
        OR EXISTS (
            SELECT 1
            FROM information_schema.COLUMNS AS columns
            WHERE columns.TABLE_SCHEMA = tables.TABLE_SCHEMA
                AND columns.TABLE_NAME = tables.TABLE_NAME
                AND columns.COLLATION_NAME <> 'utf8mb4_czech_ci'
                AND columns.COLLATION_NAME NOT LIKE '%\_bin'
        )
    )
ORDER BY tables.TABLE_NAME
SQL,
)->fetch_all();

foreach (array_column($tabulkyKPrevodu, 0) as $tabulka) {
    $binarniSloupce = array_column(
        $this->q(<<<SQL
SELECT COLUMN_NAME
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = '{$tabulka}'
    AND COLLATION_NAME LIKE '%\_bin'
SQL,
        )->fetch_all(),
        0,
    );

    $definiceBinarnichSloupcu = [];
    if ($binarniSloupce !== []) {
        $createTable = $this->q("SHOW CREATE TABLE `{$tabulka}`")->fetch_all()[0][1];
        foreach ($binarniSloupce as $sloupec) {
            if (preg_match('~^\s*(`' . preg_quote($sloupec, '~') . '` .*COLLATE \w+_bin.*?),?$~m', $createTable, $shoda) !== 1) {
                throw new RuntimeException("Nenalezena definice binárního sloupce {$tabulka}.{$sloupec} v:\n{$createTable}");
            }
            $definiceBinarnichSloupcu[] = 'MODIFY ' . str_replace('utf8mb3', 'utf8mb4', $shoda[1]);
        }
    }

    $this->q("ALTER TABLE `{$tabulka}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci");
    if ($definiceBinarnichSloupcu !== []) {
        $this->q("ALTER TABLE `{$tabulka}` " . implode(', ', $definiceBinarnichSloupcu));
    }
}
