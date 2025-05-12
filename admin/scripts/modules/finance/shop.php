<?php

use Gamecon\Shop\Shop;
use Gamecon\Shop\Predmet;
use Gamecon\Shop\TypPredmetu;
use Gamecon\XTemplate\XTemplate;
use Gamecon\Shop\StavPredmetu;

/**
 * nazev: Shop
 * pravo: 108
 * submenu_group: 5
 */

/** @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */

if (($polozkyKUlozeni = post('polozky')) !== null) {
    $puvodniHodnoty = post('polozky_original');
    $zmenenoZaznamu = 0;
    foreach ($polozkyKUlozeni as $idPredmetu => $polozkaKUlozeni) {
        if ($polozkaKUlozeni === $puvodniHodnoty[$idPredmetu]) {
            continue;
        }
        $predmet = Predmet::zId($idPredmetu);
        if (!$predmet) {
            chyba("Nenzámé ID předmětu $idPredmetu");
        }
        $predmet->kusuVyrobeno((int)$polozkaKUlozeni['kusu_celkem']);
        $predmet->stav((int)$polozkaKUlozeni['stav']);
        $zmenenoZaznamu += $predmet->uloz();
    }
    oznameni("Uloženo $zmenenoZaznamu změn", false);
}

$template = new XTemplate(__DIR__ . '/shop.xtpl');

$url                = Url::zAktualni();
$predchoziNazevTypu = null;
foreach (Shop::letosniPolozky() as $polozka) {
    $nazevTypu = TypPredmetu::nazevTypu($polozka->idTypu(), true);
    if ($nazevTypu !== $predchoziNazevTypu) {
        $template->parse('shop.typ');
        $template->assign('typPolozky', mb_ucfirst($nazevTypu));
        $htmlIdTypu = slugify($nazevTypu) . '-' . $polozka->idTypu();
        $template->assign('htmlIdTypu', $htmlIdTypu);
        $template->assign('kotvaNaTypPolozky', URL_ADMIN . '/' . $url->cela() . '#' . $htmlIdTypu);
        $template->parse('shop.typ.typPolozky');
    }
    $predchoziNazevTypu = $nazevTypu;
    $template->assign('idPredmetu', $polozka->idPredmetu());
    $template->assign('nazev', $polozka->nazev());
    $template->assign('cenaZaKus', $polozka->cena());
    $template->assign('suma', $polozka->suma());
    $template->assign('modelRok', $polozka->modelRok());
    $template->assign(
        'naposledyKoupenoKdyRelativni',
        $polozka->naposledyKoupenoKdy()
            ? $polozka->naposledyKoupenoKdy()->relativni()
            : '',
    );
    $template->assign(
        'naposledyKoupenoKdyPresne',
        $polozka->naposledyKoupenoKdy()
            ? $polozka->naposledyKoupenoKdy()->formatCasStandard()
            : '',
    );
    $template->assign('letosProdanoKusu', $polozka->prodanoKusu());
    $template->assign('zbyvaKusu', $polozka->zbyvaKusu());
    $template->assign('kusuCelkem', $polozka->vyrobenoKusu());
    $template->assign('stav', $polozka->stav());
    foreach ((new ReflectionClass(StavPredmetu::class))->getConstants() as $constantName => $constantValue) {
        $template->assign('stavCislo', $constantValue);
        $template->assign('stavNazev', match ($constantValue){
            StavPredmetu::MIMO => 'Vyřazený',
            StavPredmetu::VEREJNY => 'Veřejný',
            StavPredmetu::POZASTAVENY => 'Neprodejný',
            StavPredmetu::PODPULTOVY => 'Skrytý',
            default => $constantName,
        });
        $template->assign('selected', $polozka->stav() === $constantValue
            ? 'selected'
            : '');
        $template->parse('shop.typ.polozka.stav');
    }
    $template->parse('shop.typ.polozka');
}
$template->parse('shop.typ');

$template->parse('shop');
$template->out('shop');

require __DIR__ . '/../_import-eshopu.php';

require __DIR__ . '/../penize/_kfcMrizkovyProdej.php';
