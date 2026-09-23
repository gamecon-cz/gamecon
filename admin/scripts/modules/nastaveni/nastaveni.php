<?php

use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\SystemoveNastaveni\SystemoveNastaveniAjax;
use Gamecon\SystemoveNastaveni\SystemoveNastaveniHtml;
use Gamecon\Vyjimkovac\Vyjimkovac;

/**
 * nazev: Nastavení
 * pravo: 110 Administrace - panel Nastavení
 */

/**
 * @var Uzivatel $u
 * @var SystemoveNastaveni $systemoveNastaveni
 * @var Vyjimkovac $vyjimkovac
 */

// Web a admin sdílí session, takže na dotaz na stav kopírování nestačí samotné přihlášení.
$_SESSION[SystemoveNastaveniHtml::SMI_VIDET_STAV_KOPIE_SESSION_KLIC] = true;

$nastaveniHtml = new SystemoveNastaveniHtml($systemoveNastaveni);
$nastaveniAjax = new SystemoveNastaveniAjax($systemoveNastaveni, $nastaveniHtml, $u, $vyjimkovac);

if ($nastaveniAjax->zpracujPost()) {
    exit;
}

if ($nastaveniHtml->zpracujPost($u)) {
    back();
}

$nastaveniHtml->zobrazHtml();
