<?php

/**
 * nazev: Medailonky
 * pravo: 105
 * submenu_group: 2
 * submenu_order: 2
 */

use Gamecon\Uzivatel\Medailonek;
use Gamecon\Web\NahravaniObrazku;
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
    $nahravaniFotek = NahravaniObrazku::proFotkyUzivatelu();
    NahravaniObrazku::hlidejPrilisVelkyPozadavek();
    if (post('akceFotky') === 'nahrat') {
        NahravaniObrazku::sOhlasenimChyby(fn () => $nahravaniFotek->ulozJakoJediny(
            $nahravaniFotek->overNahranySoubor($_FILES['fotka'] ?? null),
            '',
            (string)$idUzivatele,
        ));
        oznameni('Fotka je nahraná.');
    }
    if (post('akceFotky') === 'smazat') {
        NahravaniObrazku::sOhlasenimChyby(fn () => $nahravaniFotek->smazVsechnyVarianty('', (string)$idUzivatele));
        oznameni('Fotka je smazaná, na webu se ukáže výchozí obrázek.');
    }

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
        'acceptFotky'  => $nahravaniFotek->accept(),
        'limitFotky'   => htmlspecialchars($nahravaniFotek->popisLimitu()),
        // stejný výřez jako na stránce vypravěče (web/moduly/aktivity.php)
        'nahledFotky'  => htmlspecialchars((string)$uzivatelMedailonku->fotkaAuto()?->kvalita(85)->pokryjOrez(180, 180)),
    ]);
    if ($uzivatelMedailonku->fotka()) {
        $template->parse('medailonek.fotka.smazat');
        $template->parse('medailonek.fotka.ma');
    } else {
        $template->parse('medailonek.fotka.nema');
    }
    $template->parse('medailonek.fotka');
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
