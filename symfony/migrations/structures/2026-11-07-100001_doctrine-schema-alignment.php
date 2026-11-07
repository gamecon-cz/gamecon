<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Brings the schema to what Doctrine generates, so that doctrine:schema:validate stays clean. Nothing here
// is about the shop. A database restored from production already has most of these names, a fresh one built
// from the old migrations does not, hence every step checks before it acts.

$databaseName = $this->q('SELECT DATABASE()')->fetchColumn();

$tableExists = fn (string $table): bool => (bool) $this->q(<<<SQL
SELECT EXISTS (
    SELECT 1
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = '{$databaseName}'
      AND TABLE_NAME = '{$table}'
)
SQL)->fetchColumn();

$columnExists = fn (string $table, string $column): bool => (bool) $this->q(<<<SQL
SELECT EXISTS (
    SELECT 1
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = '{$databaseName}'
      AND TABLE_NAME = '{$table}'
      AND COLUMN_NAME = '{$column}'
)
SQL)->fetchColumn();

$indexExists = fn (string $table, string $index): bool => (bool) $this->q(<<<SQL
SELECT EXISTS (
    SELECT 1
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = '{$databaseName}'
      AND TABLE_NAME = '{$table}'
      AND INDEX_NAME = '{$index}'
)
SQL)->fetchColumn();

$renameIndex = function (string $table, string $oldName, string $newName) use ($indexExists): void {
    if ($indexExists($table, $oldName) && ! $indexExists($table, $newName)) {
        $this->q("ALTER TABLE `{$table}` RENAME INDEX `{$oldName}` TO `{$newName}`");
    }
};

// The new tables reference these keys as BIGINT UNSIGNED, and a foreign key needs the exact same type. The
// anonymized development dump still has INT(11).
foreach ([
    'shop_predmety'      => 'id_predmetu',
    'uzivatele_hodnoty'  => 'id_uzivatele',
] as $table => $column) {
    $columnType = $this->q(<<<SQL
SELECT COLUMN_TYPE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = '{$databaseName}'
  AND TABLE_NAME = '{$table}'
  AND COLUMN_NAME = '{$column}'
SQL)->fetchColumn();
    if ($columnType && ! str_contains($columnType, 'bigint')) {
        $this->q('SET FOREIGN_KEY_CHECKS = 0');
        $this->q("ALTER TABLE `{$table}` MODIFY `{$column}` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT");
        $this->q('SET FOREIGN_KEY_CHECKS = 1');
    }
}

// The activity-to-location link is a plain many-to-many now; the main location lives on akce_seznam.
if ($columnExists('akce_lokace', 'id_akce_lokace')) {
    $this->q('ALTER TABLE akce_lokace MODIFY id_akce_lokace BIGINT UNSIGNED NOT NULL');
    if ($indexExists('akce_lokace', 'id_akce_lokace')) {
        $this->q('DROP INDEX id_akce_lokace ON akce_lokace');
    }
    $this->q('ALTER TABLE akce_lokace DROP id_akce_lokace');
}
if ($columnExists('akce_lokace', 'je_hlavni')) {
    $this->q('ALTER TABLE akce_lokace DROP je_hlavni');
}
$renameIndex('akce_lokace', 'fk_akce_lokace_lokace', 'IDX_49CCDE68259B4755');

