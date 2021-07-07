<?php

use \Gamecon\Cas\DateTimeCz;

/** @var XTemplate $t */
/** @var Uzivatel $u */

$this->blackarrowStyl(true);
$this->bezPaticky(true);
$this->info()->nazev('Přihláška');

/**
 * Pomocná funkce pro náhled předmětu pro aktuální ročník
 */
function nahledPredmetu($soubor) {
    $cesta = 'soubory/obsah/materialy/' . ROK . '/' . $soubor;
    try {
        $nahled = Nahled::zSouboru($cesta)->kvalita(98)->url();
    } catch (Exception $e) {
        // pokud soubor neexistuje, nepoužít cache ale vypsat do html přímo cestu
        // při zkoumání html je pak přímo vidět, kam je potřeba nahrát soubor
        $nahled = $cesta;
    }
    return $nahled;
}

if (GC_BEZI || ($u && $u->gcPritomen())) {
    // zpřístupnit varianty mimo registraci i pro nepřihlášeného uživatele kvůli
    // příchodům z titulky, menu a podobně
    $t->parse('prihlaskaGcBezi');
    return;
}

if (!$u) {
    // Mimo období kdy GC běží: Situaci uživateli vždy dostatečně vysvětlí
    // registrační stránka. A umožní mu aspoň vytvořit si účet.
    back(URL_WEBU . '/registrace');
}

if (pred(REG_GC_OD)) {
    $t->assign('zacatek', (new DateTimeCz(REG_GC_OD))->format('j. n. \v\e H:i'));
    $t->parse('prihlaskaPred');
    return;
}

if (po(REG_GC_DO)) {
    $t->assign('rok', ROK + 1);
    $t->parse('prihlaskaPo');
    return;
}

$shop = new Shop($u);
$pomoc = new Pomoc($u);

if (post('odhlasit')) {
    $u->gcOdhlas();
    oznameni(hlaska('odhlaseniZGc', $u));
}

if (post('prihlasitNeboUpravit')) {
    $prihlasovani = false;
    if (!$u->gcPrihlasen()) {
        $prihlasovani = true;
        $u->gcPrihlas();
    }
    $shop->zpracujPredmety();
    $shop->zpracujUbytovani();
    $shop->zpracujJidlo();
    $shop->zpracujVstupne();
    $u->zpracujPotvrzeniProtiCovidu();
    $pomoc->zpracuj();
    if ($prihlasovani) {
        oznameni(hlaska('prihlaseniNaGc', $u));
    } else {
        oznameni(hlaska('aktualizacePrihlasky'));
    }
}

// informace o slevách (jídlo nevypisovat, protože tabulka správně vypisuje cenu po slevě)
$slevy = $u->finance()->slevyVse();
$slevy = array_diff($slevy, ['jídlo zdarma', 'jídlo se slevou']);
if ($slevy) {
    $t->assign([
        'slevy' => implode(', ', $slevy),
        'titul' => mb_strtolower($u->status()),
    ]);
    $t->parse('prihlaska.slevy');
}

$t->assign('ka', $u->koncA() ? 'ka' : '');
if ($u->maPravo(P_UBYTOVANI_ZDARMA)) {
    $t->parse('prihlaska.ubytovaniInfoOrg');
} else if ($u->maPravo(P_ORG_AKCI) && !$u->maPravo(P_NEMA_SLEVU_AKTIVITY)) {
    $t->parse('prihlaska.ubytovaniInfoVypravec');
}

// náhledy
$nahledy = [
    ['Triko.png', 'Triiko_detail.png', 'Tričko'],
    ['Kostka.png', 'Kostka_detail.png', 'Kostka'],
    ['Fate.png', 'Fate_detail.png', 'Fate kostka'],
    ['Placka.png', 'Placka_detail.png', 'Placka'],
    ['nicknack.jpg', 'nicknack_m.jpg', 'Nicknack'],
    ['Ponozky.png', 'Ponozky_detail.png', 'Ponožky'],
];
foreach ($nahledy as $nahled) {
    $t->assign([
        'obrazek' => nahledPredmetu($nahled[0]),
        'miniatura' => nahledPredmetu($nahled[1]),
        'nazev' => $nahled[2],
    ]);
    $t->parse('prihlaska.nahled');
}

$t->assign([
    'a' => $u->koncA(),
    'jidlo' => $shop->jidloHtml(),
    'predmety' => $shop->predmetyHtml(),
    'rok' => ROK,
    'ubytovani' => $shop->ubytovaniHtml(),
    'covidFreePotvrzeni' => $shop->covidFreePotrvzeniHtml((int)date('Y')),
    'ulozitNeboPrihlasit' => $u->gcPrihlasen()
        ? 'Uložit změny'
        : 'Přihlásit na GameCon',
    'vstupne' => $shop->vstupneHtml(),
    'pomoc' => $pomoc->html(),
]);

$t->parse($u->gcPrihlasen()
    ? 'prihlaska.prihlasen'
    : 'prihlaska.neprihlasen');
if ($u->gcPrihlasen()) {
    $t->parse('prihlaska.odhlasit');
}
