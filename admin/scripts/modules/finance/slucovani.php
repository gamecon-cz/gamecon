<?php

use Gamecon\XTemplate\XTemplate;

/**
 * nazev: Slučování uživatelů
 * pravo: 108
 * submenu_group: 5
 */

$t = new XTemplate(__DIR__ . '/slucovani.xtpl');

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
        'uaid'   => $uzivatel1->id(),
        'ubid'   => $uzivatel2->id(),
        'ua'     => $uzivatel1,
        'ub'     => $uzivatel2,
        'amrtvy' => $uzivatel1->mrtvyMail() ? '(mrtvý)' : '',
        'bmrtvy' => $uzivatel2->mrtvyMail() ? '(mrtvý)' : '',
    ]);

    for ($rok = ARCHIV_OD; $rok <= ROCNIK; $rok++) {
        $t->assign('rok', $rok);
        $t->parse(
            in_array($rok, $uzivatel1->historiePrihlaseni()) ?
                'slucovani.detaily.historiePrihlaseni.aPrihlasen' :
                'slucovani.detaily.historiePrihlaseni.aNeprihlasen',
        );
        $t->parse(
            in_array($rok, $uzivatel2->historiePrihlaseni()) ?
                'slucovani.detaily.historiePrihlaseni.bPrihlasen' :
                'slucovani.detaily.historiePrihlaseni.bNeprihlasen',
        );
        $t->parse('slucovani.detaily.historiePrihlaseni');
    }

    $t->parse('slucovani.detaily');
}

// načíst historii slučování uživatelů
$mergeHistory = dbFetchAll('
    SELECT
        id_smazaneho_uzivatele,
        id_noveho_uzivatele,
        EXISTS (SELECT 1 FROM uzivatele_hodnoty WHERE id_uzivatele = id_noveho_uzivatele) AS novy_existuje,
        zustatek_smazaneho_puvodne,
        zustatek_noveho_puvodne,
        email_smazaneho,
        email_noveho_puvodne,
        zustatek_noveho_aktualne,
        email_noveho_aktualne,
        kdy
    FROM uzivatele_slucovani_log
    ORDER BY kdy DESC
');

foreach ($mergeHistory as $merge) {
    $t->assign([
        'merge_id_smazaneho' => $merge['id_smazaneho_uzivatele'],
        'merge_id_noveho' => $merge['id_noveho_uzivatele'],
        'merge_zustatek_smazaneho' => $merge['zustatek_smazaneho_puvodne'],
        'merge_zustatek_noveho_puvodne' => $merge['zustatek_noveho_puvodne'],
        'merge_email_smazaneho' => htmlspecialchars($merge['email_smazaneho']),
        'merge_email_noveho_puvodne' => htmlspecialchars($merge['email_noveho_puvodne']),
        'merge_zustatek_noveho_aktualne' => $merge['zustatek_noveho_aktualne'],
        'merge_email_noveho_aktualne' => htmlspecialchars($merge['email_noveho_aktualne']),
        'merge_kdy' => $merge['kdy'],
    ]);
    if ($merge['novy_existuje']) {
        $t->assign('merge_odkaz_na_noveho', getCurrentUrlWithQuery(['pracovni_uzivatel' => $merge['id_noveho_uzivatele']]));
        $t->parse('slucovani.historie.radek.odkazNaNoveho');
    } else {
        $t->parse('slucovani.historie.radek.bezOdkazuNaNoveho');
    }
    $t->parse('slucovani.historie.radek');
}

if (!empty($mergeHistory)) {
    $t->parse('slucovani.historie');
}

$t->parse('slucovani');
$t->out('slucovani');
