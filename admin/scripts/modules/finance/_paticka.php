<?php
use Gamecon\XTemplate\XTemplate;

$x = new XTemplate('_paticka.xtpl');
$x->assign(['kurzEura' => KURZ_EURO]);
// skutečná cesta ke skriptu, aby při jeho případném přejmenování vědělo IDE i o tomto odkazu (název souboru = URL)
$x->assign(['nastaveniUrl' => URL_ADMIN . '/' . basename(__DIR__ . '/../../../scripts/modules/nastaveni/nastaveni.php', '.php')]);

$x->parse('paticka.kurz');
$x->parse('paticka');
$x->out('paticka');
