<?php
/** @var \Godric\DbMigrations\Migration $this */

$migrationCodes = [];
foreach (scandir(__DIR__, SCANDIR_SORT_NONE) as $file) {
    if (!preg_match('~^\d+[.]php~', $file)) {
        continue;
    }
    $migrationCodes[] = basename($file, '.php');
}
sort($migrationCodes);

$currentMigrationCode = basename(__FILE__, '.php');

$this->q(<<<SQL
CREATE TABLE migrations(migration_id SERIAL, migration_code VARCHAR(128) PRIMARY KEY, applied_at DATETIME NULL)
ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
SQL
);

foreach ($migrationCodes as $migrationCode) {
    $appliedAtSql = $migrationCode === $currentMigrationCode
        ? 'NOW()'
        : 'NULL';
    // populates by current as well previous migrations
    $this->q(<<<SQL
INSERT INTO migrations(migration_code, applied_at) VALUES ('$migrationCode', $appliedAtSql);
SQL
    );
}
