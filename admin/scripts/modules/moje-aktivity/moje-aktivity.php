<?php

/**
 * Úvodní karta organizátora s přehledem jeho aktivit
 *
 * nazev: Moje aktivity
 * pravo: 109
 */

use \Gamecon\Cas\DateTimeCz;

/**
 * @var Uzivatel $u
 */

if (get('id')) {
    require __DIR__ . '/_moje-aktivita.php';
    return;
}

$aktivity = Aktivita::zFiltru([
// TODO revert    'organizator' => $u->id(),
    'rok' => ROK,
]);

$t = new XTemplate(basename(__DIR__ . '/moje-aktivity.xtpl'));

if (empty($aktivity)) {
    $t->parse('prehled.zadnaAktivita');
}
foreach ($aktivity as $a) {
// TODO revert    $ucastnici = $a->prihlaseni();
    $ucastnici = Uzivatel::zHledani('kru');
    $o = dbQuery(
        'SELECT id_uzivatele, MAX(cas) as cas FROM akce_prihlaseni_log WHERE id_akce = $1 GROUP BY id_uzivatele',
        [$a->id()]
    );
    while ($r = mysqli_fetch_assoc($o)) {
        $casyPrihlaseni[$r['id_uzivatele']] = new DateTimeCz($r['cas']);
    }
    foreach ($ucastnici as $ucastnik) {
        $vek = $ucastnik->vekKDatu($a->zacatek() /* TODO REVERT -> */ ?? new DateTimeCz() /* <- TODO REVERT */);
        if ($vek === null) {
            $vek = '?';
        } elseif ($vek >= 18) {
            $vek = '18+';
        }
        $t->assign([
            'jmeno' => $ucastnik->jmenoNick(),
            'mail' => $ucastnik->mail(),
            'vek' => $vek,
            'telefon' => $ucastnik->telefon(),
            'casPrihlaseni' => isset($casyPrihlaseni[$ucastnik->id()]) ? $casyPrihlaseni[$ucastnik->id()]->format('j.n. H:i') : '<i>???</i>',
        ]);
        $t->parse('prehled.aktivita.ucast.ucastnik');
    }
    if ($ucastnici) {
        $t->parse('prehled.aktivita.ucast');
    }
    $t->assign([
        'nazevAktivity' => $a->nazev(),
        'obsazenost' => $a->obsazenostHtml(),
        'cas' => $a->denCas(),
        'maily' => implode(';', array_map(function ($u) {
            return $u->mail();
        }, $ucastnici)),
        'id' => $a->id(),
    ]);
    $t->parse('prehled.aktivita');
}

$t->assign('manual', Stranka::zUrl('manual-vypravece')->html());
$t->parse('prehled');
$t->out('prehled');