// table, current name, name Doctrine expects
foreach ([
    ['stranky',                       'url_stranky', 'UNIQ_3D4EE408803DB254'],
    ['lokace',                        'nazev', 'UNIQ_nazev_rok'],
    ['reporty_log_pouziti',           'id_reportu', 'IDX_FEAC86E4C6E1AB00'],
    ['reporty_log_pouziti',           'id_uzivatele', 'IDX_FEAC86E4D84E9520'],
    ['reporty_log_pouziti',           'id_reportu_2', 'IDX_id_reportu_id_uzivatele'],
    ['akce_seznam',                   'patri_pod', 'IDX_2EE8EBF0AF219F1D'],
    ['akce_seznam',                   'typ', 'IDX_2EE8EBF0241AA1D'],
    ['akce_seznam',                   'stav', 'IDX_2EE8EBF0CEB69E0D'],
    ['akce_seznam',                   'zamcel', 'IDX_2EE8EBF0607A39CF'],
    ['akce_seznam',                   'rok', 'IDX_rok'],
    ['akce_seznam',                   'url_akce', 'UNIQ_url_akce_rok_typ'],
    ['ubytovani',                     'id_uzivatele', 'IDX_F483CEC3D84E9520'],
    ['uzivatele_role_podle_rocniku',  'id_uzivatele', 'IDX_9204F263D84E9520'],
    ['uzivatele_role_podle_rocniku',  'id_role', 'IDX_9204F263DC499668'],
    ['uzivatele_role_podle_rocniku',  'rocnik', 'idx_uzivatele_role_podle_rocniku_rocnik'],
    ['google_api_user_tokens',        'user_id_2', 'IDX_9A526EB4A76ED395'],
    ['google_api_user_tokens',        'user_id', 'UNIQ_user_id_google_client_id'],
    ['platby',                        'id_uzivatele', 'IDX_4852A679D84E9520'],
    ['platby',                        'provedl', 'IDX_4852A67969513658'],
    ['platby',                        'id_uzivatele_2', 'IDX_id_uzivatele_rok'],
    ['platby',                        'fio_id', 'UNIQ_fio_id'],
    ['kategorie_sjednocenych_tagu',   'id_hlavni_kategorie', 'IDX_A82F4189FF2287A1'],
    ['kategorie_sjednocenych_tagu',   'nazev', 'UNIQ_nazev'],
    ['log_udalosti',                  'id_logujiciho', 'IDX_459DF155498E1820'],
    ['log_udalosti',                  'metadata', 'IDX_metadata'],
    ['uzivatele_role',                'id_uzivatele_2', 'IDX_4F909638D84E9520'],
    ['uzivatele_role',                'id_role', 'IDX_4F909638DC499668'],
    ['uzivatele_role',                'posadil', 'IDX_4F909638AAB26C2'],
    ['uzivatele_role',                'id_uzivatele', 'UNIQ_id_uzivatele_id_role'],
    ['akce_typy',                     'stranka_o', 'IDX_C12F7955DC7C4C42'],
    ['uzivatele_hodnoty',             'infopult_poznamka', 'IDX_infopult_poznamka'],
    ['uzivatele_hodnoty',             'login_uzivatele', 'UNIQ_login_uzivatele'],
    ['uzivatele_hodnoty',             'email1_uzivatele', 'UNIQ_email1_uzivatele'],
    ['novinky',                       'url', 'UNIQ_url'],
    ['google_drive_dirs',             'user_id_2', 'IDX_9E13BEAFA76ED395'],
    ['google_drive_dirs',             'tag', 'IDX_tag'],
    ['google_drive_dirs',             'dir_id', 'UNIQ_dir_id'],
    ['google_drive_dirs',             'user_id', 'UNIQ_user_and_name'],
    ['systemove_nastaveni',           'skupina', 'IDX_skupina'],
    ['systemove_nastaveni',           'klic', 'UNIQ_klic_rocnik_nastaveni'],
    ['systemove_nastaveni',           'nazev', 'UNIQ_nazev_rocnik_nastaveni'],
    ['akce_import',                   'id_uzivatele', 'IDX_D72EE2CDD84E9520'],
    ['akce_import',                   'google_sheet_id', 'IDX_google_sheet_id'],
    ['slevy',                         'id_uzivatele', 'IDX_17003B9AD84E9520'],
    ['slevy',                         'provedl', 'IDX_17003B9A69513658'],
    ['hromadne_akce_log',             'provedl', 'IDX_E0A93D8A69513658'],
    ['hromadne_akce_log',             'akce', 'IDX_akce'],
    ['role_seznam',                   'typ_role', 'IDX_typ_role'],
    ['role_seznam',                   'vyznam_role', 'IDX_vyznam_role'],
    ['role_seznam',                   'kod_role', 'UNIQ_kod_role'],
    ['role_seznam',                   'nazev_role', 'UNIQ_nazev_role'],
    ['akce_prihlaseni_log',           'id_akce', 'IDX_947919F21E74DA0A'],
    ['akce_prihlaseni_log',           'id_uzivatele', 'IDX_947919F2D84E9520'],
    ['akce_prihlaseni_log',           'id_zmenil', 'IDX_947919F2E2649593'],
    ['akce_prihlaseni_log',           'typ', 'IDX_typ'],
    ['akce_prihlaseni_log',           'zdroj_zmeny', 'IDX_zdroj_zmeny'],
    ['akce_prihlaseni',               'id_akce_2', 'IDX_7B7E722B1E74DA0A'],
    ['akce_prihlaseni',               'id_uzivatele', 'IDX_7B7E722BD84E9520'],
    ['akce_prihlaseni',               'id_stavu_prihlaseni', 'IDX_7B7E722B55D06BC9'],
    ['akce_prihlaseni',               'id_akce', 'UNIQ_id_akce_id_uzivatele'],
    ['shop_nakupy_zrusene',           'id_uzivatele', 'IDX_id_uzivatele'],
    ['shop_nakupy_zrusene',           'datum_zruseni', 'IDX_datum_zruseni'],
    ['shop_nakupy_zrusene',           'zdroj_zruseni', 'IDX_zdroj_zruseni'],
    ['akce_stavy_log',                'id_akce', 'IDX_195FCE481E74DA0A'],
    ['akce_stavy_log',                'id_stav', 'IDX_195FCE484596820F'],
    ['uzivatele_url',                 'url', 'UNIQ_BA2D6079F47645AE'],
    ['uzivatele_url',                 'id_uzivatele', 'IDX_BA2D6079D84E9520'],
    ['systemove_nastaveni_log',       'id_uzivatele', 'IDX_8F9C0959D84E9520'],
    ['systemove_nastaveni_log',       'id_nastaveni', 'IDX_8F9C0959C8E5E058'],
    ['reporty',                       'skript', 'UNIQ_skript'],
    ['role_texty_podle_uzivatele',    'id_uzivatele_2', 'IDX_D4CCA4DFD84E9520'],
    ['role_texty_podle_uzivatele',    'id_uzivatele', 'UNIQ_id_uzivatele_vyznam_role'],
    ['akce_sjednocene_tagy',          'id_akce', 'IDX_714E29671E74DA0A'],
    ['akce_sjednocene_tagy',          'id_tagu', 'IDX_714E2967DFF2D11'],
    ['prava_role',                    'id_role', 'IDX_57A9921ADC499668'],
    ['prava_role',                    'id_prava', 'IDX_57A9921A1A86105C'],
    ['sjednocene_tagy',               'id_kategorie_tagu', 'IDX_EEE57AA8FFC91574'],
    ['sjednocene_tagy',               'nazev', 'UNIQ_nazev'],
    ['mutex',                         'klic', 'UNIQ_EECDB22FEC2A7D56'],
    ['mutex',                         'zamknul', 'IDX_EECDB22FCF5C09F0'],
    ['mutex',                         'akce', 'UNIQ_akce'],
    ['obchod_bunky',                  'mrizka_id', 'IDX_2DA00FBEE5BF0939'],
    ['shop_nakupy',                   'id_uzivatele', 'IDX_1A37DD21D84E9520'],
    ['shop_nakupy',                   'id_objednatele', 'IDX_1A37DD218369B810'],
    ['shop_nakupy',                   'rok', 'IDX_rok_id_uzivatele'],
    ['akce_stav',                     'nazev', 'UNIQ_nazev'],
    ['akce_instance',                 'id_hlavni_akce', 'IDX_F1D05242895FCA4C'],
    ['akce_prihlaseni_spec',          'id_akce_2', 'IDX_78A8F4401E74DA0A'],
    ['akce_prihlaseni_spec',          'id_uzivatele', 'IDX_78A8F440D84E9520'],
    ['akce_prihlaseni_spec',          'id_stavu_prihlaseni', 'IDX_78A8F44055D06BC9'],
    ['akce_prihlaseni_spec',          'id_akce', 'UNIQ_id_akce_id_uzivatele'],
    ['uzivatele_slucovani_log',       'id_smazaneho_uzivatele', 'IDX_smazany_uzivatel'],
    ['uzivatele_slucovani_log',       'id_noveho_uzivatele', 'IDX_novy_uzivatel'],
    ['uzivatele_slucovani_log',       'kdy', 'IDX_kdy'],
    ['uzivatele_role_log',            'id_uzivatele', 'IDX_9977B328D84E9520'],
    ['uzivatele_role_log',            'id_role', 'IDX_9977B328DC499668'],
    ['uzivatele_role_log',            'id_zmenil', 'IDX_9977B328E2649593'],
    ['akce_organizatori',             'id_akce', 'IDX_F44FC74E1E74DA0A'],
    ['akce_organizatori',             'id_uzivatele', 'IDX_F44FC74ED84E9520'],
] as [$table, $oldName, $newName]) {
    $renameIndex($table, $oldName, $newName);
}

// Tables are named in the singular. The rename comes after the list above, which still names the indexes of
// the plural tables.
foreach (['google_api_user_tokens' => 'google_api_user_token', 'google_drive_dirs' => 'google_drive_dir'] as $oldName => $newName) {
    if ($tableExists($oldName) && ! $tableExists($newName)) {
        $this->q("RENAME TABLE `{$oldName}` TO `{$newName}`");
    }
}
$renameIndex('google_api_user_token', 'idx_9a526eb4a76ed395', 'IDX_E2B772D4A76ED395');
$renameIndex('google_drive_dir', 'idx_9e13beafa76ed395', 'IDX_78417C52A76ED395');
