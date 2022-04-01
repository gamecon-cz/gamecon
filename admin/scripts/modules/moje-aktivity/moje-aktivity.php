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

$testovani = defined('TESTING') && TESTING && !empty($_GET['test']);

if (get('id')) {
    require __DIR__ . '/_moje-aktivita.php';
    return;
}

$organizovaneAktivity = Aktivita::zFiltru(
    [
        'organizator' => $testovani
            ? null
            : $u->id(),
        'rok' => ROK,
    ],
    [],
    $testovani
        ? 10
        : null
);

$template = new XTemplate(basename(__DIR__ . '/moje-aktivity.xtpl'));

if (!$organizovaneAktivity) {
    $template->parse('prehled.zadnaAktivita');
} else {
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
            $template->assign([
                'jmeno' => $ucastnik->jmenoNick(),
                'mail' => $ucastnik->mail(),
                'vek' => $vek,
                'telefon' => $ucastnik->telefon(),
                'casPrihlaseni' => isset($casyPrihlaseni[$ucastnik->id()])
                    ? $casyPrihlaseni[$ucastnik->id()]->format('j.n. H:i')
                    : '<i>???</i>',
            ]);
            $template->parse('prehled.aktivita.ucast.ucastnik');
        }
        if ($ucastnici) {
            $template->parse('prehled.aktivita.ucast');
        }
        $template->assign([
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
        $template->assign('soucasnaUrl', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
        $template->assign('testovani', $testovani);
        $template->parse('prehled.aktivita.onlinePrezence');
        $template->parse('prehled.aktivita');
    }
}

$template->assign('manual', Stranka::zUrl('manual-vypravece')->html());
$template->parse('prehled');
$template->out('prehled');
