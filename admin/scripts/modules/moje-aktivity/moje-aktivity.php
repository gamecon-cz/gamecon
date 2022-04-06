<?php

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\OnlinePrezence\OnlinePrezenceHtml;
use Gamecon\Aktivita\OnlinePrezence\OnlinePrezenceAjax;

/**
 * Úvodní karta organizátora s přehledem jeho aktivit
 *
 * nazev: Moje aktivity
 * pravo: 109
 */

/**
 * @var Uzivatel $u
 */

$testovani = defined('TESTING') && TESTING && !empty($_GET['test']);

if (get('id')) {
    require __DIR__ . '/_moje-aktivita.php';
    return;
}

$organizovaneAktivityFiltr = ['rok' => ROK, 'organizator' => $u->id()];
if ($testovani) {
    unset($organizovaneAktivityFiltr['organizator']);
    $organizovaneAktivityFiltr['stav'] = [Stav::PRIPRAVENA, Stav::NOVA, Stav::AKTIVOVANA, Stav::PUBLIKOVANA];
}

$organizovaneAktivity = Aktivita::zFiltru(
    $organizovaneAktivityFiltr,
    ['zacatek'],
    $testovani
        ? 10
        : null
);

global $BEZ_DEKORACE;
$BEZ_DEKORACE = true; // pokud nedoslo k chybě, tak nechceme levé menu, ale pouze nový čistý layout pro prezenci, viz admin/index.php

$onlinePrezenceHtml = new OnlinePrezenceHtml();
$onlinePrezenceAjax = new OnlinePrezenceAjax($onlinePrezenceHtml);

if ($onlinePrezenceAjax->odbavAjax()) {
    return;
}

if ($testovani) {
    $zacatky = [];
    array_walk(
        $organizovaneAktivity,
        static function (Aktivita $aktivita) use (&$zacatky) {
            $zacatky[] = $aktivita->zacatek();
        }
    );
    $zacatky = array_filter($zacatky);
    $prvniZacatek = min($zacatky) ?: null; // kvůli testování odpočtu
    if ($prvniZacatek) {
        /** @var \Gamecon\Cas\DateTimeCz $prvniZacatek */
        $now = (clone $prvniZacatek)->modify('-' . (MOJE_AKTIVITY_EDITOVATELNE_X_MINUT_PRED_JEJICH_ZACATKEM * 60 + 10) . ' seconds');
        array_walk($organizovaneAktivity, static function (Aktivita $aktivita) use ($prvniZacatek) {
            $aReflection = (new ReflectionClass(Aktivita::class))->getProperty('a');
            $aReflection->setAccessible(true);
            $aValue = $aReflection->getValue($aktivita);
            $aValue['zacatek'] = (clone $prvniZacatek)->modify('+' . random_int(0, 10) . ' seconds');
            $aReflection->setValue($aktivita, $aValue);
        });
    }
} else {
    $now = new DateTimeImmutable();
}

echo $onlinePrezenceHtml->dejHtmlOnlinePrezence(
    $organizovaneAktivity,
    (int)MOJE_AKTIVITY_EDITOVATELNE_X_MINUT_PRED_JEJICH_ZACATKEM,
    $now,
    getCurrentUrlPath() . '/..'
);
