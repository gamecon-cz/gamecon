<?php
use Gamecon\XTemplate\XTemplate;

/**
 * nazev: Slučování uživatelů
 * pravo: 108
 * submenu_group: 5
 */

$t = new XTemplate('slucovani.xtpl');

// provede sloučení uživatelů
if (post('sloucit')) {
    if (post('skrytyUA') < post('skrytyUB')) {
        $new = Uzivatel::zId(post('skrytyUA'));
        $old = Uzivatel::zId(post('skrytyUB'));
    } else {
        $new = Uzivatel::zId(post('skrytyUB'));
        $old = Uzivatel::zId(post('skrytyUA'));
    }

    $zmeny = [];
    if (post('login') == $old->id()) $zmeny[] = 'login_uzivatele';
    if (post('heslo') == $old->id()) $zmeny[] = 'heslo_md5';
    if (post('mail') == $old->id()) $zmeny[] = 'email1_uzivatele';
    if (post('jmeno') == $old->id()) {
        $zmeny[] = 'jmeno_uzivatele';
        $zmeny[] = 'prijmeni_uzivatele';
    }
    if (post('adresa') == $old->id()) {
        $zmeny[] = 'ulice_a_cp_uzivatele';
        $zmeny[] = 'mesto_uzivatele';
        $zmeny[] = 'stat_uzivatele';
        $zmeny[] = 'psc_uzivatele';
    }
    if (post('telefon') == $old->id()) $zmeny[] = 'telefon_uzivatele';
    if (post('datum_narozeni') == $old->id()) $zmeny[] = 'datum_narozeni';
    if (post('pohlavi') == $old->id()) $zmeny[] = 'pohlavi';
    if (post('poznamka') == $old->id()) $zmeny[] = 'poznamka';
    if (post('op') == $old->id()) $zmeny[] = 'op';

    $new->sluc($old, $zmeny);
    oznameni('Uživatelé sloučeni, nové ID: ' . $new->id() . ' - staré ID: ' . $old->id());
}

// připraví / předvyplní form pro sloučení uživatelů
if (post('pripravit')) {
    // kontrola prázdného id
    $idUzivatele1 = post('ua');
    $idUzivatele2 = post('ub');
    if (!$idUzivatele1 || !$idUzivatele2) {
        chyba('Zadejte obě ID');
    }

    if ($idUzivatele1 == $idUzivatele2) {
        chyba('Slučujete stejná ID');
    }

    $uzivatel1 = Uzivatel::zId($idUzivatele1);
    $uzivatel2 = Uzivatel::zId($idUzivatele2);

    if (!$uzivatel1 || !$uzivatel2) {
        if ($uzivatel2) {
            chyba("První ID $idUzivatele1 neexistuje");
        }
        if ($uzivatel1) {
            chyba("Druhé ID $idUzivatele2 neexistuje");
        }
        chyba("Ani první ID $idUzivatele1, ani druhé ID $idUzivatele2 neexistuje");
    }

    $t->assign([
        'uaid' => $uzivatel1->id(),
        'ubid' => $uzivatel2->id(),
        'ua' => $uzivatel1,
        'ub' => $uzivatel2,
        'amrtvy' => $uzivatel1->mrtvyMail() ? '(mrtvý)' : '',
        'bmrtvy' => $uzivatel2->mrtvyMail() ? '(mrtvý)' : '',
    ]);

    for ($rok = 2009; $rok <= ROK; $rok++) {
        $t->assign('rok', $rok);
        $t->parse(
            in_array($rok, $uzivatel1->historiePrihlaseni()) ?
                'slucovani.detaily.historiePrihlaseni.aPrihlasen' :
                'slucovani.detaily.historiePrihlaseni.aNeprihlasen'
        );
        $t->parse(
            in_array($rok, $uzivatel2->historiePrihlaseni()) ?
                'slucovani.detaily.historiePrihlaseni.bPrihlasen' :
                'slucovani.detaily.historiePrihlaseni.bNeprihlasen'
        );
        $t->parse('slucovani.detaily.historiePrihlaseni');
    }

    $t->parse('slucovani.detaily');
}

$t->parse('slucovani');
$t->out('slucovani');
