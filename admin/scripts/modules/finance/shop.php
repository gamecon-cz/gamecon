<?php

use Gamecon\Shop\Shop;
use Gamecon\Shop\Predmet;
use Gamecon\Shop\TypPredmetu;

/**
 * nazev: Shop
 * pravo: 108
 * submenu_group: 5
 */

if (($polozkyKUlozeni = post('polozky')) !== null) {
    $puvodniHodnoty = post('polozky_original');
    foreach ($polozkyKUlozeni as $idPredmetu => $polozkaKUlozeni) {
        if ($polozkaKUlozeni === $puvodniHodnoty[$idPredmetu]) {
            continue;
        }
        $predmet = Predmet::zId($idPredmetu);
        if (!$predmet) {
            chyba("Nenzámé ID předmětu $idPredmetu");
        }
        $predmet->kusuVyrobeno((int)$polozkaKUlozeni['kusu_celkem']);
        $predmet->uloz();
    }
}

$template = new XTemplate(__DIR__ . '/shop.xtpl');

$url = Url::zAktualni();
$predchoziNazevTypu = null;
foreach (Shop::letosniPolozky() as $polozka) {
    $nazevTypu = TypPredmetu::nazevTypu($polozka->idTypu(), true);
    if ($nazevTypu !== $predchoziNazevTypu) {
        $template->parse('eshop.typ');
        $template->assign('typPolozky', mb_ucfirst($nazevTypu));
        $htmlIdTypu = slugify($nazevTypu) . '-' . $polozka->idTypu();
        $template->assign('htmlIdTypu', $htmlIdTypu);
        $template->assign('kotvaNaTypPolozky', URL_ADMIN . '/' . $url->cela() . '#' . $htmlIdTypu);
        $template->parse('eshop.typ.typPolozky');
    }
    $predchoziNazevTypu = $nazevTypu;
    $template->assign('idPredmetu', $polozka->idPredmetu());
    $template->assign('nazev', $polozka->nazev());
    $template->assign('cenaZaKus', $polozka->cena());
    $template->assign('suma', $polozka->suma());
    $template->assign('modelRok', $polozka->modelRok());
    $template->assign('naposledyKoupenoKdyRelativni', $polozka->naposledyKoupenoKdy()->relativni());
    $template->assign('naposledyKoupenoKdyPresne', $polozka->naposledyKoupenoKdy()->formatCasStandard());
    $template->assign('letosProdanoKusu', $polozka->prodanoKusu());
    $template->assign('zbyvaKusu', $polozka->zbyvaKusu());
    $template->assign('kusuCelkem', $polozka->vyrobenoKusu());
    $template->parse('eshop.typ.polozka');
}

$template->parse('eshop.typ');
$template->parse('eshop');
$template->out('eshop');
