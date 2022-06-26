<?php

/**
 * Skript který je hostingem automaticky spouštěn jednou za hodinu. Standardní
 * limit vykonání je 90 sekund jako jinde na webu.
 */

require __DIR__ . '/../nastaveni/zavadec.php';
/** @var \Gamecon\Vyjimkovac\Vyjimkovac $vyjimkovac */

// TODO nutný hack před zmergeování zavaděče mezi redesignem a masterem
// tato proměnná je nastavena zavaděčem a zde upravíme zobrazení výjimek
$vyjimkovac->zobrazeni(\Gamecon\Vyjimkovac\Vyjimkovac::PLAIN);

//////////////////////////////// pomocné funkce ////////////////////////////////

/**
 * Výstup do logu
 */
function logs($s) {
    echo date('Y-m-d H:i:s ') . $s . "\n";
}

/////////////////////////////////// příprava ///////////////////////////////////

// otestovat, že je skript volán s heslem a sprvánou url
if (HTTPS_ONLY) httpsOnly();
if (!defined('CRON_KEY') || get('key') !== CRON_KEY)
    die('špatný klíč');

// otevřít log soubor pro zápis a přesměrovat do něj výstup
$logdir = SPEC . '/logs';
$logfile = 'cron-' . date('Y-m') . '.log';
if (!is_dir($logdir))
    mkdir($logdir);
$logdescriptor = fopen($logdir . '/' . $logfile, 'ab');
ob_start(function ($string) use ($logdescriptor) {
    fwrite($logdescriptor, $string . "\n");
    fclose($logdescriptor);
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
        logs('platba ' . $p->id() . ' (' . $p->castka() . 'Kč, VS: ' . $p->vs() . ($p->zprava() ? ', zpráva: ' . $p->zprava() : '') . ')');
    }
    if (!$platby) logs('Žádné zaúčtovatelné platby.');
} else {
    logs('FIO_TOKEN není definován, přeskakuji nové platby.');
}

logs('Odemykám zamčené aktivity.');
$i = Aktivita::odemciHromadne();
logs("Odemčeno $i aktivit.");

if (date('G') == 5) { // 5 hodin ráno
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
            $dump->save(ZALOHA_DB_SLOZKA . "/export_$time.sql.gz");
            logs('záloha databáze dokončena.');
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

logs('Cron dokončen.');
