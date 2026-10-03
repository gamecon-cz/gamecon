<?php

use App\Enum\ProductTagCode;
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
// from(), not tryFrom(): a form left open over a deploy still posts the old type number, and a
// fallback would cancel a different category than the one the admin chose.
$kategorie   = post('typ')
    ? ProductTagCode::from((string)post('typ'))
    : ProductTagCode::UBYTOVANI;
$mozneTypy   = [
    ProductTagCode::JIDLO->value     => 'jídlo',
    ProductTagCode::UBYTOVANI->value => 'ubytování',
    ProductTagCode::TRICKO->value    => 'tričko',
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
    Shop::zrusObjednavkyPro($uzivatele, $kategorie);
    oznameni('Objednávky pro ' . count($uzivatele) . ' uživatelů zrušeny.');
}

// vykreslení šablony
$t = new XTemplate(__DIR__ . '/ruseni-predmetu.xtpl');
$t->assign('zustatek', $zustatek);

foreach ($mozneTypy as $typId => $typ) {
    $t->assign([
        'id'       => $typId,
        'nazev'    => $typ,
        'selected' => $typId === $kategorie->value
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
