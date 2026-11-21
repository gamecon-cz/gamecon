<?php

use Gamecon\Aktivita\TypAktivity;
use Gamecon\Cas\DateTimeGamecon;
use Gamecon\Web\ZpracovaneObrazky;
use Gamecon\Role\Role;

/** @var \Gamecon\XTemplate\XTemplate $t */
/** @var Modul $this */
/** @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */

$this->blackarrowStyl(true);
// texty jsou v katalozích, názvy a popisy linií v jazyce stránky z DB, česká a anglická titulka jsou jazykovými verzemi
$this->setObsahPrelozeny(true);
$this->info()
     ->nazev(t('titulka.info.nazev'))
     ->popis(t('titulka.info.popis'))
     // the module also answers on /titulka, the home page stays the one address
     ->setKanonickaCesta('');

// linie
$offsety = [120, 320, 280];
$typy    = serazenePodle(TypAktivity::zViditelnych(), 'poradi');
$obrazky = ZpracovaneObrazky::linieTitulka()->seznamObrazku();
foreach ($typy as $i => $typ) {
    $t->assign([
        'cislo'     => sprintf('%02d', $i + 1),
        // prostý text, do HTML se escapuje
        'nazev'     => htmlspecialchars(mb_ucfirst($typ->getNazevVJazyce())),
        'url'       => $typ->url(),
        'obrazek'   => $obrazky[$typ->id()]['src'] ?? '', // prevent errors, mainly for local testing
        'ikona'     => cestaObrazkuLinieNaTitulce($typ->id()),
        'aosOffset' => $offsety[$i % 3],
        'popis'     => htmlspecialchars($typ->getPopisKratkyVJazyce()),
    ]);
    $t->parse('titulka.linie');
}

// výčet toho, co na GameConu je, s odkazy na linie; věta je v katalogu celá, aby slovosled zůstal na překladu
$odkazyPopisu = [
    'deskove'   => ['deskoherna', t('titulka.popisbox.odkaz.deskove')],
    'rpg'       => ['rpg', t('titulka.popisbox.odkaz.rpg')],
    'larpy'     => ['larpy', t('titulka.popisbox.odkaz.larpy')],
    'akcni_hry' => ['bonusy', t('titulka.popisbox.odkaz.akcni_hry')],
    'wargaming' => ['wargaming', t('titulka.popisbox.odkaz.wargaming')],
    'turnaj'    => ['dnd', t('titulka.popisbox.odkaz.turnaj')],
    'workshopy' => ['bonusy', t('titulka.popisbox.odkaz.workshopy')],
];
// t() vrací prostý text: větu escapovat celou a značky odkazů nahradit až potom
$znackyOdkazu = [];
$odkazyPodleZnacky = [];
foreach ($odkazyPopisu as $nazev => [$cesta, $text]) {
    $znacka = "\u{E000}{$nazev}\u{E000}"; // znak ze soukromé oblasti Unicode, htmlspecialchars ho nechá a v textu není
    $znackyOdkazu[$nazev] = $znacka;
    $odkazyPodleZnacky[$znacka] = '<a href="' . $cesta . '">' . htmlspecialchars($text) . '</a>';
}
$t->assign('popisFestivalu', strtr(htmlspecialchars(t('titulka.popisbox.veta', $znackyOdkazu)), $odkazyPodleZnacky));

// odpočet
if (pred($systemoveNastaveni->prihlasovaniUcastnikuOd())) {
    $t->assign([
        'odpocetTimestamp' => $systemoveNastaveni->prihlasovaniUcastnikuOd()->getTimestamp(),
        'odpocetNadpis'    => htmlspecialchars(t(
            'titulka.odpocet.prihlasovani_zacne',
            ['zacatek' => $systemoveNastaveni->prihlasovaniUcastnikuOd()],
        )),
    ]);
} else {
    $t->assign([
        'odpocetTimestamp' => $systemoveNastaveni->gcBeziOd()->getTimestamp(),
        'odpocetNadpis'    => htmlspecialchars(t('titulka.odpocet.do_gameconu_zbyva')),
    ]);
    $t->parse('titulka.odpocetPrihlasit');
}

$zacatekGameconu = DateTimeGamecon::zacatekGameconu();
$konecGameconu   = DateTimeGamecon::konecGameconu();

$stovkySpokojenychUcastniku = dbFetchSingle(<<<SQL
SELECT FLOOR(COUNT(*) / 100) * 100
FROM uzivatele_role
WHERE id_role = $0
SQL,
    [
        0 => Role::pritomenNaRocniku(pred($systemoveNastaveni->spocitanyZacatekLetosnihoGameconu())
            ? $systemoveNastaveni->rocnik() - 1
            : $systemoveNastaveni->rocnik(),
        ),
    ],
);

$stovkyAktivit = dbFetchSingle(<<<SQL
SELECT FLOOR(COUNT(DISTINCT IF(patri_pod IS NULL, CONCAT('A', id_akce), CONCAT('I', patri_pod))) / 10) * 10
FROM akce_seznam
WHERE rok = $0
    AND typ NOT IN ($1)
SQL,
    [
        0 => pred($systemoveNastaveni->spocitanyZacatekLetosnihoGameconu())
            ? $systemoveNastaveni->rocnik() - 1
            : $systemoveNastaveni->rocnik(),
        1 => TypAktivity::interniTypy(),
    ],
);

// ostatní
$t->assign([
    // „16. – 19. 7.“ / „16 – 19 July“, vzor data je v katalogu
    'gcOd'                       => htmlspecialchars(t('titulka.hlavicka.zacatek', ['zacatek' => $zacatekGameconu])),
    'gcDo'                       => htmlspecialchars(t('titulka.hlavicka.konec', ['konec' => $konecGameconu])),
    'gcRok'                      => $konecGameconu->format('Y'),
    'stovkySpokojenychUcastniku' => $stovkySpokojenychUcastniku,
    'stovkyAktivit'              => $stovkyAktivit,
]);

// vyplnění obrázku
ZpracovaneObrazky::kartyTitulka()->vypisDoSablonySorted($t, 'titulka.karty');
ZpracovaneObrazky::cislaTitulka()->vypisDoSablonySorted($t, 'titulka.cisla');
ZpracovaneObrazky::fotkyTitulka()->vypisDoSablonySorted($t, 'titulka.fotky', 525, 525);
ZpracovaneObrazky::logaSponzoruTitulka()->vypisDoSablony($t, 'titulka.sponzor');
ZpracovaneObrazky::logaPartneruTitulka()->vypisDoSablony($t, 'titulka.partner');
