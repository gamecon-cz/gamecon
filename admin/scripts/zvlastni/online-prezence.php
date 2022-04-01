<?php
/**
 * Vyplnění prezence a uzavření aktivity online.
 */

/** @var Uzivatel $u */
/** @var Uzivatel|null $uPracovni */

$onlinePrezenceHtml = new \Gamecon\Aktivita\OnlinePrezence\OnlinePrezenceHtml();
$onlinePrezenceAjax = new \Gamecon\Aktivita\OnlinePrezence\OnlinePrezenceAjax($onlinePrezenceHtml);

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

echo $onlinePrezenceHtml->dejHtmlOnlinePrezence(
    $aktivity,
    getBackUrl()
);
