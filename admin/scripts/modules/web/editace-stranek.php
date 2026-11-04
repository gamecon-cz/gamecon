<?php

use Gamecon\Web\QrOdkaz;
use Gamecon\XTemplate\XTemplate;

/**
 * nazev: Editace stránek
 * ikona: pen-to-square
 * pravo: 105
 */

if (get('id') || get('akce') === 'nova') {
    // režim editace
    $f = Stranka::form(get('id'));
    $f->processPost();
    echo $f->full();
    // ne $stranka: modul běží ve scope admin/index.php, který si v $stranka drží název stránky adminu pro submenu
    $upravovanaStranka = get('id') ? Stranka::zId(get('id')) : null;
    if ($upravovanaStranka) {
        echo (new QrOdkaz($upravovanaStranka->urlNaWebu(), 'qr-' . str_replace('/', '-', $upravovanaStranka->url())))->html();
    }
    return;
}

$t = new XTemplate(__DIR__ . '/editace-stranek.xtpl');
$t->parseEach(Stranka::zVsech(), 'stranka', 'editaceStranek.radek');
$t->parse('editaceStranek');
$t->out('editaceStranek');
