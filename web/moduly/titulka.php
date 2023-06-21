<?php

use Gamecon\Cas\DateTimeCz;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\Cas\DateTimeGamecon;
use Gamecon\Web\Loga;

/** @var \Gamecon\XTemplate\XTemplate $t */
/** @var Modul $this */
/** @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */

$this->blackarrowStyl(true);
$this->info()
    ->nazev('GameCon – největší festival nepočítačových her')
    ->popis('GameCon je největší festival nepočítačových her v České republice, který se každoročně koná třetí víkend v červenci. Opět se můžete těšit na desítky RPGček, deskovek, larpů, akčních her, wargaming, přednášky, klání v Příbězích Impéria, tradiční mistrovství v DrD a v neposlední řadě úžasné lidi a vůbec zážitky, které ve vás přetrvají minimálně do dalšího roku.')
    ->url(URL_WEBU);

// linie
$offsety = [120, 320, 280];
$typy    = serazenePodle(TypAktivity::zViditelnych(), 'poradi');
foreach ($typy as $i => $typ) {
    $t->assign([
        'cislo'     => sprintf('%02d', $i + 1),
        'nazev'     => mb_ucfirst($typ->nazev()),
        'url'       => $typ->url(),
        'obrazek'   => 'soubory/systemove/linie/' . $typ->id() . '.jpg',
        'ikona'     => 'soubory/systemove/linie-ikony/' . $typ->id() . '.png',
        'aosOffset' => $offsety[$i % 3],
        'popis'     => $typ->popisKratky(),
    ]);
    $t->parse('titulka.linie');
}

// sponzoři a partneři
Loga::logaSponzoru()->vypisDoSablony($t, 'titulka.sponzor');
Loga::logaPartneru()->vypisDoSablony($t, 'titulka.partner');

// odpočet
if ($systemoveNastaveni->predRegistraciUcastniku()) {
    $zacatek = $systemoveNastaveni->registraceUcastnikuOd()->format(DateTimeCz::FORMAT_ZACATEK_UDALOSTI);
    $t->assign([
        'odpocetTimestamp' => $systemoveNastaveni->registraceUcastnikuOd()->getTimestamp(),
        'odpocetNadpis'    => "Přihlašování začne $zacatek",
    ]);
} else {
    $t->assign([
        'odpocetTimestamp' => $systemoveNastaveni->gcBeziOd()->getTimestamp(),
        'odpocetNadpis'    => 'Do GameConu zbývá',
    ]);
    $t->parse('titulka.odpocetPrihlasit');
}

$zacatekGameconu = DateTimeGamecon::zacatekGameconu();
$konecGameconu   = DateTimeGamecon::konecGameconu();

// ostatní
$t->assign([
    'gcOd'  => $zacatekGameconu->format('j.'),
    'gcDo'  => $konecGameconu->format('j. n.'),
    'gcRok' => $konecGameconu->format('Y'),
]);
