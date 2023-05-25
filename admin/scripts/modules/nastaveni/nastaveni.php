<?php

use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\SystemoveNastaveni\SystemoveNastaveniAjax;
use Gamecon\SystemoveNastaveni\SystemoveNastaveniHtml;

/**
 * nazev: Nastavení
 * pravo: 110 Administrace - panel Nastavení
 */

/**
 * @var Uzivatel $u
 * @var SystemoveNastaveni $systemoveNastaveni
 */

$nastaveniHtml = new SystemoveNastaveniHtml($systemoveNastaveni);
$nastaveniAjax = new SystemoveNastaveniAjax($systemoveNastaveni, $nastaveniHtml, $u);

if ($nastaveniAjax->zpracujPost()) {
    exit;
}

if ($nastaveniHtml->zpracujPost($u)) {
    back();
}

$nastaveniHtml->zobrazHtml();
