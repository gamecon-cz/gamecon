<?php
/**
 * Vyplnění prezence a uzavření aktivity online.
 */

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\OnlinePrezence\OnlinePrezenceHtml;
use Gamecon\Aktivita\OnlinePrezence\OnlinePrezenceAjax;
use Gamecon\Vyjimkovac\Vyjimkovac;
use Gamecon\Pravo;
use Symfony\Component\Filesystem\Filesystem;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

/**
 * @var Uzivatel $u
 * @var Uzivatel|null $uPracovni
 * @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni
 */

if (!$u || !$u->maPravo(Pravo::ADMINISTRACE_PREZENCE)) {
    back(URL_WEBU);
    exit;
}

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

/** vhackování vybírátka času, funkce @see _casy */
require_once __DIR__ . '/../modules/prezence/_casy.php';

$zacatek = null; // bude nastaven přes referenci v nasledujici funkci
_casy($zacatek, true);

$aktivity = $zacatek
    ? Aktivita::zRozmezi($zacatek, $zacatek)
    : [];

echo $onlinePrezenceHtml->dejHtmlOnlinePrezence($u, $aktivity);
