<?php

/**
 * Online formulář na zadávání příchozích a nedorazivších na aktivity
 *
 * nazev: Moje aktivita
 * pravo: 109
 */

/**
 * @var Uzivatel $u
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

$t = new XTemplate('_moje-aktivita.xtpl');

$zacatekAktivity = $aktivita->zacatek();
//$aktivita->zamci();
$t->assign([
    'a' => $aktivita,
    'nazevAktivity' => $aktivita->nazev(),
    'obsazenost' => $aktivita->obsazenostHtml(),
    'cas' => $aktivita->denCas(),
]);

// TODO revert $ucastnici = $aktivita->prihlaseni();
$ucastnici = Uzivatel::zHledani('kru'); // TODO REMOVE
$o = dbQuery(
    'SELECT id_uzivatele, MAX(cas) as cas FROM akce_prihlaseni_log WHERE id_akce = $1 GROUP BY id_uzivatele',
    [$aktivita->id()]
);
while ($r = mysqli_fetch_assoc($o)) {
    $casyPrihlaseni[$r['id_uzivatele']] = new \Gamecon\Cas\DateTimeCz($r['cas']);
}
foreach ($ucastnici as $ucastnik) {
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
}

if ($aktivita->nahradnici()) {

    $t->parse('mojeAktivita.hlavickaNahradnik');

    foreach ($aktivita->nahradnici() as $nahradnik) {
        $vekCislem = $nahradnik->vekKDatu($zacatekAktivity);
        $vek = $dejVekVeZkratce($vekCislem);
        $t->assign('vek', $vek);
        $t->assign('u', $nahradnik);
        $t->parse('mojeAktivita.ucast.ucastnik');
    }
}

$t->parse('mojeAktivita.ucast');
$t->parse('mojeAktivita');
$t->out('mojeAktivita');
