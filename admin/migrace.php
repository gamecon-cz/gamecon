<?php

use Godric\DbMigrations\DbMigrationsConfig;
use Godric\DbMigrations\DbMigrations;

require __DIR__ . '/../nastaveni/zavadec-zaklad.php';

if (HTTPS_ONLY) {
    httpsOnly();
}

// ověřit, že je posláno správné heslo
if (post('migraceHeslo') !== MIGRACE_HESLO) {
    http_response_code(403);
    die("Chyba: Je nutné zadat správné heslo v POST datech.\n");
}

// zapnout zobrazení chyb
ini_set('display_errors', true); // zobrazovat chyby obecně
ini_set('error_reporting', E_ALL ^ E_STRICT); // vybrat typy chyb k zobrazení
ini_set('html_errors', false); // chyby zobrazovat jako plaintext

// spustit migrace
pripravCache(SPEC . '/db-backup');
(new DbMigrations(new DbMigrationsConfig([
    'connection' => new mysqli(DBM_SERV, DBM_USER, DBM_PASS, DBM_NAME, defined('DBM_PORT') ? DBM_PORT : null),
    'migrationsDirectory' => __DIR__ . '/../migrace',
    'backupsDirectory' => SPEC . '/db-backup',
])))->run();

// informovat, že skript doběhl
echo "admin/migrace.php: Migrace dokončeny.\n";
