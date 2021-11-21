<?php

/**
 * Vyklikávací tabulky s prezencí na aktivity
 *
 * nazev: Prezence
 * pravo: 103
 */

/**
 * @var Uzivatel $u
 */

if (post('prezenceAktivity')) {
    $aktivita = Aktivita::zId(post('prezenceAktivity'));
    $dorazili = Uzivatel::zIds(array_keys(post('dorazil') ?: []));
    $aktivita->ulozPrezenci($dorazili);
    back();
}

$t = new XTemplate('prezence.xtpl');

require __DIR__ . '/_casy.php'; // vhackování vybírátka času

$zacatek = null; // bude nastaven přes referenci v nasledujici funkci
$t->assign('casy', _casy($zacatek, true));

$aktivity = $zacatek
    ? Aktivita::zRozmezi($zacatek, $zacatek)
    : [];

if ($zacatek && count($aktivity) === 0) {
    $t->parse('prezence.zadnaAktivita');
}
if (!$zacatek) {
    $t->parse('prezence.nevybrano');
}

foreach ($aktivity as $aktivita) {
    $vyplnena = $aktivita->vyplnenaPrezence();
    $zamcena = $aktivita->zamcena();
    $t->assign('a', $aktivita);
    foreach ($aktivita->prihlaseni() as $prihlasenyUzivatel) {
        $t->assign('u', $prihlasenyUzivatel);
        if (!$vyplnena && $zamcena) {
            $t->parse('prezence.aktivita.form.ucastnik.checkbox');
        } else {
            $t->parse('prezence.aktivita.form.ucastnik.skryty');
        }
        $t->parse('prezence.aktivita.form.ucastnik.' . ($prihlasenyUzivatel->gcPritomen() ? 'pritomen' : 'nepritomen'));
        $t->parse('prezence.aktivita.form.ucastnik.' . ($prihlasenyUzivatel->finance()->stav() < 0 ? 'dluh' : 'prebytek'));
        $t->parse('prezence.aktivita.form.ucastnik');
    }
    if ($vyplnena) {
        $t->parse('prezence.aktivita.vyplnena');
    }
    if ($zamcena && (!$vyplnena || $u->maPravo(\Gamecon\Pravo::ZMENA_HISTORIE_AKTIVIT))) {
        if ($vyplnena && $u->maPravo(\Gamecon\Pravo::ZMENA_HISTORIE_AKTIVIT)) {
            $t->parse('prezence.aktivita.form.submit.pozorVyplnena');
        }
        $t->parse('prezence.aktivita.form.submit');
    }
    if (!$zamcena) {
        $t->parse('prezence.aktivita.onlinePrezence');
        $t->parse('prezence.aktivita.pozorNezamknuta');
    }
    $t->assign('nadpis', implode(' – ', array_filter([$aktivita->nazev(), $aktivita->orgJmena(), $aktivita->lokace()])));
    $t->parse('prezence.aktivita.form');
    $t->parse('prezence.aktivita');
}

$t->parse('prezence');
$t->out('prezence');
