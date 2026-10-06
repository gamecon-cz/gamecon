<?php

use Gamecon\Web\QrOdkaz;
use Gamecon\XTemplate\XTemplate;

/**
 * nazev: Editace stránek
 * pravo: 105
 */

if (get('id') || get('akce') === 'nova') {
    // režim editace
    $f = Stranka::form(get('id'));
    $f->processPost();
    echo $f->full();
    $stranka = get('id') ? Stranka::zId(get('id')) : null;
    if ($stranka) {
        echo (new QrOdkaz($stranka->urlNaWebu(), 'qr-' . str_replace('/', '-', $stranka->url())))->html();
    }
    return;
}

$t = new XTemplate(__DIR__ . '/editace-stranek.xtpl');
$t->parseEach(Stranka::zVsech(), 'stranka', 'editaceStranek.radek');
$t->parse('editaceStranek');
$t->out('editaceStranek');
