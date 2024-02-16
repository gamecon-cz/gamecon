<?php

use Godric\DbMigrations\DbMigrations;
use Godric\DbMigrations\DbMigrationsConfig;
use Gamecon\SystemoveNastaveni\AnonymizovanaDatabaze;

$connection   = dbConnectTemporary(false /* bez konkrétní databáze */);
$dbMigrations = new DbMigrations(
    new DbMigrationsConfig(
        connection: $connection, // musí mít admin práva
        migrationsDirectory: SQL_MIGRACE_DIR,
        doBackups: false,
        useWebGui: true,
    ),
);
if (!defined('UNIT_TESTS') || !UNIT_TESTS) {
    $confirmedDatabase = dbOneCol(
        sprintf("SHOW DATABASES LIKE '%s'", DB_NAME),
        null,
        $connection,
    );
    if ($confirmedDatabase !== DB_NAME) {
        $dbMigrations->getWebGui()?->configureEnvironment();
        if ($dbMigrations->getWebGui()?->confirm(false)) {
            $dbMigrations->getWebGui()?->writeMessage(sprintf("Vytvářím databázi '%s'...", DB_NAME), '');
            dbQuery(
                sprintf(
                    "CREATE DATABASE IF NOT EXISTS `%s` DEFAULT CHARACTER SET utf8 COLLATE utf8_czech_ci",
                    DB_NAME,
                ),
                null,
                $connection,
            );
            dbQuery(sprintf('USE `%s`', DB_NAME), null, $connection);
            $dbMigrations->getWebGui()?->writeMessage(' vytvořena.');
            $dbMigrations->getWebGui()?->writeMessage('Nahrávám anonymizovanou databázi...', '');
            (new \MySQLImport($connection))->load(__DIR__ . '/../migrace/pomocne/gc_anonymizovana_databaze.sql');
            $dbMigrations->getWebGui()?->writeMessage(' nahrána.');
            $dbMigrations->getWebGui()?->writeMessage(
                sprintf(
                    "🔓 login: '%s', heslo: '%s'",
                    AnonymizovanaDatabaze::ADMIN_LOGIN,
                    AnonymizovanaDatabaze::ADMIN_PASSWORD,
                ),
            );
            $dbMigrations->getWebGui()?->cleanupEnvironment(false);
        }
    }
}
dbQuery(sprintf('USE `%s`', DB_NAME), null, $connection);

$dbMigrations->run();

$dbMigrations->getWebGui()?->cleanupEnvironment();
