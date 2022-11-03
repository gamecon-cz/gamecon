<?php

use Gamecon\Aktivita\Aktivita;

/**
 * Skript který je hostingem automaticky spouštěn jednou za hodinu. Standardní
 * limit vykonání je 90 sekund jako jinde na webu.
 */

require __DIR__ . '/../nastaveni/zavadec.php';

// TODO nutný hack před zmergeování zavaděče mezi redesignem a masterem
// tato proměnná je nastavena zavaděčem a zde upravíme zobrazení výjimek
/** @var \Gamecon\Vyjimkovac\Vyjimkovac $vyjimkovac */
$vyjimkovac->zobrazeni(\Gamecon\Vyjimkovac\Vyjimkovac::PLAIN);

//////////////////////////////// pomocné funkce ////////////////////////////////

/**
 * Výstup do logu
 */
function logs($s) {
    echo date('Y-m-d H:i:s ') . $s . "\n";
}

if (defined('TESTING') && TESTING && !defined('MAILY_DO_SOUBORU')) {
    define('MAILY_DO_SOUBORU', true);
}

/////////////////////////////////// příprava ///////////////////////////////////

// otestovat, že je skript volán s heslem a sprvánou url
if (HTTPS_ONLY) httpsOnly();
if (!defined('CRON_KEY') || get('key') !== CRON_KEY)
    die('špatný klíč');

// otevřít log soubor pro zápis a přesměrovat do něj výstup
$logdir = SPEC . '/logs';
$logfile = 'cron-' . date('Y-m') . '.log';
if (!is_dir($logdir) && !@mkdir($logdir) && !is_dir($logdir)) {
    throw new \RuntimeException(sprintf('Directory "%s" was not created', $logdir));
}
$logDescriptor = fopen($logdir . '/' . $logfile, 'ab');
ob_start(static function ($string) use ($logDescriptor) {
    fwrite($logDescriptor, $string . "\n");
    fclose($logDescriptor);
});

// zapnout zobrazení chyb
ini_set('display_errors', true); // zobrazovat chyby obecně
ini_set('error_reporting', E_ALL ^ E_STRICT); // vybrat typy chyb k zobrazení
ini_set('html_errors', false); // chyby zobrazovat jako plaintext

/////////////////////////////////// cron kód ///////////////////////////////////

logs('Začínám provádět cron script.');

if (defined('FIO_TOKEN') && FIO_TOKEN !== '') {
    logs('Zpracovávám nové platby přes Fio API.');
    $platby = Platby::nactiNove();
    foreach ($platby as $p) {
        logs('platba ' . $p->id()
            . ' (' . $p->castka() . 'Kč, VS: ' . $p->vs()
            . ($p->zpravaProPrijemce() ? ', zpráva: ' . $p->zpravaProPrijemce() : '')
            . ($p->poznamkaProMne() ? ', poznámka: ' . $p->poznamkaProMne() : '')
            . ')'
        );
    }
    if (!$platby) {
        logs('Žádné zaúčtovatelné platby.');
    }
} else {
    logs('FIO_TOKEN není definován, přeskakuji nové platby.');
}

logs('Odemykám zamčené týmové aktivity...');
$i = Aktivita::odemciTeamoveHromadne(Uzivatel::zId(Uzivatel::SYSTEM));
logs("odemčeno $i týmových aktivit.");

logs('Zamykám před veřejností už běžící, dosud nezamčené aktivity...');
$idsZamcenmych = Aktivita::zamkniZacinajiciDo(new DateTimeImmutable('-' . AUTOMATICKY_UZAMKNOUT_AKTIVITU_X_MINUT_PO_ZACATKU . ' minutes'));
$pocetZamcenych = count($idsZamcenmych);
logs("zamčeno $pocetZamcenych aktivit.");

logs('Odesílám vypravěčům připomenutí, že nezavřeli prezenci...');
$konciciOd = new DateTimeImmutable('-' . UPOZORNIT_NA_NEUZAMKNUTOU_AKTIVITU_X_MINUT_PO_KONCI . ' minutes');
$konciciDo = $konciciOd->modify('+ 1 hour'); // interval CRONu - abychom nespamovali každou hodinu
$pocetUpozorneni = Aktivita::upozorniNaNeuzavreneKonciciOdDo(
    $konciciOd,
    $konciciDo
);
logs("Odesláno $pocetUpozorneni mailů.");

if (date('G') >= 5) { // 5 hodin ráno či později
    $dnesniZalohaPattern = ZALOHA_DB_SLOZKA . '/export_' . date('Y-m-d_') . '[0-9][0-9][0-9][0-9][0-9][0-9].sql.gz';
    if (!glob($dnesniZalohaPattern)) { // dnešní záloha databáze ještě neexistuje
        logs('Zálohuji databázi...');
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
                $dump = new MySQLDump(dbConnect());
                $time = date('Y-m-d_His');
                $dbBackupFile = ZALOHA_DB_SLOZKA . "/export_$time.sql.gz";
                $dump->save($dbBackupFile);
                logs("...záloha databáze dokončena do souboru $dbBackupFile");
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
                ->predmet('Neproběhla záloha databáze ' . date(\Gamecon\Cas\DateTimeCz::FORMAT_DB))
                ->text($chybaZalohovaniDb)
                ->odeslat();
        }
    }
}

logs('Cron dokončen.');
