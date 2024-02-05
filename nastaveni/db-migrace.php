<?php

use Godric\DbMigrations\DbMigrations;
use Godric\DbMigrations\DbMigrationsConfig;

$connection = dbConnectTemporary(false /* bez konkrétní databáze */);
if (AUTOMATICKA_TVORBA_DB) {
    $confirmedDatabase = dbOneCol(
        sprintf("SHOW DATABASES LIKE '%s'", DB_NAME),
        null,
        $connection,
    );
    if ($confirmedDatabase !== DB_NAME) {
        dbQuery(
            sprintf(
                "CREATE DATABASE IF NOT EXISTS `%s` DEFAULT CHARACTER SET utf8 COLLATE utf8_czech_ci",
                DB_NAME,
            ),
            null,
            $connection,
        );
        dbQuery(sprintf('USE `%s`', DB_NAME), null, $connection);
        (new \MySQLImport($connection))->load(__DIR__ . '/../migrace/pomocne/gc_anonymizovana_databaze.sql');
    } else {
        dbQuery(sprintf('USE `%s`', DB_NAME), null, $connection);
    }
}

(new DbMigrations(
    new DbMigrationsConfig([
        'connection'          => $connection, // musí mít admin práva
        'migrationsDirectory' => __DIR__ . '/../migrace',
        'doBackups'           => false,
        'webGui'              => true,
    ]),
))->run();
