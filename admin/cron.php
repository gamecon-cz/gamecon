<?php

use Gamecon\Kanaly\GcMail;
use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\HromadneAkceAktivit;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Skript který je hostingem automaticky spouštěn jednou za hodinu. Standardní
 * limit vykonání je 90 sekund jako jinde na webu.
 */

require_once __DIR__ . '/cron/_cron_zavadec.php';

/////////////////////////////////// příprava ///////////////////////////////////

$logdir = SPEC . '/logs';
(new Filesystem())->mkdir($logdir);

// otestovat, že je skript volán s heslem a sprvánou url
if (HTTPS_ONLY) {
    httpsOnly();
}

if (!defined('CRON_KEY') || (string)CRON_KEY === '') {
    die('Není nastaven CRON klíč');
}

if (get('key') !== CRON_KEY) {
    $pocetChybnychPokusu   = 0;
    $invalidCronKeyLogFile = $logdir . '/invalid_cron_key.log';
    if (file_exists($invalidCronKeyLogFile)) {
        $invalidCronKeyLogContent = file_get_contents($invalidCronKeyLogFile);
        if ($invalidCronKeyLogContent) {
            $predchoziChybnyPokus = json_decode($invalidCronKeyLogContent, true);
            $pocetChybnychPokusu  = $predchoziChybnyPokus['attempts_count'] ?? 0;
            $prodlevaSekund       = 10 * $pocetChybnychPokusu;
            if ($pocetChybnychPokusu > 0 && ($predchoziChybnyPokus['at'] ?? false) && ($predchoziChybnyPokus['at'] + $prodlevaSekund) >= time()) {
                die('Na další pokus ještě počkej');
            }
        }
    }
    $pocetChybnychPokusu++;
    file_put_contents($invalidCronKeyLogFile,
        json_encode([
            'request_uri'    => $_SERVER['REQUEST_URI'],
            'referer'        => $_SERVER['HTTP_REFERER'] ?? null,
            'at'             => time(),
            'attempts_count' => $pocetChybnychPokusu,
        ]),
    );
    die('špatný klíč');
}

$job = get('job');
if ($job !== null) {
    require __DIR__ . '/cron/_cron_job.php';
    return;
}

// otevřít log soubor pro zápis a přesměrovat do něj výstup
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

require __DIR__ . '/cron/fio_stazeni_novych_plateb.php';

logs('Odemykám zamčené týmové aktivity...');
global $systemoveNastaveni;
$odemcenoTymovychAktivit = (new HromadneAkceAktivit($systemoveNastaveni))
    ->odemciTeamoveHromadne(Uzivatel::zId(Uzivatel::SYSTEM, true));
logs("odemčeno $odemcenoTymovychAktivit týmových aktivit.");

logs('Zamykám před veřejností už běžící, dosud nezamčené aktivity...');
$idsZamcenmych  = Aktivita::zamkniZacinajiciDo(new DateTimeImmutable('-' . AUTOMATICKY_UZAMKNOUT_AKTIVITU_X_MINUT_PO_ZACATKU . ' minutes'));
$pocetZamcenych = count($idsZamcenmych);
logs("zamčeno $pocetZamcenych aktivit.");

logs('Odesílám vypravěčům připomenutí, že nezavřeli prezenci...');
$konciciOd       = new DateTimeImmutable('-' . UPOZORNIT_NA_NEUZAMKNUTOU_AKTIVITU_X_MINUT_PO_KONCI . ' minutes');
$konciciDo       = $konciciOd->modify('+ 1 hour'); // interval CRONu - abychom nespamovali každou hodinu
$pocetUpozorneni = Aktivita::upozorniNaNeuzavreneKonciciOdDo(
    $konciciOd,
    $konciciDo,
);
logs("Odesláno $pocetUpozorneni mailů.");

if (date('G') >= 5) { // 5 hodin ráno či později
    include __DIR__ . '/cron/zaloha_databaze.php';
}

logs('Cron dokončen.');
