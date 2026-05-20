<?php

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\Aktivita\FiltrAktivity;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

/**
 * @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni|null $systemoveNastaveni
 * @var \Gamecon\XTemplate\XTemplate $t
 */

$systemoveNastaveni ??= SystemoveNastaveni::zGlobals();

// načíst aktivity DnD (všechna kola)
$aktivity = Aktivita::zFiltru(
    systemoveNastaveni: $systemoveNastaveni,
    filtr: [FiltrAktivity::TYP => TypAktivity::DND, FiltrAktivity::ROK => ROCNIK],
);
if (empty($aktivity)) {
    // když aktivity nejsou založeny, použít z minulého roku
    $aktivity = Aktivita::zFiltru(
        systemoveNastaveni: $systemoveNastaveni,
        filtr: [FiltrAktivity::TYP => TypAktivity::DND, FiltrAktivity::ROK => ROCNIK - 1],
    );
}

// načíst organizátory aktivit
$organizatori = [];
foreach ($aktivity as $a) {
    foreach ($a->organizatori() as $o) {
        $organizatori[] = $o;
    }
}

// vyfiltrovat unikátní organizátory a seřadit
$organizatori = array_unique($organizatori, SORT_REGULAR); // regular sort aby fungovalo unique pro objekty
usort($organizatori, function (
    $a,
    $b,
) {
    return strcasecmp($a->jmenoNaWebu(), $b->jmenoNaWebu());
});

// zobrazit
foreach ($organizatori as $o) {
    if (!$o->fotka()) continue;
    $t->assign([
        'pj'           => $o,
        'jmenoNaWebu'  => $o->jmenoNaWebu()
    ]);
    $t->parse('dndPj.pj', 'pj');
}
