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
        $t->assign([
            'aktivita'   => $aktivita,
            'obsazenost' => $aktivita->obsazenost() ? '('.trim($aktivita->obsazenost()).')' : '',
            'prihlasit'  => $aktivita->prihlasovatko($u),
        ]);
        $t->parse('aktivity.aktivita.termin');
    }

    $organizatori = implode(', ', array_map(function ($organizator) {
        return $organizator->jmenoNick();
    }, $aktivita->organizatori()));

    $t->assign([
        'aktivita'     => $aktivita,
        'obrazek'      => $aktivita->obrazek()->pasuj(512), // TODO kvalita?
        'organizatori' => $organizatori,
    ]);

    $t->parseEach($aktivita->tagy(), 'stitek', 'aktivity.aktivita.stitek');
    $t->parse('aktivity.aktivita');
}


// záhlaví

$this->info()->nazev(mb_ucfirst($typ->nazevDlouhy()));
$this->info()->obrazek(null);


// podstránky linie

$stranky = serazenePodle($typ->stranky(), 'poradi');
$t->parseEach($stranky, 'stranka', 'aktivity.stranka');
