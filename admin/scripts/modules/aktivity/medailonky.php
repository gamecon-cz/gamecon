<?php

/**
 * nazev: Medailonky
 * ikona: id-card
 * pravo: 105
 * submenu_group: 2
 * submenu_order: 2
 */

use Gamecon\Uzivatel\Medailonek;
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

$ikonaAno = '<i class="fa-solid fa-circle-check ikona-ano" role="img" aria-label="ano"></i>';
$ikonaNe = '<i class="fa-solid fa-circle-xmark ikona-ne" role="img" aria-label="ne"></i>';

$t = new XTemplate(__DIR__ . '/medailonky.xtpl');
foreach (Medailonek::zVsech() as $medailonek) {
    assert($medailonek instanceof Medailonek);
    $uzivatelMedailonku = Uzivatel::zId($medailonek->idUzivatele());
    $t->assign('uzivatelId', $uzivatelMedailonku->id());
    $t->assign('jmenoNick', $uzivatelMedailonku->jmenoNick());
    $t->assign('maPopisOSobe', trim($medailonek->oSobe()) !== '' ? $ikonaAno : $ikonaNe);
    $t->assign('oSobe', $medailonek->oSobe());
    $t->assign('maDrdPopis', trim($medailonek->drd()) !== '' ? $ikonaAno : $ikonaNe);
    $t->assign('drdPopis', $medailonek->drd());
    $t->assign('medailonek', $medailonek);
    $t->parse('medailonky.radek');
}
$t->parse('medailonky');
$t->out('medailonky');
