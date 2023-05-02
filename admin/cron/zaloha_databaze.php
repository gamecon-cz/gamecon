<?php

use Gamecon\Kanaly\GcMail;
use Ifsnop\Mysqldump\Mysqldump;
use Gamecon\Cas\DateTimeCz;
use Gamecon\SystemoveNastaveni\NastrojeDatabaze;

require_once __DIR__ . '/_cron_zavadec.php';

$dnesniZalohaPattern = ZALOHA_DB_SLOZKA . '/export_' . date('Y-m-d_') . '[0-9][0-9][0-9][0-9][0-9][0-9].sql.gz';
if (!glob($dnesniZalohaPattern) || getopt('', ['force']) || !empty($vynutZalohuDatabaze)) { // dnešní záloha databáze ještě neexistuje
    logs(sprintf("Zálohuji databázi '%s'...", DBM_NAME));
    $chybaZalohovaniDb = null;
    if (!defined('ZALOHA_DB_SLOZKA') || !ZALOHA_DB_SLOZKA) {
        $chybaZalohovaniDb = 'Není definována konstanta s adresářem pro zálohování ZALOHA_DB_SLOZKA.';
    } elseif (!is_dir(ZALOHA_DB_SLOZKA)
        && !@mkdir(ZALOHA_DB_SLOZKA, 0750, true)
        && !is_dir(ZALOHA_DB_SLOZKA)
    ) {
        $chybaZalohovaniDb = "Nelze vytvořit adresář pro zálohování '" . ZALOHA_DB_SLOZKA . "': " . implode("\n", (array)error_get_last());
    } else {
        try {
            $time         = date('Y-m-d_His');
            $dbBackupFile = ZALOHA_DB_SLOZKA . "/export_$time.sql.gz";
            $mysqldump    = NastrojeDatabaze::vytvorZGlobals()->vytvorMysqldumpHlavniDatabaze(['compress' => Mysqldump::GZIP]);
            $mysqldump->start($dbBackupFile);
            logs("...záloha databáze dokončena do souboru $dbBackupFile", false);
            copy($dbBackupFile, ZALOHA_DB_SLOZKA . "/export_latest.sql.gz");
        } catch (\Throwable $throwable) {
            $chybaZalohovaniDb = 'Uložení zálohy na disk selhalo';
            logs('Error při ukládání zálohy DB: ' . $throwable->getMessage() . '; ' . $throwable->getTraceAsString());
        }
    }
    if ($chybaZalohovaniDb) {
        logs($chybaZalohovaniDb);
        (new GcMail)
            ->adresat('info@gamecon.cz')
            ->predmet('Neproběhla záloha databáze ' . date(DateTimeCz::FORMAT_DB))
            ->text($chybaZalohovaniDb)
            ->odeslat();
    }
}
