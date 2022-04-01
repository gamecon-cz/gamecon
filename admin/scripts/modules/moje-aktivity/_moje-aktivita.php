<?php

/**
 * Online formulář na zadávání příchozích a nedorazivších na aktivity
 *
 * nazev: Moje aktivita
 * pravo: 109
 */

/**
 * @var Uzivatel $u
 * @var bool $testovani
 */

$aktivita = Aktivita::zId(get('id'));
$problem = require __DIR__ . '/_moje-aktivita-problem.php';

if ($problem) {
    return;
}

$dejVekVeZkratce = static function (?int $vek): string {
    if ($vek === null) {
        return '?';
    }
    if ($vek >= 18) {
        return '18+';
    }
    return (string)$vek;
};

$t = new XTemplate(__DIR__ . '/_moje-aktivita.xtpl');

$zacatekAktivity = $aktivita->zacatek();
//$aktivita->zamci();
$t->assign([
    'a' => $aktivita,
    'nazevAktivity' => $aktivita->nazev(),
    'obsazenost' => $aktivita->obsazenostHtml(),
    'cas' => $aktivita->denCas(),
]);

$ucastnici = $testovani
    ? Uzivatel::zHledani('kru')
    : $aktivita->prihlaseni();

$o = dbQuery(
    'SELECT id_uzivatele, MAX(cas) as cas FROM akce_prihlaseni_log WHERE id_akce = $1 GROUP BY id_uzivatele',
    [$aktivita->id()]
);
while ($r = mysqli_fetch_assoc($o)) {
    $casyPrihlaseni[$r['id_uzivatele']] = new \Gamecon\Cas\DateTimeCz($r['cas']);
}
$vypisUcastnika = static function (\Uzivatel $ucastnik) use ($zacatekAktivity, $dejVekVeZkratce, $casyPrihlaseni, $t) {
    $vekCislem = $ucastnik->vekKDatu($zacatekAktivity);
    $vek = $dejVekVeZkratce($vekCislem);
    $t->assign('u', $ucastnik);
    $t->assign([
        'jmeno' => $ucastnik->jmenoNick(),
        'mail' => $ucastnik->mail(),
        'vek' => $vek,
        'telefon' => $ucastnik->telefon(),
        'casPrihlaseni' => isset($casyPrihlaseni[$ucastnik->id()]) ? $casyPrihlaseni[$ucastnik->id()]->format('j.n. H:i') : '<i>???</i>',
    ]);
    $t->parse('mojeAktivita.ucast.ucastnik');
};

foreach ($ucastnici as $ucastnik) {
    $vypisUcastnika($ucastnik);
}
$t->parse('mojeAktivita.ucast');

if ($aktivita->nahradnici()) {

    $t->parse('mojeAktivita.ucast.hlavickaNahradnik');

    foreach ($aktivita->nahradnici() as $nahradnik) {
        $vypisUcastnika($nahradnik);
    }
    $t->parse('mojeAktivita.ucast');
}

$t->assign('urlZpet', getBackUrl());

$t->parse('mojeAktivita');
$t->out('mojeAktivita');
