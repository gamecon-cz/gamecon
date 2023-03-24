<?php

$connection = dbConnect(false /* bez konkrétní databáze */);
if (AUTOMATICKA_TVORBA_DB) {
    $confirmedDatabase = dbOneCol(
        sprintf("SHOW DATABASES LIKE '%s'", DBM_NAME),
        null,
        $connection
    );
    if ($confirmedDatabase !== DBM_NAME) {
        dbQuery(
            sprintf(
                "CREATE DATABASE IF NOT EXISTS `%s` DEFAULT CHARACTER SET utf8 COLLATE utf8_czech_ci",
                DBM_NAME
            ),
            null,
            $connection
        );
    }
    dbQuery(sprintf('USE `%s`', DBM_NAME), null, $connection);
}

(new Godric\DbMigrations\DbMigrations(
    new \Godric\DbMigrations\DbMigrationsConfig([
        'connection'          => $connection, // musí mít admin práva
        'migrationsDirectory' => __DIR__ . '/../migrace',
        'doBackups'           => false,
        'webGui'              => true,
    ])
))->run();
