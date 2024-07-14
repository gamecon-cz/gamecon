<?php

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\OnlinePrezence\OnlinePrezenceHtml;
use Gamecon\XTemplate\XTemplate;

/**
 * Vyklikávací tabulky s prezencí na aktivity
 *
 * nazev: Prezence
 * pravo: 103
 */

/**
 * @var Uzivatel $u
 */

$t = new XTemplate(__DIR__ . '/prezence.xtpl');

$jenZamceneNeuzavrene = !empty($_GET['zamcene_neuzavrene']);

$zacatek = null; // bude nastaven přes referenci ve funkci _casy
if (!$jenZamceneNeuzavrene) {
    require __DIR__ . '/_casy.php'; // vhackování vybírátka času

    $t->assign('casy', _casy($zacatek, true));
}

$t->assign('checked', $jenZamceneNeuzavrene ? 'checked' : '');
$t->assign('urlAkce', getCurrentUrlWithQuery());
foreach ($_GET as $name => $value) {
    if ($name === 'zamcene_neuzavrene') {
        continue;
    }
    $t->assign('name', $name);
    $t->assign('value', $value);
    $t->parse('prezence.filtrAktivit.ostatniFiltry');
}
$t->parse('prezence.filtrAktivit');

$aktivity = [];
if ($jenZamceneNeuzavrene) {
    $aktivity = Aktivita::zRozmezi(
        \Gamecon\Cas\DateTimeGamecon::zacatekGameconu()->modify('-1 week'),
        new \Gamecon\Cas\DateTimeCz('2999-12-31 00:00:01'),
        Aktivita::ZAMCENE | Aktivita::NEUZAVRENE
    );
} else if ($zacatek) {
    $aktivity = Aktivita::zRozmezi($zacatek, $zacatek);
}

if (!$jenZamceneNeuzavrene) {
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
    $uzavrena = $aktivita->uzavrena();
    $t->assign('a', $aktivita);
    foreach ($aktivita->prihlaseni() as $prihlasenyUzivatel) {
        $t->assign('u', $prihlasenyUzivatel);
        $t->parse('prezence.aktivita.form.ucastnik.' . ($prihlasenyUzivatel->gcPritomen() ? 'pritomen' : 'nepritomen'));
        $t->parse('prezence.aktivita.form.ucastnik.' . ($prihlasenyUzivatel->finance()->stav() < 0 ? 'dluh' : 'prebytek'));
        if ($aktivita->dorazilJakoCokoliv($prihlasenyUzivatel)) {
            $t->parse('prezence.aktivita.form.ucastnik.dorazil');
        } elseif ($aktivita->nedorazilNeboZrusil($prihlasenyUzivatel)) {
            $t->parse('prezence.aktivita.form.ucastnik.nedorazil');
        } else {
            $t->parse('prezence.aktivita.form.ucastnik.nepotvrzeno');
        }
        $t->parse('prezence.aktivita.form.ucastnik');
    }
    if ($zamcena && (!$vyplnena || $u->maPravoNaZmenuHistorieAktivit())) {
        if ($vyplnena && $u->maPravoNaZmenuHistorieAktivit()) {
            $t->parse('prezence.aktivita.form.pozorVyplnena');
        }
    }
    if (!$uzavrena) {
        /** @var \Gamecon\Cas\DateTimeCz|null $zacatek */
        $t->assign('cas', $zacatek ? $zacatek->formatDb() : null);
        $t->assign('htmlIdAktivity', OnlinePrezenceHtml::nazevProAnchor($aktivita));
        $t->parse('prezence.aktivita.form.onlinePrezence');
        $t->parse('prezence.aktivita.pozorNeuzavrena');
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
