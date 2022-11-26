<?php

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
    return;
}

$t = new XTemplate('editace-stranek.xtpl');
$t->parseEach(Stranka::zVsech(), 'stranka', 'editaceStranek.radek');
$t->parse('editaceStranek');
$t->out('editaceStranek');
