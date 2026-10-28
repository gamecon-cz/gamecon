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

// načíst aktivity DrD (všechna kola)
$aktivity = Aktivita::zFiltru(
    systemoveNastaveni: $systemoveNastaveni,
    filtr: [FiltrAktivity::TYP => TypAktivity::DRD, FiltrAktivity::ROK => ROCNIK],
);
if (empty($aktivity)) {
    // když aktivity nejsou založeny, použít z minulého roku
    $aktivity = Aktivita::zFiltru(
        systemoveNastaveni: $systemoveNastaveni,
        filtr: [FiltrAktivity::TYP => TypAktivity::DRD, FiltrAktivity::ROK => ROCNIK - 1],
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
    $fotka = $o->fotka();
    if (!$fotka) continue;
    $oSobe  = trim((string)$o->oSobe());
    $profil = trim((string)$o->drdProfil());
    $t->assign([
        'fotka'       => $fotka->kvalita(85)->pokryjOrez(240, 240),
        'jmenoNaWebu' => $o->jmenoNaWebu(),
        'tituly'      => mb_ucfirst(implode(', ', $o->drdTituly())),
        'oSobe'       => $oSobe,
        'profil'      => $profil,
    ]);
    // prázdný popis by se rozbalil do ničeho
    if ($oSobe !== '') {
        $t->parse('drdPj.pj.oSobe');
    }
    if ($profil !== '') {
        $t->parse('drdPj.pj.profil');
    }
    $t->parse('drdPj.pj');
}
