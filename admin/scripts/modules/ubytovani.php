<?php

use Gamecon\Shop\Shop;

/**
 * Stránka k editaci ubytovacích informací
 *
 * nazev: Ubytování
 * pravo: 101
 */

/**
 * @var Uzivatel|null|void $uPracovni
 * @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni
 */

$nastaveni = ['ubytovaniBezZamku' => true, 'jidloBezZamku' => true];
$shop = $uPracovni ? new Shop($uPracovni, $nastaveni, $systemoveNastaveni) : null;

if (post('pridelitPokoj')) {
    Pokoj::ubytujNaCislo(Uzivatel::zId(post('uid')), post('pokoj'));
    oznameni('Pokoj přidělen');
}

if (post('zpracujUbytovani')) {
    $shop->zpracujUbytovani();
    oznameni('Ubytování uloženo');
}

if (post('zpracujJidlo')) {
    $shop->zpracujJidlo();
    oznameni('Jídlo uloženo');
}

$t = new XTemplate(__DIR__ . '/ubytovani.xtpl');

$pokoj = Pokoj::zCisla(get('pokoj'));
$ubytovani = $pokoj ? $pokoj->ubytovani() : [];
if (get('pokoj') && !$pokoj) throw new Chyba('pokoj ' . get('pokoj') . ' neexistuje nebo je prázdný');
$t->assign([
    'uid' => $uPracovni ? $uPracovni->id() : '',
    'pokoj' => get('pokoj'),
    'ubytovani' => array_uprint($ubytovani, function ($e) {
        $ne = $e->gcPritomen() ? '' : 'ne';
        $color = $ne ? '#f00' : '#0a0';
        $a = $e->koncA();
        return $e->jmenoNick() . " (<span style=\"color:$color\">{$ne}dorazil$a</span>)";
    }, '<br>'),
]);

if ($uPracovni && $uPracovni->gcPrihlasen()) {
    $t->assign('ubytovaniHtml', $shop->ubytovaniHtml(true));
    $t->assign('jidloHtml', $shop->jidloHtml(true));
    $t->parse('ubytovani.ubytovani');
    $t->parse('ubytovani.jidlo');
}

if (!$uPracovni) {
    $t->assign('status', '<div class="warning">Uživatel nevybrán</div>');
} elseif (!$uPracovni->gcPrihlasen()) {
    $t->assign('status', '<div class="error">Uživatel není přihlášen na GC</div>');
}

$t->parse('ubytovani');
$t->out('ubytovani');

require __DIR__ . '/_ubytovani-a-dalsi-obcasne-infopultakoviny-import-ubytovani.php';
require __DIR__ . '/_ubytovani-a-dalsi-obcasne-infopultakoviny-import-balicku.php';
