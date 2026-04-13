<?php

/** @var \Godric\DbMigrations\Migration $this */

/* Convert all tables from utf8mb3 to utf8mb4 */

/* This migration converts all remaining tables that still use utf8mb3 charset
   to utf8mb4, which is the modern standard and supports full Unicode including
   emojis and other 4-byte characters. */

$tables = [
    'akce_import',
    'akce_instance',
    'akce_organizatori',
    'akce_prihlaseni',
    'akce_prihlaseni_log',
    'akce_prihlaseni_spec',
    'akce_prihlaseni_stavy',
    'akce_sjednocene_tagy',
    'akce_stav',
    'akce_stavy_log',
    'akce_typy',
    'google_api_user_tokens',
    'google_drive_dirs',
    'hromadne_akce_log',
    'kategorie_sjednocenych_tagu',
    'log_udalosti',
    'lokace',
    'medailonky',
    'migrations',
    'mutex',
    'novinky',
    'obchod_bunky',
    'obchod_mrizky',
    'platby',
    'prava_role',
    'reporty',
    'reporty_log_pouziti',
    'reporty_quick',
    'role_seznam',
    'role_texty_podle_uzivatele',
    'r_prava_soupis',
    'shop_nakupy',
    // shop_nakupy_zrusene: uses database default charset (utf8mb4) from creation
    'shop_predmety',
    'sjednocene_tagy',
    'slevy',
    'stranky',
    'systemove_nastaveni',
    'systemove_nastaveni_log',
    'ubytovani',
    'uzivatele_role',
    'uzivatele_role_log',
    'uzivatele_slucovani_log',
    'uzivatele_url',
    '_vars',
];

$dbName = $this->q("SELECT DATABASE()")->fetchColumn();

foreach ($tables as $table) {
    // Check if table exists
    $tableExistsResult = $this->q("SELECT COUNT(*) AS cnt FROM information_schema.TABLES WHERE TABLE_SCHEMA = '$dbName' AND TABLE_NAME = '$table'");
    if ((int) $tableExistsResult->fetch(\PDO::FETCH_ASSOC)['cnt'] === 0) {
        continue;
    }

    // Check if already utf8mb4
    $charsetResult = $this->q("SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = '$dbName' AND TABLE_NAME = '$table'");
    $collation = $charsetResult->fetch(\PDO::FETCH_ASSOC)['TABLE_COLLATION'] ?? '';
    if (str_starts_with($collation, 'utf8mb4_')) {
        continue; // Already utf8mb4
    }

    // For tables with FK constraints referencing columns that will change type,
    // temporarily disable FK checks
    try {
        $this->q("SET FOREIGN_KEY_CHECKS = 0");
        $this->q("ALTER TABLE `$table` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci");
        $this->q("SET FOREIGN_KEY_CHECKS = 1");
    } catch (\Throwable $exception) {
        $this->q("SET FOREIGN_KEY_CHECKS = 1");
        throw $exception;
    }
}
