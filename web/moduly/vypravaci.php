<?php

use Gamecon\Aktivita\TypAktivity;
use Gamecon\Uzivatel\PrehledVypravecu;

/** @var Modul $this */
/** @var Url $url */
/** @var \Gamecon\XTemplate\XTemplate $t */
/** @var Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */

$this->blackarrowStyl(true);
$this->info()->nazev('Vypravěči');

// vypravaci/rpg ukáže jen vypravěče RPG
$vybranaSekce = null;
$urlSekce     = $url->cast(1);
if ($urlSekce !== null) {
    if ($url->cast(2) !== null) {
        throw new Nenalezeno();
    }
    // i linie, ve které zatím nikdo nevede, ať sdílený odkaz nevede na chybu
    $vybranaSekce = current(array_filter(
        TypAktivity::zViditelnych(),
        static fn (TypAktivity $linie) => $linie->url() === $urlSekce,
    )) ?: throw new Nenalezeno();
    $this->info()->nazev('Vypravěči – ' . mb_ucfirst($vybranaSekce->nazev()));
    // na telefonu může být vybraná sekce ve filtru mimo obrazovku
    $t->assign('nazevVybraneSekce', htmlspecialchars(mb_ucfirst($vybranaSekce->nazev())));
    $t->parse('vypravaci.nadpisSekce');
}

$prehled = new PrehledVypravecu($systemoveNastaveni->rocnik());

$t->assign('rocnik', $systemoveNastaveni->rocnik());

$sekce = $prehled->sekce();
if ($sekce) {
    $t->assign('vsichniAktivni', $vybranaSekce === null ? ' aria-current="page"' : '');
    foreach ($sekce as $linie) {
        $t->assign([
            'urlSekce'     => $linie->url(),
            'nazevSekce'   => htmlspecialchars(mb_ucfirst($linie->nazev())),
            'sekceAktivni' => $linie->url() === $urlSekce ? ' aria-current="page"' : '',
        ]);
        $t->parse('vypravaci.filtr.sekce');
    }
    $t->parse('vypravaci.filtr');
}

$vypraveci = $prehled->vypraveci($vybranaSekce);
foreach ($vypraveci as $vypravec) {
    $oSobe      = $prehled->oSobe($vypravec);
    $nazvySekci = array_map(
        static fn (TypAktivity $linie) => $linie->nazev(),
        $prehled->sekceVypravece($vypravec),
    );
    $t->assign([
        'id'          => $vypravec->id(),
        // kdo fotku nenahrál, má náhradní podle pohlaví
        'fotka'       => $vypravec->fotkaAuto()->kvalita(85)->pokryjOrez(240, 240),
        'jmenoNaWebu' => htmlspecialchars($vypravec->jmenoNaWebu()),
        'sekce'       => htmlspecialchars(mb_ucfirst(implode(', ', $nazvySekci))),
        'oSobe'       => $oSobe,
    ]);
    if ($nazvySekci) {
        $t->parse('vypravaci.medailonky.vypravec.sekce');
    }
    // prázdný popis by se rozbalil do ničeho
    if ($oSobe !== '') {
        $t->parse('vypravaci.medailonky.vypravec.oSobe');
    }
    $t->parse('vypravaci.medailonky.vypravec');
}
if ($vypraveci) {
    $t->parse('vypravaci.medailonky');
} else {
    $t->parse($vybranaSekce ? 'vypravaci.zadniVSekci' : 'vypravaci.zadni');
}
