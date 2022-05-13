<?php

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\OnlinePrezence\OnlinePrezenceHtml;
use Gamecon\Aktivita\OnlinePrezence\OnlinePrezenceAjax;
use Gamecon\Aktivita\OnlinePrezence\OnlinePrezenceTestovaciAktivity;
use Gamecon\Vyjimkovac\Vyjimkovac;

/**
 * Úvodní karta organizátora s přehledem jeho aktivit
 *
 * nazev: Moje aktivity
 * pravo: 109
 */

/**
 * @var Uzivatel $u
 */

$muzemeTestovat = defined('TESTING') && TESTING
    && defined('TEST_MAZAT_VSECHNA_RAZITKA_POSLEDNICH_ZMEN') && TEST_MAZAT_VSECHNA_RAZITKA_POSLEDNICH_ZMEN;
$testujeme = $muzemeTestovat && !empty($_GET['test']);

if (get('id')) {
    require __DIR__ . '/_moje-aktivita.php';
    return;
}

global $BEZ_DEKORACE;
$BEZ_DEKORACE = true; // pokud nedoslo k chybě, tak nechceme levé menu, ale pouze nový čistý layout pro prezenci, viz admin/index.php

$onlinePrezenceHtml = new OnlinePrezenceHtml(
    Vyjimkovac::js(URL_WEBU),
    (int)MOJE_AKTIVITY_NA_POSLEDNI_CHVILI_X_MINUT_PRED_JEJICH_ZACATKEM,
    $muzemeTestovat,
    $testujeme
);
$onlinePrezenceAjax = new OnlinePrezenceAjax($onlinePrezenceHtml, new \Symfony\Component\Filesystem\Filesystem());

if ($onlinePrezenceAjax->odbavAjax($u)) {
    return;
}

$now = new DateTimeImmutable();

if ($testujeme) {
    $onlinePrezenceTestovaciAktivity = OnlinePrezenceTestovaciAktivity::vytvor();
    $organizovaneAktivity = $onlinePrezenceTestovaciAktivity->dejTestovaciAktivity();
    $onlinePrezenceTestovaciAktivity->upravZacatkyAktivitNaParSekundPredEditovatelnosti($organizovaneAktivity, $now, 20);
    $onlinePrezenceTestovaciAktivity->upravKonceAktivitNa($organizovaneAktivity, (clone $now)->modify('+1 hour'));
    if (count($organizovaneAktivity) > 2) {
        $prvniDveAktivity = array_slice($organizovaneAktivity, 0, 2);
        // aby první dvě aktivity začínaly teď a neměli proto odpočet
        $onlinePrezenceTestovaciAktivity->upravZacatkyAktivitNa($prvniDveAktivity, $now);
        // aby už skončily a zobrazilo se tak u nich varování
        $onlinePrezenceTestovaciAktivity->upravKonceAktivitNa($prvniDveAktivity, $now);
    }
    if (count($organizovaneAktivity) > 3) {
        // aby jedna aktivita po chvíli skončila a zobrazila tak varování
        $aktivitaSRychlymKoncem = $organizovaneAktivity[2];
        $onlinePrezenceTestovaciAktivity->upravKonceAktivitNaSekundyPoOdemceni($aktivitaSRychlymKoncem, 5);
    }
} else {
    $organizovaneAktivity = $u->organizovaneAktivity();
}

echo $onlinePrezenceHtml->dejHtmlOnlinePrezence(
    $u,
    $organizovaneAktivity,
    (int)MOJE_AKTIVITY_EDITOVATELNE_X_MINUT_PRED_JEJICH_ZACATKEM,
    $now,
);
