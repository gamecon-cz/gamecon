<?php

/**
 * nazev: Medailonky
 * ikona: id-card
 * pravo: 105
 * submenu_group: 2
 * submenu_order: 2
 */

use Gamecon\Uzivatel\Medailonek;
use Gamecon\Web\FotkaUzivatele;
use Gamecon\XTemplate\XTemplate;

if (post('noveId')) {
    dbInsertIgnore('medailonky', ['id_uzivatele' => post('noveId'), 'o_sobe' => '', 'drd' => '']);
    oznameni('vytvořeno', false);
    back(getCurrentUrlWithQuery(['id' => post('noveId')]));
}

if (get('id') !== null) {
    $idUzivatele = (int)get('id');
    $uzivatelMedailonku = $idUzivatele > 0
        ? Uzivatel::zId($idUzivatele)
        : null;
    if (!$uzivatelMedailonku) {
        oznameniPresmeruj("Uživatel s ID {$idUzivatele} neexistuje.", URL_ADMIN . '/aktivity/medailonky', Chyba::CHYBA);
    }

    // fotka patří uživateli, ne řádku v medailonkách, jde tedy nahrát i před založením medailonku
    $fotka = new FotkaUzivatele($uzivatelMedailonku);
    $fotka->zpracujPost();

    $medailonek = Medailonek::zId($idUzivatele);
    $medailonekForm = Medailonek::form($idUzivatele);
    if ($medailonek) {
        $medailonekForm->processPost();
    }

    $template = new XTemplate(__DIR__ . '/medailonky.xtpl');
    $template->assign([
        'idUzivatele'  => $idUzivatele,
        'jmeno'        => htmlspecialchars((string)$uzivatelMedailonku->jmenoNick()),
        'urlNaWebu'    => htmlspecialchars(URL_WEBU . '/' . $idUzivatele),
        'fotkaHtml'    => $fotka->getHtml(),
    ]);
    if ($medailonek) {
        $template->assign('formular', $medailonekForm->full());
        $template->parse('medailonek.formular');
    } else {
        $template->parse('medailonek.zalozit');
    }
    $template->parse('medailonek');
    $template->out('medailonek');

    return; // nezobrazovat věci níž
}

$template = new XTemplate(__DIR__ . '/medailonky.xtpl');
foreach (Medailonek::zVsech() as $medailonek) {
    assert($medailonek instanceof Medailonek);
    $uzivatelMedailonku = Uzivatel::zId($medailonek->idUzivatele());
    $template->assign('uzivatelId', $uzivatelMedailonku->id());
    $template->assign('jmenoNick', $uzivatelMedailonku->jmenoNick());
    $template->assign('maPopisOSobe', trim($medailonek->oSobe()) !== ''
        ? '✅'
        : '❌'
    );
    $template->assign('oSobe', $medailonek->oSobe());
    $template->assign('maDrdPopis', trim($medailonek->drd()) !== ''
        ? '✅'
        : '❌'
    );
    $template->assign('drdPopis', $medailonek->drd());
    $template->assign('maFotku', $uzivatelMedailonku->fotka()
        ? '✅'
        : '❌'
    );
    $template->assign('medailonek', $medailonek);
    $template->parse('medailonky.radek');
}
$template->parse('medailonky');
$template->out('medailonky');
