<?php

use Gamecon\Kanaly\GcMail;
use Gamecon\Aktivita\Aktivita;

/**
 * Skript který je hostingem automaticky spouštěn jednou za hodinu. Standardní
 * limit vykonání je 90 sekund jako jinde na webu.
 */

require_once __DIR__ . '/cron/cron_zavadec.php';

/////////////////////////////////// příprava ///////////////////////////////////

// otestovat, že je skript volán s heslem a sprvánou url
if (HTTPS_ONLY) {
    httpsOnly();
}

if (!defined('CRON_KEY') || get('key') !== CRON_KEY) {
    die('špatný klíč');
}

$job = get('job');
if ($job !== null) {
    if ($job === 'odhlaseni_neplaticu') {
        require __DIR__ . '/cron/odhlaseni_neplaticu.php';
        return;
    }
    throw new \RuntimeException(sprintf("Invalid job '%s'", $job));
}

// otevřít log soubor pro zápis a přesměrovat do něj výstup
$logdir  = SPEC . '/logs';
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
$idsZamcenmych  = Aktivita::zamkniZacinajiciDo(new DateTimeImmutable('-' . AUTOMATICKY_UZAMKNOUT_AKTIVITU_X_MINUT_PO_ZACATKU . ' minutes'));
$pocetZamcenych = count($idsZamcenmych);
logs("zamčeno $pocetZamcenych aktivit.");

logs('Odesílám vypravěčům připomenutí, že nezavřeli prezenci...');
$konciciOd       = new DateTimeImmutable('-' . UPOZORNIT_NA_NEUZAMKNUTOU_AKTIVITU_X_MINUT_PO_KONCI . ' minutes');
$konciciDo       = $konciciOd->modify('+ 1 hour'); // interval CRONu - abychom nespamovali každou hodinu
$pocetUpozorneni = Aktivita::upozorniNaNeuzavreneKonciciOdDo(
    $konciciOd,
    $konciciDo
);
logs("Odesláno $pocetUpozorneni mailů.");

if (date('G') >= 5) { // 5 hodin ráno či později
    include __DIR__ . '/cron/zaloha_databaze.php';
}

logs('Cron dokončen.');
