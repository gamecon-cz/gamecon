<?php

/**
 * nazev: Medailonky
 * pravo: 105
 * submenu_group: 2
 * submenu_order: 2
 */

use Gamecon\XTemplate\XTemplate;

if (get('id') !== null) {
    $f = Medailonek::form(get('id'));
    $f->processPost();
    echo $f->full();
    return; // nezobrazovat věci níž
}

if (post('noveId')) {
    dbInsertIgnore('medailonky', ['id_uzivatele' => post('noveId'), 'o_sobe' => '', 'drd' => '']);
    oznameni('vytvořeno', false);
    back(getCurrentUrlWithQuery(['id' => post('noveId')]));
}

$t = new XTemplate('medailonky.xtpl');
$t->parseEach(Medailonek::zVsech(), 'medailonek', 'medailonky.radek');
$t->parse('medailonky');
$t->out('medailonky');
