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

$testovani = defined('TESTING') && TESTING && isset($_GET['test']);

$organizovaneAktivity = Aktivita::zFiltru([
    'organizator' => $testovani
        ? null
        : $u->id(),
    'rok' => ROK,
]);

$t = new XTemplate(basename(__DIR__ . '/moje-aktivity.xtpl'));

if (empty($organizovaneAktivity)) {
    $t->parse('prehled.zadnaAktivita');
}
foreach ($organizovaneAktivity as $organizovanaAktivita) {
    $ucastnici = $testovani
        ? Uzivatel::zHledani('kru')
        : $organizovanaAktivita->prihlaseni();
    $o = dbQuery(
        'SELECT id_uzivatele, MAX(cas) AS cas FROM akce_prihlaseni_log WHERE id_akce = $1 GROUP BY id_uzivatele',
        [$organizovanaAktivita->id()]
    );
    while ($r = mysqli_fetch_assoc($o)) {
        $casyPrihlaseni[$r['id_uzivatele']] = new DateTimeCz($r['cas']);
    }
    foreach ($ucastnici as $ucastnik) {
        $vek = $ucastnik->vekKDatu($organizovanaAktivita->zacatek() ?? ($testovani ? new DateTimeCz() : null));
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
            'casPrihlaseni' => isset($casyPrihlaseni[$ucastnik->id()])
                ? $casyPrihlaseni[$ucastnik->id()]->format('j.n. H:i')
                : '<i>???</i>',
        ]);
        $t->parse('prehled.aktivita.ucast.ucastnik');
    }
    if ($ucastnici) {
        $t->parse('prehled.aktivita.ucast');
    }
    $t->assign([
        'nazevAktivity' => $organizovanaAktivita->nazev(),
        'obsazenost' => $organizovanaAktivita->obsazenostHtml(),
        'cas' => $organizovanaAktivita->denCas(),
        'maily' => implode(
            ';',
            array_map(
                static function ($u) {
                    return $u->mail();
                },
                $ucastnici
            )
        ),
        'id' => $organizovanaAktivita->id(),
    ]);
    $t->parse('prehled.aktivita');
}

$t->assign('manual', Stranka::zUrl('manual-vypravece')->html());
$t->parse('prehled');
$t->out('prehled');
