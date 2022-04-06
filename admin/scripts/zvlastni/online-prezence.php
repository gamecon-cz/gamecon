<?php
/**
 * Vyplnění prezence a uzavření aktivity online.
 */

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\OnlinePrezence\OnlinePrezenceHtml;
use Gamecon\Aktivita\OnlinePrezence\OnlinePrezenceAjax;

/** @var Uzivatel $u */
/** @var Uzivatel|null $uPracovni */

$onlinePrezenceHtml = new OnlinePrezenceHtml();
$onlinePrezenceAjax = new OnlinePrezenceAjax($onlinePrezenceHtml);

if ($onlinePrezenceAjax->odbavAjax()) {
    return;
}

/** vhackování vybírátka času, funkce @see _casy */
require_once __DIR__ . '/../modules/prezence/_casy.php';

$zacatek = null; // bude nastaven přes referenci v nasledujici funkci
_casy($zacatek, true);

$aktivity = $zacatek
    ? Aktivita::zRozmezi($zacatek, $zacatek)
    : [];

echo $onlinePrezenceHtml->dejHtmlOnlinePrezence($aktivity);
