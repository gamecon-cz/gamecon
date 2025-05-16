<?php

use Gamecon\Aktivita\OnlinePrezence\OnlinePrezenceHtml;
use Gamecon\Aktivita\OnlinePrezence\OnlinePrezenceAjax;
use Gamecon\Aktivita\OnlinePrezence\OnlinePrezenceTestovaciAktivity;
use Gamecon\Vyjimkovac\Vyjimkovac;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Úvodní karta organizátora s přehledem jeho aktivit
 *
 * nazev: Moje aktivity
 * pravo: 109
 */

/**
 * @var Uzivatel $u
 * @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni
 */

$muzemeTestovat = defined('TESTING') && TESTING
                  && defined('TEST_MAZAT_VSECHNA_RAZITKA_POSLEDNICH_ZMEN') && TEST_MAZAT_VSECHNA_RAZITKA_POSLEDNICH_ZMEN;
$testujeme      = $muzemeTestovat && !empty($_GET['test']);

if (get('id')) {
    require __DIR__ . '/_moje-aktivita.php';

    return;
}

global $BEZ_DEKORACE;
$BEZ_DEKORACE = true; // pokud nedoslo k chybě, tak nechceme levé menu, ale pouze nový čistý layout pro prezenci, viz admin/index.php

$filesystem         = new Filesystem();
$onlinePrezenceHtml = new OnlinePrezenceHtml(
    Vyjimkovac::js(URL_WEBU),
    $systemoveNastaveni,
    $filesystem,
    $muzemeTestovat,
    $testujeme,
);
$onlinePrezenceAjax = new OnlinePrezenceAjax(
    $onlinePrezenceHtml,
    $filesystem,
    $systemoveNastaveni,
    $testujeme,
);

if ($onlinePrezenceAjax->odbavAjax($u)) {
    return;
}

$ted = $systemoveNastaveni->ted();

if ($testujeme) {
    $onlinePrezenceTestovaciAktivity = OnlinePrezenceTestovaciAktivity::vytvor(
        $systemoveNastaveni,
    );
    $organizovaneAktivity            = $onlinePrezenceTestovaciAktivity->dejTestovaciAktivity();
    $onlinePrezenceTestovaciAktivity->upravZacatkyAktivitNaParSekundPredEditovatelnosti($organizovaneAktivity, $ted, 20);
    $onlinePrezenceTestovaciAktivity->upravKonceAktivitNa($organizovaneAktivity, (clone $ted)->modify('+1 hour'));
    if (count($organizovaneAktivity) > 2) {
        $prvniDveAktivity = array_slice($organizovaneAktivity, 0, 2);
        // aby první dvě aktivity začínaly teď a neměli proto odpočet
        $onlinePrezenceTestovaciAktivity->upravZacatkyAktivitNa($prvniDveAktivity, $ted);
        // aby už skončily a zobrazilo se tak u nich varování
        $onlinePrezenceTestovaciAktivity->upravKonceAktivitNa($prvniDveAktivity, $ted);
    }
    if (count($organizovaneAktivity) > 3) {
        // aby jedna aktivita po chvíli skončila a zobrazila tak varování
        $aktivitaSRychlymKoncem = $organizovaneAktivity[2];
        $onlinePrezenceTestovaciAktivity->upravKonceAktivitNaSekundyPoOdemceni($aktivitaSRychlymKoncem, 5);
    }
} else {
    $organizovaneAktivity = $u->organizovaneAktivity();
}

echo $onlinePrezenceHtml->dejHtmlOnlinePrezence($u, $organizovaneAktivity);
