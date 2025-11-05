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

$nastaveniHtml = new SystemoveNastaveniHtml($systemoveNastaveni);
$nastaveniAjax = new SystemoveNastaveniAjax($systemoveNastaveni, $nastaveniHtml, $u, $vyjimkovac);

// AJAX endpoint pro stav kopírování databáze
if (get('ajax') === SystemoveNastaveniHtml::AJAX_STAV_KOPIE_KLIC) {
    $nastaveniHtml->ajaxStavKopieDatabazeZOstre();
    exit;
}

if ($nastaveniAjax->zpracujPost()) {
    exit;
}

if ($nastaveniHtml->zpracujPost($u)) {
    back();
}

$nastaveniHtml->zobrazHtml();
