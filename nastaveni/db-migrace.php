<?php

if (AUTOMATICKA_TVORBA_DB) {
    $connection = dbConnect(false /* bez konkrétní databáze */);
    $confirmedDatabase = dbOneCol(sprintf("SHOW DATABASES LIKE '%s'", DB_NAME));
    if ($confirmedDatabase !== DB_NAME) {
        dbQuery(sprintf("CREATE DATABASE IF NOT EXISTS `%s` DEFAULT CHARACTER SET utf8 COLLATE utf8_czech_ci", DB_NAME));
    }
    dbQuery(sprintf('USE `%s`', DB_NAME));
}

(new Godric\DbMigrations\DbMigrations([
    'connection'          =>  dbConnect(), // musí mít admin práva
    'migrationsDirectory' =>  __DIR__ . '/../migrace',
    'doBackups'           =>  false,
    'checkInitialMigrationChanges' => false,
    'webGui'              =>  true,
]))->run();