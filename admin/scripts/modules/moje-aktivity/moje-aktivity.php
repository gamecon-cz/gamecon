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

if (get('id')) {
    require __DIR__ . '/_moje-aktivita.php';
    return;
}

global $BEZ_DEKORACE;
$BEZ_DEKORACE = true; // pokud nedoslo k chybě, tak nechceme levé menu, ale pouze nový čistý layout pro prezenci, viz admin/index.php

$onlinePrezenceHtml = new OnlinePrezenceHtml(
    Vyjimkovac::js(URL_WEBU),
    (int)MOJE_AKTIVITY_NA_POSLEDNI_CHVILI_X_MINUT_PRED_JEJICH_ZACATKEM
);
$onlinePrezenceAjax = new OnlinePrezenceAjax($onlinePrezenceHtml);

if ($onlinePrezenceAjax->odbavAjax()) {
    return;
}

$now = new DateTimeImmutable();

$testovani = defined('TESTING') && TESTING && !empty($_GET['test']);

if ($testovani) {
    $onlinePrezenceTestovaciAktivity = new OnlinePrezenceTestovaciAktivity(
        Gamecon\Aktivita\Aktivita::dejPrazdnou(),
        Stav::dejPrazdny()
    );
    $organizovaneAktivity = $onlinePrezenceTestovaciAktivity->dejTestovaciAktivity();
    $onlinePrezenceTestovaciAktivity->upravZacatkyAktivitNaParSekundPredEditovatelnosti($organizovaneAktivity, $now, 20);
    if (count($organizovaneAktivity) > 2) {
        // aby první dvě aktivity začínaly teď a neměli proto odpočet
        $onlinePrezenceTestovaciAktivity->upravZacatkyPrvnichAktivitNa($organizovaneAktivity, 2, $now);
    }
} else {
    $organizovaneAktivity = Aktivita::zFiltru(
        ['rok' => ROK, 'organizator' => $u->id()],
        ['zacatek']
    );
}

echo $onlinePrezenceHtml->dejHtmlOnlinePrezence(
    $organizovaneAktivity,
    (int)MOJE_AKTIVITY_EDITOVATELNE_X_MINUT_PRED_JEJICH_ZACATKEM,
    $now,
    getCurrentUrlPath() . '/..'
);
