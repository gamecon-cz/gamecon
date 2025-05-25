<?php

use Gamecon\XTemplate\XTemplate;

use Gamecon\Shop\Shop;

/**
 * Nástroj na automatické rušení předmětů daného typu uživatelům s zůstatkem
 * menším jak X.
 *
 * nazev: Hromadné rušení objednávek
 * pravo: 108
 * submenu_group: 5
 */

// nastavení výchozích hodnot
$zustatek    = (int)post('zustatek')
    ?: -20;
$typPredmetu = (int)post('typ')
    ?: Shop::UBYTOVANI;
$mozneTypy   = [
    Shop::JIDLO     => 'jídlo',
    Shop::UBYTOVANI => 'ubytování',
    Shop::TRICKO    => 'tričko',
];
$uzivatele   = [];
if (post('vypsat') || post('rusit')) {
    foreach (Uzivatel::zPrihlasenych() as $un) {
        if ($un->finance()->stav() < $zustatek) {
            if ($un->maPravoNerusitObjednavky()) {
                continue;
            }
            $uzivatele[] = $un;
        }
    }
}

// zpracování POST požadavků
if (post('rusit') && $uzivatele) {
    Shop::zrusObjednavkyPro($uzivatele, $typPredmetu);
    oznameni('Objednávky pro ' . count($uzivatele) . ' uživatelů zrušeny.');
}

// vykreslení šablony
$t = new XTemplate(__DIR__ . '/ruseni-predmetu.xtpl');
$t->assign('zustatek', $zustatek);

foreach ($mozneTypy as $typId => $typ) {
    $t->assign([
        'id'       => $typId,
        'nazev'    => $typ,
        'selected' => $typId == $typPredmetu
            ? 'selected'
            : '',
    ]);
    $t->parse('ruseniPredmetu.typ');
}

if (post('vypsat') || post('rusit')) {
    if ($uzivatele) {
        foreach ($uzivatele as $uzivatel) {
            $t->assign('jmenoNick', $uzivatel->jmenoNick());
            $t->assign('stavFinanci', $uzivatel->finance()->formatovanyStav());
            $t->parse('ruseniPredmetu.vypis.uzivatel');
        }
    } else {
        $t->parse('ruseniPredmetu.vypis.zadniUzivatele');
    }
    $t->parse('ruseniPredmetu.vypis');
}

if (!post('vypsat')) {
    $t->parse('ruseniPredmetu.ruseniBlocker');
}

$t->parse('ruseniPredmetu');
$t->out('ruseniPredmetu');
