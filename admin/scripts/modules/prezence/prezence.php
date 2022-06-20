<?php

use Gamecon\Aktivita\Aktivita;

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
    $aktivita->dejPrezenci()->uloz($dorazili);
    back();
}

$t = new XTemplate(__DIR__ . '/prezence.xtpl');

$jenUzamceneNeuzavrene = !empty($_GET['uzamcene_neuzavrene']);

$zacatek = null; // bude nastaven přes referenci ve funkci _casy
if (!$jenUzamceneNeuzavrene) {
    require __DIR__ . '/_casy.php'; // vhackování vybírátka času

    $t->assign('casy', _casy($zacatek, true));
}

$t->assign('checked', $jenUzamceneNeuzavrene ? 'checked' : '');
$t->assign('urlAkce', getCurrentUrlWithQuery());
foreach ($_GET as $name => $value) {
    if ($name === 'uzamcene_neuzavrene') {
        continue;
    }
    $t->assign('name', $name);
    $t->assign('value', $value);
    $t->parse('prezence.filtrAktivit.ostatniFiltry');
}
$t->parse('prezence.filtrAktivit');

$aktivity = [];
if ($jenUzamceneNeuzavrene) {
    $aktivity = Aktivita::zRozmezi(
        new \Gamecon\Cas\DateTimeCz('0001-01-01 00:00:01'),
        new \Gamecon\Cas\DateTimeCz('2999-12-31 00:00:01'),
        Aktivita::ZAMCENE | Aktivita::NEUZAVRENE
    );
} else if ($zacatek) {
    $aktivity = Aktivita::zRozmezi($zacatek, $zacatek);
}

if (!$jenUzamceneNeuzavrene) {
    if ($zacatek && count($aktivity) === 0) {
        $t->parse('prezence.zadnaAktivita');
    }
    if (!$zacatek) {
        $t->parse('prezence.nevybrano');
    }
}

foreach ($aktivity as $aktivita) {
    $vyplnena = $aktivita->nekdoUzDorazil();
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
    if ($zamcena && (!$vyplnena || $u->maPravoNaZmenuHistorieAktivit())) {
        if ($vyplnena && $u->maPravoNaZmenuHistorieAktivit()) {
            $t->parse('prezence.aktivita.form.submit.pozorVyplnena');
        }
        $t->parse('prezence.aktivita.form.submit');
    }
    if (!$zamcena) {
        /** @var \Gamecon\Cas\DateTimeCz|null $zacatek */
        $t->assign('cas', $zacatek ? $zacatek->formatDb() : null);
        $t->parse('prezence.aktivita.form.onlinePrezence');
        $t->parse('prezence.aktivita.pozorNezamknuta');
    }
    $t->assign(
        'nadpis',
        implode(' – ', array_filter([$aktivita->nazev(), $aktivita->orgJmena(), $aktivita->lokace()]))
        . ($aktivita->zamcena() ? ' <span class="hinted">🔒<span class="hint">Zamčená pro přihlašování</span></span> ' : '')
        . ($aktivita->uzavrena() ? ' <span class="hinted">📕<span class="hint">S uzavřenou prezencí</span></span> ' : '')
    );
    $t->parse('prezence.aktivita.form');
    $t->parse('prezence.aktivita');
}

$t->parse('prezence');
$t->out('prezence');
