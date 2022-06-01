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
 */

$nastaveni = ['ubytovaniBezZamku' => true, 'jidloBezZamku' => true];
$shop = $uPracovni ? new Shop($uPracovni, $nastaveni) : null;

if (post('pokojeImport')) {
    $f = fopen($_FILES['pokojeSoubor']['tmp_name'], 'rb');
    if (!$f) throw new Exception('Soubor se nepodařilo načíst');

    $hlavicka = array_flip(fgetcsv($f, 512, ";"));
    if (!array_key_exists('id_uzivatele', $hlavicka)) throw new Exception('Nepodařilo se zpracovat soubor');
    $uid = $hlavicka['id_uzivatele'];
    $od = $hlavicka['prvni_noc'];
    $do = $hlavicka['posledni_noc'];
    $pokoj = $hlavicka['pokoj'];

    dbDelete('ubytovani', ['rok' => ROK]);

    while ($r = fgetcsv($f, 512, ";")) {
        if ($r[$pokoj]) {
            for ($den = $r[$od]; $den <= $r[$do]; $den++) {
                dbInsert('ubytovani', [
                    'id_uzivatele' => $r[$uid],
                    'den' => $den,
                    'pokoj' => $r[$pokoj],
                    'rok' => ROK,
                ]);
            }
        }
    }

    oznameni('Import dokončen');
}

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

$t = new XTemplate('ubytovani.xtpl');

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
    $t->assign('shop', $shop);
    $t->parse('ubytovani.ubytovani');
    $t->parse('ubytovani.jidlo');
}

if (!$uPracovni) {
    $t->assign('status', '<div class="warning">Uživatel nevybrán</div>');
} elseif (!$uPracovni->gcPrihlasen()) {
    $t->assign('status', '<div class="error">Uživatel není přihlášen na GC</div>');
}

$t->assign('ubytovaniReport', basename(__DIR__ . '/../zvlastni/reporty/ubytovani.php', '.php'));

$t->parse('ubytovani');
$t->out('ubytovani');

require __DIR__ . '/_ubytovani-a-dalsi-obcasne-infopultakoviny-import-balicku.php';
