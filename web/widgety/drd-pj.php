<?php

use Gamecon\Aktivita\Aktivita;

/** @var \Gamecon\XTemplate\XTemplate $t */

// načíst aktivity DrD (všechna kola)
$aktivity = Aktivita::zFiltru(['typ' => \Gamecon\Aktivita\TypAktivity::DRD, 'rok' => ROCNIK]);
if (empty($aktivity)) {
    // když aktivity nejsou založeny, použít z minulého roku
    $aktivity = Aktivita::zFiltru(['typ' => \Gamecon\Aktivita\TypAktivity::DRD, 'rok' => ROCNIK - 1]);
}

// načíst organizátory aktivit
$organizatori = [];
foreach ($aktivity as $a) {
    foreach ($a->organizatori() as $o) $organizatori[] = $o;
}

// vyfiltrovat unikátní organizátory a seřadit
$organizatori = array_unique($organizatori, SORT_REGULAR); // regular sort aby fungovalo unique pro objekty
usort($organizatori, function ($a, $b) {
    return strcasecmp($a->nick(), $b->nick());
});

// zobrazit
foreach ($organizatori as $o) {
    if (!$o->fotka()) continue;
    $t->assign([
        'pj' => $o,
        'tituly' => mb_ucfirst(implode(', ', $o->drdTituly())),
    ]);
    $t->parse('drdPj.pj', 'pj');
}
