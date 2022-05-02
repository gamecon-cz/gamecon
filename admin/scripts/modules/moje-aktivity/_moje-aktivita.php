<?php

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\OnlinePrezence\OnlinePrezenceHtml;
use Gamecon\Aktivita\OnlinePrezence\OnlinePrezenceAjax;
use Gamecon\Vyjimkovac\Vyjimkovac;

/**
 * Online formulář na zadávání příchozích a nedorazivších na aktivity
 *
 * nazev: Moje aktivita
 * pravo: 109
 */

/**
 * @var Uzivatel $u
 * @var bool $testovani
 */

$aktivita = Aktivita::zId(get('id'));
$problem = require __DIR__ . '/_moje-aktivita-problem.php';

if ($problem) {
    return;
}

global $BEZ_DEKORACE;
$BEZ_DEKORACE = true; // pokud nedoslo k chybě, tak nechceme levé menu, ale pouze nový čistý layout pro prezenci, viz admin/index.php

$onlinePrezenceHtml = new OnlinePrezenceHtml(
    Vyjimkovac::js(URL_WEBU),
    (int)MOJE_AKTIVITY_NA_POSLEDNI_CHVILI_X_MINUT_PRED_JEJICH_ZACATKEM
);
$onlinePrezenceAjax = new OnlinePrezenceAjax($onlinePrezenceHtml, new \Symfony\Component\Filesystem\Filesystem());

if ($onlinePrezenceAjax->odbavAjax($u)) {
    return;
}

echo $onlinePrezenceHtml->dejHtmlOnlinePrezence($u, [$aktivita]);
