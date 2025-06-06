<?php

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\FiltrAktivity;
use Gamecon\Aktivita\TypAktivity;

/** @var Modul $this */
/** @var Url $url */
/** @var \Gamecon\XTemplate\XTemplate $t */
/** @var Uzivatel|null $u */
/** @var Uzivatel|null|void $org */
/** @var Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */
/** @var null|\Gamecon\Aktivita\TypAktivity $typ */

$this->blackarrowStyl(true);
$this->pridejJsSoubor(__DIR__ . '/../soubory/blackarrow/_spolecne/zachovej-scroll.js');

$typ = $this->param('typ');

// zpracování POST požadavků

Aktivita::prihlasovatkoZpracuj($u, $u);
Aktivita::vyberTeamuZpracuj($u, $u);
Tym::vypisZpracuj($u);

// aktivity

$aktivity = Aktivita::zFiltru(
    systemoveNastaveni: $systemoveNastaveni,
    filtr: [
        FiltrAktivity::ROK             => $systemoveNastaveni->rocnik(),
        FiltrAktivity::JEN_VIDITELNE   => true,
        FiltrAktivity::BEZ_DALSICH_KOL => true,
        FiltrAktivity::TYP             => $typ
            ? $typ->id()
            : null,
        FiltrAktivity::ORGANIZATOR     => !empty($org)
            ? $org->id()
            : null,
    ],
);

$skupiny = seskupenePodle($aktivity, function (
    $aktivita,
) {
    return $aktivita->patriPod()
        ?: -$aktivita->id();
});

$skupiny = serazenePodle($skupiny, function (
    $skupina,
) {
    return current($skupina)->nazev();
});

foreach ($skupiny as $skupina) {
    $skupina = serazenePodle($skupina, 'zacatek');

    /** @var Aktivita $aktivita */
    foreach ($skupina as $aktivita) {
        $vyberTymu = $aktivita->vyberTeamu($u);
        if ($vyberTymu) {
            $t->assign('vyberTymu', $vyberTymu);
            $t->parse('aktivity.aktivita.termin.vyberTymu');
        }

        $tym = $aktivita->tym();
        if ($tym && in_array($aktivita->typId(), [TypAktivity::DRD, TypAktivity::LKD])) {
            $t->assign('tym', $tym);
            $t->parse('aktivity.aktivita.termin.tym');
        }

        $vypisTymu = $tym && $u && $aktivita->prihlasen($u)
            ? $tym->vypis()
            : null;
        if ($vypisTymu && !$vyberTymu) {
            $t->assign('vypisTymu', $vypisTymu);
            $t->parse('aktivity.aktivita.termin.vypisTymu');
        }

        $vypravec = current($aktivita->organizatori());
        if ($vypravec && ($aktivita->typId() == TypAktivity::DRD || $aktivita->patriPod() > 0)) {
            $t->assign('vypravec', $vypravec->jmenoNick());
            $t->parse('aktivity.aktivita.termin.vypravec');
        }

        $t->assign([
            'aktivita'   => $aktivita,
            'obsazenost' => $aktivita->obsazenost()
                ? '(' . trim($aktivita->obsazenost()) . ')'
                : '',
            'prihlasit'  => $aktivita->prihlasovatko($u),
        ]);

        $t->parse('aktivity.nahled.termin');
        $t->parse('aktivity.aktivita.termin');
    }

    $organizatori = implode(', ', array_map(static function (
        Uzivatel $organizator,
    ) {
        $url = $organizator->url(true);

        return $url === null
            // asi vypravěčská skupina nebo podobně
            ? $organizator->jmenoNick()
            : '<a href="' . $url . '">' . $organizator->jmenoNick() . '</a>';
    }, $aktivita->organizatori()));

    $obrazek = $aktivita->obrazek();

    $t->assign([
        'aktivita'           => $aktivita,
        'htmlId'             => $aktivita->urlId(),
        // nelze použít prosté #htmlId, protože to rozbije base href a odkazuje to pak o úroveň výš
        'kotva'              => URL_WEBU . '/' . $url->cela() . '#' . $aktivita->urlId(),
        'obrazek'            => $obrazek
            ? $obrazek->pasuj(512)
            : null, // TODO kvalita?
        'organizatori'       => $organizatori,
        'organizatoriNahled' => strtr($organizatori, [', ' => '<br>']),
        'kapacita'           => $aktivita->kapacita()
            ?: 'neomezeně',
    ]);

    $t->parseEach($aktivita->tagy(), 'stitek', 'aktivity.aktivita.stitek');
    $t->parseEach($aktivita->tagy(), 'stitek', 'aktivity.nahled.stitek');
    $t->parse('aktivity.aktivita');
    $t->parse('aktivity.nahled');
}

// záhlaví a informace

$this->info()->obrazek(null);

if (!empty($org)) {
    /** @var Uzivatel $org */
    $this->info()->nazev($org->jmenoNick());
    $t->assign([
        'jmeno' => $org->jmenoNick(),
        'popis' => $org->oSobe()
            ?: '<p><em>popisek od vypravěče nemáme</em></p>',
        'fotka' => $org->fotkaAuto()->kvalita(85)->pokryjOrez(180, 180),
    ]);
    $t->parse('aktivity.hlavickaVypravec');
} elseif ($typ) {
    $this->info()->nazev(mb_ucfirst($typ->nazevDlouhy()));

    $descriptionFile = 'soubory/systemove/linie-ikony/' . $typ->id() . '.txt';

    $lines = is_file($descriptionFile)
        ? @file($descriptionFile, FILE_IGNORE_NEW_LINES)
        : false;

    $picture_path = 'soubory/systemove/linie-ikony/org_' . $typ->id() . '.jpg';

    if (!is_file($picture_path)) {
        $picture_path = "soubory/obsah/obrazky/organizatori/flant.jpg";
    }

    /* 'ikonaLiniePopis' => $varIkonaLiniePopis, */
    $t->assign([
        'popisLinie'      => $typ->oTypu(),
        'ikonaLinie'      => $picture_path,
        'ikonaLinieSekce' => $lines[0] ?? 'Sekce',
        'ikonaLinieJmeno' => $lines[1] ?? 'Jmeno "Prezdivka" Prijmeni',
        'ikonaLinieEmail' => $lines[2] ?? 'info@gamecon.cz',
        'specTridy'       => $typ->id() == TypAktivity::DRD
            ? 'aktivity_aktivity-drd'
            : null,
    ]);

    // podstránky linie
    $stranky = serazenePodle($typ->stranky(), 'poradi');
    $t->parseEach($stranky, 'stranka', 'aktivity.stranka');

    if (!$systemoveNastaveni->jsmeNaOstre() && $u && $u->jeOrganizator()) {
        $t->assign('urlEditaceStranek', URL_ADMIN . '/web/editace-stranek');
        $t->assign('prikladUrlStranky', $typ->url() . '/nemas-zdani-co-je-k-mani');
        $t->parse('aktivity.strankaNavod');
    }
}
