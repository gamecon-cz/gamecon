<?php

use Gamecon\XTemplate\XTemplate;
use Gamecon\SystemoveNastaveni\SystemoveNastaveniHtml;

$x = new XTemplate('_paticka.xtpl');
$x->assign(['kurzEura' => KURZ_EURO]);

$nastaveniBaseName = basename(__DIR__ . '/../../../scripts/modules/nastaveni/nastaveni.php', '.php');
// skutečná cesta ke skriptu, aby při jeho případném přejmenování vědělo IDE i o tomto odkazu (název souboru = URL)
$x->assign([
    'nastaveniUrl' => URL_ADMIN . '/' . $nastaveniBaseName . '?' . SystemoveNastaveniHtml::ZVYRAZNI . '=KURZ_EURO#KURZ_EURO',
]);

$x->parse('paticka.kurz');
$x->parse('paticka');
$x->out('paticka');
