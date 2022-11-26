<?php

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\OnlinePrezence\OnlinePrezenceHtml;
use Gamecon\Aktivita\OnlinePrezence\OnlinePrezenceAjax;
use Gamecon\Vyjimkovac\Vyjimkovac;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Online formulář na zadávání příchozích a nedorazivších na aktivity
 *
 * nazev: Moje aktivita
 * pravo: 109
 */

/**
 * @var Uzivatel $u
 * @var bool $testujeme
 * @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni
 */

$aktivita = Aktivita::zId(get('id'));
$problem = require __DIR__ . '/_moje-aktivita-problem.php';

if ($problem) {
    return;
}

global $BEZ_DEKORACE;
$BEZ_DEKORACE = true; // pokud nedoslo k chybě, tak nechceme levé menu, ale pouze nový čistý layout pro prezenci, viz admin/index.php

$filesystem = new Filesystem();
$onlinePrezenceHtml = new OnlinePrezenceHtml(Vyjimkovac::js(URL_WEBU), $systemoveNastaveni, $filesystem);
$onlinePrezenceAjax = new OnlinePrezenceAjax(
    $onlinePrezenceHtml,
    $filesystem,
    $systemoveNastaveni,
    false
);

if ($onlinePrezenceAjax->odbavAjax($u)) {
    return;
}

echo $onlinePrezenceHtml->dejHtmlOnlinePrezence($u, [$aktivita]);
