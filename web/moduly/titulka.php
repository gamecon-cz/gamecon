<?php

use Gamecon\Cas\DateTimeCz;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\Cas\DateTimeGamecon;
use Gamecon\Web\Loga;
use Gamecon\Role\Role;

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
if (pred($systemoveNastaveni->prihlasovaniUcastnikuOd())) {
    $zacatek = $systemoveNastaveni->prihlasovaniUcastnikuOd()->format(DateTimeCz::FORMAT_ZACATEK_UDALOSTI);
    $t->assign([
        'odpocetTimestamp' => $systemoveNastaveni->prihlasovaniUcastnikuOd()->getTimestamp(),
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

if (date('Y-m-d') === '2025-04-01') {
    $t->parse(block: 'titulka.april2025');
}

// ostatní
$t->assign([
    'gcOd'                       => $zacatekGameconu->format('j.'),
    'gcDo'                       => $konecGameconu->format('j. n.'),
    'gcRok'                      => $konecGameconu->format('Y'),
    'stovkySpokojenychUcastniku' => dbFetchSingle(<<<SQL
SELECT FLOOR(COUNT(*) / 100) * 100
FROM uzivatele_role
WHERE id_role = $0
SQL,
        [
            0 => Role::pritomenNaRocniku(po($systemoveNastaveni->spocitanyKonecLetosnihoGameconu())
                ? $systemoveNastaveni->rocnik()
                : $systemoveNastaveni->rocnik() - 1,
            ),
        ],
    ),
    'stovkyAktivit'              => dbFetchSingle(<<<SQL
SELECT FLOOR(COUNT(*) / 10) * 10
FROM akce_seznam
WHERE rok = $0
    AND patri_pod IS NULL
    AND typ NOT IN ($1)
SQL,
        [
            0 => po($systemoveNastaveni->spocitanyKonecLetosnihoGameconu())
                ? $systemoveNastaveni->rocnik()
                : $systemoveNastaveni->rocnik() - 1,
            1 => TypAktivity::interniTypy(),
        ],
    ),
]);
