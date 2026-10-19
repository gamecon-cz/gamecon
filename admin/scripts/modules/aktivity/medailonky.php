<?php

/**
 * nazev: Medailonky
 * pravo: 105
 * submenu_group: 2
 * submenu_order: 2
 */

use Gamecon\Uzivatel\Medailonek;
use Gamecon\Web\NahravaniObrazku;
use Gamecon\Web\NahravaniObrazkuException;
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
    if (NahravaniObrazku::bylPozadavekPrilisVelky()) {
        chyba(NahravaniObrazku::zpravaOPrilisVelkemPozadavku());
    }
    if (post('akceFotky') === 'nahrat') {
        try {
            $nahravaniFotek->ulozJakoJediny(
                $nahravaniFotek->overNahranySoubor($_FILES['fotka'] ?? null),
                '',
                (string)$idUzivatele,
            );
        } catch (NahravaniObrazkuException $nahravaniObrazkuException) {
            chyba(htmlspecialchars($nahravaniObrazkuException->getMessage()));
        }
        oznameni('Fotka je nahraná.');
    }
    if (post('akceFotky') === 'smazat') {
        try {
            $nahravaniFotek->smazVsechnyVarianty('', (string)$idUzivatele);
        } catch (NahravaniObrazkuException $nahravaniObrazkuException) {
            chyba(htmlspecialchars($nahravaniObrazkuException->getMessage()));
        }
        oznameni('Fotka je smazaná, na webu se ukáže výchozí obrázek.');
    }

    $medailonek = Medailonek::zId($idUzivatele);
    $f = Medailonek::form($idUzivatele);
    if ($medailonek) {
        $f->processPost();
    }

    $t = new XTemplate(__DIR__ . '/medailonky.xtpl');
    $t->assign([
        'idUzivatele'  => $idUzivatele,
        'jmeno'        => htmlspecialchars((string)$uzivatelMedailonku->jmenoNick()),
        'urlNaWebu'    => htmlspecialchars(URL_WEBU . '/' . $idUzivatele),
        'acceptFotky'  => $nahravaniFotek->accept(),
        'limitFotky'   => htmlspecialchars($nahravaniFotek->popisLimitu()),
        // stejný výřez jako na stránce vypravěče (web/moduly/aktivity.php)
        'nahledFotky'  => htmlspecialchars((string)$uzivatelMedailonku->fotkaAuto()?->kvalita(85)->pokryjOrez(180, 180)),
    ]);
    if ($uzivatelMedailonku->fotka()) {
        $t->parse('medailonek.fotka.smazat');
        $t->parse('medailonek.fotka.ma');
    } else {
        $t->parse('medailonek.fotka.nema');
    }
    $t->parse('medailonek.fotka');
    if ($medailonek) {
        $t->assign('formular', $f->full());
        $t->parse('medailonek.formular');
    } else {
        $t->parse('medailonek.zalozit');
    }
    $t->parse('medailonek');
    $t->out('medailonek');

    return; // nezobrazovat věci níž
}

$t = new XTemplate(__DIR__ . '/medailonky.xtpl');
foreach (Medailonek::zVsech() as $medailonek) {
    assert($medailonek instanceof Medailonek);
    $uzivatelMedailonku = Uzivatel::zId($medailonek->idUzivatele());
    $t->assign('uzivatelId', $uzivatelMedailonku->id());
    $t->assign('jmenoNick', $uzivatelMedailonku->jmenoNick());
    $t->assign('maPopisOSobe', trim($medailonek->oSobe()) !== ''
        ? '✅'
        : '❌'
    );
    $t->assign('oSobe', $medailonek->oSobe());
    $t->assign('maDrdPopis', trim($medailonek->drd()) !== ''
        ? '✅'
        : '❌'
    );
    $t->assign('drdPopis', $medailonek->drd());
    $t->assign('maFotku', $uzivatelMedailonku->fotka()
        ? '✅'
        : '❌'
    );
    $t->assign('medailonek', $medailonek);
    $t->parse('medailonky.radek');
}
$t->parse('medailonky');
$t->out('medailonky');
