<?php

$this->blackarrowStyl(true);
$this->pridejJsSoubor('soubory/blackarrow/_spolecne/zachovej-scroll.js');

$typ = $this->param('typ');


// zpracování POST požadavků

Aktivita::prihlasovatkoZpracuj($u);
Aktivita::vyberTeamuZpracuj($u);
Tym::vypisZpracuj($u);


// aktivity

$aktivity = Aktivita::zFiltru([
    'rok'           => ROK,
    'jenViditelne'  => true,
    'bezDalsichKol' => true,
    'typ'           => $typ->id(),
]);

$skupiny = seskupenePodle($aktivity, function ($aktivita) {
    return $aktivita->patriPod() ?: -$aktivita->id();
});

$skupiny = serazenePodle($skupiny, function ($skupina) {
    return current($skupina)->nazev();
});

foreach ($skupiny as $skupina) {
    $skupina = serazenePodle($skupina, 'zacatek');

    foreach ($skupina as $aktivita) {
        $vyberTymu = $aktivita->vyberTeamu($u);
        if ($vyberTymu) {
            $t->assign('vyberTymu', $vyberTymu);
            $t->parse('aktivity.aktivita.termin.vyberTymu');
        }

        $tym = $aktivita->tym();
        if ($tym && in_array($aktivita->typId(), [Typ::DRD, Typ::LKD])) {
            $t->assign('tym', $tym);
            $t->parse('aktivity.aktivita.termin.tym');
        }

        $vypisTymu = $tym && $u && $aktivita->prihlasen($u) ? $tym->vypis() : null;
        if ($vypisTymu && !$vyberTymu) {
            $t->assign('vypisTymu', $vypisTymu);
            $t->parse('aktivity.aktivita.termin.vypisTymu');
        }

        $vypravec = current($aktivita->organizatori());
        if ($vypravec && $aktivita->typId() == Typ::DRD) {
            $t->assign('vypravec', $vypravec->jmenoNick());
            $t->parse('aktivity.aktivita.termin.vypravec');
        }

        $t->assign([
            'aktivita'   => $aktivita,
            'obsazenost' => $aktivita->obsazenost() ? '('.trim($aktivita->obsazenost()).')' : '',
            'prihlasit'  => $aktivita->prihlasovatko($u),
        ]);

        $t->parse('aktivity.nahled.termin');
        $t->parse('aktivity.aktivita.termin');
    }

    $organizatori = implode(', ', array_map(function ($organizator) {
        return $organizator->jmenoNick();
    }, $aktivita->organizatori()));

    $obrazek = $aktivita->obrazek();

    $t->assign([
        'aktivita'     => $aktivita,
        'obrazek'      => $obrazek ? $obrazek->pasuj(512) : null, // TODO kvalita?
        'organizatori' => $organizatori,
        'organizatoriNahled' => strtr($organizatori, [', ' => '<br>']),
        'kapacita'     => $aktivita->kapacita() ?: 'neomezeně',
    ]);

    $t->parseEach($aktivita->tagy(), 'stitek', 'aktivity.aktivita.stitek');
    $t->parseEach($aktivita->tagy(), 'stitek', 'aktivity.nahled.stitek');
    $t->parse('aktivity.aktivita');
    $t->parse('aktivity.nahled');
}


// záhlaví a informace

$this->info()->nazev(mb_ucfirst($typ->nazevDlouhy()));
$this->info()->obrazek(null);
$t->assign([
    'popisLinie' => $typ->oTypu(),
    'ikonaLinie' => 'soubory/systemove/linie-ikony/' . $typ->id() . '.png',
    'specTridy'  => $typ->id() == Typ::DRD ? 'aktivity_aktivity-drd' : null,
]);


// podstránky linie

$stranky = serazenePodle($typ->stranky(), 'poradi');
$t->parseEach($stranky, 'stranka', 'aktivity.stranka');
