<?php

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\FiltrAktivity;
use Gamecon\Aktivita\OnlinePrezence\OnlinePrezenceHtml;
use Gamecon\Aktivita\StavAktivity;
use Gamecon\Cas\DateTimeGamecon;
use Gamecon\XTemplate\XTemplate;

/**
 * Vyklikávací tabulky s prezencí na aktivity
 *
 * nazev: Prezence
 * pravo: 103
 */

/**
 * @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni
 * @var Uzivatel $u
 */

$t = new XTemplate(__DIR__ . '/prezence.xtpl');

$filtrAktivit = $_GET['filtr_aktivit'] ?? '';
$jenZamceneNeuzavrene = $filtrAktivit === 'zamcene_neuzavrene';
$jenUzavreneNevyplnene = $filtrAktivit === 'uzavrene_nevyplnene';
$jenBezPrihlasenych = $filtrAktivit === 'bez_prihlasenych';
$ignorovatCas = !empty($_GET['ignorovat_cas']);

$zacatek = null; // bude nastaven přes referenci ve funkci _casy

require_once __DIR__ . '/../aktivity/_filtr-moznosti.php';

$filtrMoznosti = FiltrMoznosti::vytvorZGlobals(FiltrMoznosti::NEFILTROVAT_PODLE_ROKU);
if (!$ignorovatCas) {
    require __DIR__ . '/_casy.php'; // vhackování vybírátka času

    $t->assign('casy', _casy($zacatek, true) . $filtrMoznosti->dejProTemplate());
} else {
    $t->assign('casy', $filtrMoznosti->dejProTemplate());
}

$t->assign('checkedZadny', $filtrAktivit === '' ? 'checked' : '');
$t->assign('checkedNeuzavrene', $jenZamceneNeuzavrene ? 'checked' : '');
$t->assign('checkedNevyplnene', $jenUzavreneNevyplnene ? 'checked' : '');
$t->assign('checkedBezPrihlasenych', $jenBezPrihlasenych ? 'checked' : '');
$t->assign('checkedCas', $ignorovatCas
    ? 'checked'
    : '');
$t->assign('urlAkce', getCurrentUrlWithQuery());
$t->parse('prezence.filtrAktivit');

$aktivity = [];
$filtr = $filtrMoznosti->dejFiltr()[0];
if ($ignorovatCas) {
    $filtr['rok'] = $systemoveNastaveni->rocnik();
} elseif ($zacatek) {
    $filtr['od'] = $zacatek->format(DateTimeGamecon::FORMAT_DB);
    $filtr['do'] = $zacatek->format(DateTimeGamecon::FORMAT_DB);
}
if ($jenZamceneNeuzavrene) {
    $filtr['jenZamcene'] = true;
    $filtr['jenNeuzavrene'] = true;
}
if ($jenUzavreneNevyplnene) {
    $filtr[FiltrAktivity::STAV] = StavAktivity::UZAVRENA;
    $filtr[FiltrAktivity::JEN_NEVYPLNENE] = true;
}
if ($jenBezPrihlasenych) {
    $filtr[FiltrAktivity::JEN_BEZ_PRIHLASENYCH] = true;
    $filtr['rok'] = $systemoveNastaveni->rocnik();
}
$aktivity = Aktivita::zFiltru(
    systemoveNastaveni: $systemoveNastaveni,
    filtr: $filtr,
);

if (count($aktivity) === 0) {
    $t->parse('prezence.zadnaAktivita');
}

foreach ($aktivity as $aktivita) {
    $vyplnena = $aktivita->nekdoUzDorazil();
    $zamcena = $aktivita->zamcena();
    $uzavrena = $aktivita->uzavrena();
    $t->assign('a', $aktivita);
    foreach ($aktivita->prihlaseni() as $prihlasenyUzivatel) {
        $t->assign('u', $prihlasenyUzivatel);
        $t->assign('idUzivatele', $prihlasenyUzivatel->id());
        $t->assign('jmenoNick', $prihlasenyUzivatel->jmenoNick());
        $t->assign('telefon', $prihlasenyUzivatel->telefon());
        $t->assign('stavFinanci', $prihlasenyUzivatel->finance()->formatovanyStav());
        $t->parse('prezence.aktivita.form.ucastnik.' . ($prihlasenyUzivatel->gcPritomen()
                ? 'pritomen'
                : 'nepritomen'));
        $t->parse('prezence.aktivita.form.ucastnik.' . ($prihlasenyUzivatel->finance()->stav() < 0
                ? 'dluh'
                : 'prebytek'));
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
        $t->assign('cas', $aktivita->zacatek()?->formatDb());
        $t->assign('htmlIdAktivity', OnlinePrezenceHtml::nazevProAnchor($aktivita));
        $t->parse('prezence.aktivita.form.onlinePrezence');
        $t->parse('prezence.aktivita.pozorNeuzavrena');
    }
    $t->assign(
        'nadpis',
        implode(' – ', array_filter([$aktivita->nazev(), $aktivita->orgJmena(), $aktivita->popisLokaci(), $aktivita->zacatek()?->format('l H:i')]))
        . ($aktivita->zamcena()
            ? ' <span class="hinted">🔒<span class="hint">Zamčená pro přihlašování</span></span> '
            : '')
        . ($aktivita->uzavrena()
            ? ' <span class="hinted">📕<span class="hint">S uzavřenou prezencí</span></span> '
            : ''),
    );
    $t->parse('prezence.aktivita.form');
    $t->parse('prezence.aktivita');
}

$t->parse('prezence');
$t->out('prezence');
