<?php

/**
 * Přehled vypravěčů na GameConu
 * @var \Gamecon\XTemplate\XTemplate $t
 */

use Gamecon\Role\Role;

$vypraveci = Uzivatel::zRole(Role::LETOSNI_VYPRAVEC(ROCNIK));

usort($vypraveci, function(Uzivatel $a, Uzivatel $b) {
    return strcasecmp($a->jmenoNaWebu(), $b->jmenoNaWebu());
});

foreach ($vypraveci as $vypravec) {
    $medailonekUrl = $vypravec->url(true);
    // Vypravěče bez profilu/URL přeskočíme
    if (!$medailonekUrl) {
        continue;
    }

    $t->assign([
        'jmeno'         => $vypravec->jmenoNaWebu(),
        'medailonekUrl' => $medailonekUrl,
    ]);
    
    $fotka = $vypravec->fotka(); // Používáme fotka() místo fotkaAuto(), abychom správně zobrazili placeholder, pokud úča nahrál vlastní nemá
    if ($fotka) {
        $t->assign('fotkaUrl', $fotka->pokryjOrez(400, 400)->url());
        $t->parse('vypraveci.vypravec.fotka');
    } else {
        $t->parse('vypraveci.vypravec.bezFotky');
    }

    $t->parse('vypraveci.vypravec');
}

$t->parse('vypraveci');
