<?php

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\FiltrAktivity;
use Gamecon\Aktivita\JazykAktivity;
use Gamecon\Aktivita\KontaktSefaLinie;
use Gamecon\Aktivita\Program;
use Gamecon\Aktivita\RocnikyAktivit;
use Gamecon\Aktivita\Tag;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\ArchivPrednasek\ArchivPrednasekStranka;
use Gamecon\Cas\DatumVJazyce;
use Gamecon\Jazyk\AktualniJazyk;
use Gamecon\KategorieTagu;
use Gamecon\Uzivatel\Medailonek;

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
// Stránka linie je přeložená, když má anglický úvod (stránka z CMS). Rozhraní výpisu je přeložené vždy a názvy
// a popisy aktivit mají vlastní `lang` podle jazyka aktivity. Medailonek vypravěče (/<id>) přeložený není.
if ($typ) {
    $this->setObsahPrelozeny($typ->getStrankaO()?->isPrelozena() ?? false);
}

// zpracování POST požadavků

Aktivita::prihlasovatkoZpracuj($u, $u);

// ročník, dřívější jde vybrat v liště nad aktivitami (?rocnik=2024)

$letosniRocnik = $systemoveNastaveni->rocnik();
// ve kterých ročnících tu něco bylo
$rocnikySAktivitami = RocnikyAktivit::getSAktivitami(
    $letosniRocnik,
    $typ?->id(),
    !empty($org)
        ? $org->id()
        : null,
);
// /<id> najde kohokoli (Uzivatel::zUrl()), medailonek ale patří jen vypravěči
if (!empty($org) && !Medailonek::maStrankuVypravece($org, $letosniRocnik, $rocnikySAktivitami)) {
    throw new Nenalezeno();
}
// letošní i bez aktivit, ať se jde vrátit
$rocniky = RocnikyAktivit::pridejLetosni($letosniRocnik, $rocnikySAktivitami);

$rocnik = (int)get('rocnik');
if (!in_array($rocnik, $rocniky, true)) {
    $rocnik = $letosniRocnik;
}
$minulyRocnik = $rocnik !== $letosniRocnik;
if ($minulyRocnik) {
    $this->info()->setKanonickyParametr('rocnik', $rocnik);
}

// aktivity

$aktivity = Aktivita::zFiltru(
    systemoveNastaveni: $systemoveNastaveni,
    filtr: [
        FiltrAktivity::ROK             => $rocnik,
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

// data pro filtry nad seznamem, jedna položka na kartu
$kartyProFiltr          = [];
$dnyProFiltr            = [];
$klicePodleNazvuDne     = [];
$vypraveciProFiltr      = [];
// „Jen volná místa“: během registrace jen termín, na který se dá přihlásit, a přihlášenému jen s místem pro jeho pohlaví.
// Mimo ni stačí jakékoli volné místo bez ohledu na to, kdo se dívá, ať jde zjistit, co se nezaplnilo. Na minulý ročník
// se nepřihlašuje, i když v něm aktivita zůstala aktivovaná (prihlasovatelna() rok nehlídá), takže tam vždy stačí volné
// místo.
$volnoJenKPrihlaseni = !$minulyRocnik && $systemoveNastaveni->probihaRegistraceAktivit();

// medailonek /<id> má každý, i vypravěčská skupina, která má jen přezdívku a jméno nevyplněné;
// jméno si vypravěč píše sám a XTemplate neescapuje
$odkazNaMedailonek = static fn (Uzivatel $organizator): string
    => '<a href="' . $organizator->id() . '">' . htmlspecialchars($organizator->jmenoNaWebu()) . '</a>';

foreach ($skupiny as $skupina) {
    $skupina = serazenePodle($skupina, 'zacatek');

    $dnyKarty     = [];
    $volnoNaKarte = false;

    // ID vypravěče => odkaz na jeho medailonek, ze všech termínů karty v pořadí termínů
    $vypraveciKarty = [];
    // ID termínu => [ID vypravěče => odkaz]; u DrD a skupin má každý termín svého vypravěče
    $vypraveciTerminu = [];
    // ID vypravěčů termínu bez ohledu na pořadí => true
    $sestavyVypravecu = [];
    foreach ($skupina as $aktivita) {
        $vypraveciTerminu[$aktivita->id()] = [];
        foreach ($aktivita->organizatori() as $organizator) {
            $vypraveciKarty[$organizator->id()] ??= $odkazNaMedailonek($organizator);
            $vypraveciTerminu[$aktivita->id()][$organizator->id()] = $vypraveciKarty[$organizator->id()];
            // filtr kartu najde u vypravěče kteréhokoli termínu
            $vypraveciProFiltr[$organizator->id()] ??= $organizator->jmenoNaWebu();
        }
        $idVypravecuTerminu = array_keys($vypraveciTerminu[$aktivita->id()]);
        sort($idVypravecuTerminu);
        $sestavyVypravecu[implode(',', $idVypravecuTerminu)] = true;
    }
    // Vedou-li všechny termíny titíž, vypíše je hlavička karty i náhled jednou. Jinak (první kolo DrD má desítky PJ)
    // by byl seznam v hlavičce dlouhý a opakoval by termíny, takže je má jen každý termín u sebe, i v náhledu.
    $vypraveciVsechTerminuStejni = count($sestavyVypravecu) <= 1;

    /** @var Aktivita $aktivita */
    foreach ($skupina as $aktivita) {
        // programový den jako v programu, noční hra po půlnoci patří k předchozímu dni (záměr), i když karta ukazuje skutečný den
        $den = $aktivita->denProgramu();
        if ($den) {
            $nazevDne               = $den->format('l');
            // transliterace je drahá a dnů je pár
            $klicDne                = $klicePodleNazvuDne[$nazevDne] ??= odstranDiakritiku($nazevDne);
            $dnyKarty[$klicDne]     = true;
            // klíč zůstává český (filtr v URL), název je pro čtenáře
            $dnyProFiltr[$klicDne] ??= ['datum' => $den->format('Y-m-d'), 'nazev' => DatumVJazyce::denVTydnu($den)];
        }
        $volnoNaKarte = $volnoNaKarte || ($volnoJenKPrihlaseni
            ? $aktivita->isVolnoKPrihlaseni($u)
            : $aktivita->isVolno());
        $vypraveciTohotoTerminu = $vypraveciTerminu[$aktivita->id()];
        if ($vypraveciTohotoTerminu) {
            $t->assign('vypravec', implode(', ', $vypraveciTohotoTerminu));
            if ($aktivita->typId() == TypAktivity::DRD || $aktivita->patriPod() > 0) {
                $t->parse('aktivity.aktivita.termin.vypravec');
            }
            if (!$vypraveciVsechTerminuStejni) {
                $t->parse('aktivity.nahled.termin.vypravec');
            }
        }

        if ($minulyRocnik) {
            $prihlasit = '';
        } elseif ($aktivita->tymova() && $u && $u->gcPrihlasen() && $aktivita->prihlasovatelna()) {
            $prihlasit = $aktivita->getPrihlasovatkoTymu($u);
        } else {
            $prihlasit = $aktivita->prihlasovatko($u);
        }

        $t->assign([
            'aktivita'   => $aktivita,
            // „pátek 19–23“ jako Aktivita::denCasSkutecny(), ten je ale česky vždy (e-mail sledujícím)
            'denCas'     => $aktivita->zacatek() && $aktivita->konec()
                ? htmlspecialchars(t('aktivity.termin', ['zacatek' => $aktivita->zacatek(), 'konec' => $aktivita->konec()]))
                : '',
            'obsazenost' => $aktivita->obsazenost() ?: '',
            'prihlasit'  => $prihlasit,
        ]);

        $t->parse('aktivity.nahled.termin');
        $t->parse('aktivity.aktivita.termin');
    }

    $obrazek = $aktivita->obrazek();

    $jazykTextu = $aktivita->jazyk()->getJazykTextu();
    $t->assign([
        'aktivita'           => $aktivita,
        'htmlId'             => $aktivita->urlId(),
        // nelze použít prosté #htmlId, protože to rozbije base href a odkazuje to pak o úroveň výš
        'kotva'              => urlWebu($url->cela())
            . ($minulyRocnik
                ? '?rocnik=' . $rocnik
                : '')
            . '#' . $aktivita->urlId(),
        'obrazek'            => $obrazek
            ? $obrazek->pasuj(512)
            : null, // TODO kvalita?
        // vypravěči všech termínů, ne jen posledního ($aktivita tu zbyla z cyklu)
        'organizatori'       => implode(', ', $vypraveciKarty),
        // čárka v názvu skupiny („Albi, s.r.o.“) by se při nahrazení čárek rozdělila na dva řádky
        'organizatoriNahled' => implode('<br>', $vypraveciKarty),
        'kapacita'           => $aktivita->kapacita()
            ?: htmlspecialchars(t('aktivity.neomezene')),
        // název a popisy píše vypravěč v jazyce aktivity, rozhraní kolem nich je v jazyce stránky
        'jazykTextu'         => $jazykTextu !== null
            ? ' lang="' . $jazykTextu->value . '"'
            : '',
    ]);
    if ($vypraveciKarty && $vypraveciVsechTerminuStejni) {
        $t->parse('aktivity.aktivita.vypraveci');
        $t->parse('aktivity.nahled.vypraveci');
    }

    if ($aktivita->jazyk() !== JazykAktivity::CESKY) {
        // popisek() je prostý text z t(), do HTML se escapuje
        $t->assign('jazyk', htmlspecialchars($aktivita->jazyk()->popisek()));
        $t->parse('aktivity.aktivita.jazyk');
        $t->parse('aktivity.nahled.jazyk');
    }
    $t->parseEach($aktivita->getNazvyTaguVJazyce(), 'stitek', 'aktivity.aktivita.stitek');
    $t->parseEach($aktivita->getNazvyTaguVJazyce(), 'stitek', 'aktivity.nahled.stitek');
    $t->parse('aktivity.aktivita');
    $t->parse('aktivity.nahled');

    $kartyProFiltr[] = [
        'htmlId'    => $aktivita->urlId(),
        'id'        => (int)$aktivita->id(),
        'tagy'      => $aktivita->tagyId(),
        'dny'       => array_keys($dnyKarty),
        'vypraveci' => array_keys($vypraveciKarty),
        'volno'     => $volnoNaKarte,
    ];
}

// u jediné karty není co filtrovat; co na menší stránce nic nezúží (jediný den), schová lišta sama
$filtrAktivit = null;
if (count($kartyProFiltr) >= 2) {
    $nazvyKategoriiTagu = [];
    foreach (KategorieTagu::zVsech() as $kategorieTagu) {
        // v jazyce stránky, Preact podle textu kategorie seskupuje
        $nazvyKategoriiTagu[$kategorieTagu->id()] = $kategorieTagu->getNazevVJazyce();
    }
    uasort($dnyProFiltr, static fn(
        array $den,
        array $jinyDen,
    ) => $den['datum'] <=> $jinyDen['datum']);
    (new Collator('cs'))->asort($vypraveciProFiltr);

    $filtrAktivit = [
        'karty'     => $kartyProFiltr,
        // seřazené podle pořadí kategorie a názvu, viz Tag::dotaz()
        'tagy'      => array_map(static fn(
            Tag $tag,
        ) => [
            'id'        => $tag->id(),
            'nazev'     => $tag->getNazevVJazyce(),
            'kategorie' => $nazvyKategoriiTagu[$tag->idKategorieTagu()] ?? '',
        ], Tag::zIds(array_values(array_unique(array_merge(...array_column($kartyProFiltr, 'tagy')))))),
        'dny'       => array_map(static fn(
            string $klic,
            array  $den,
        ) => ['klic' => $klic, 'nazev' => $den['nazev']], array_keys($dnyProFiltr), $dnyProFiltr),
        'vypraveci' => array_map(static fn(
            int    $id,
            string $jmeno,
        ) => ['id' => $id, 'jmeno' => $jmeno], array_keys($vypraveciProFiltr), $vypraveciProFiltr),
    ];
}

// přepínač ročníku, i u stránky bez filtrů
$rocnikyAktivit = count($rocniky) > 1
    ? ['rocnik' => $rocnik, 'letosni' => $letosniRocnik, 'rocniky' => $rocniky]
    : null;

// záhlaví a informace

$this->info()->obrazek(null);
// ať je odkaz na dřívější ročník poznat už podle názvu stránky
$pripojkaRocniku = $minulyRocnik
    ? ' ' . $rocnik
    : '';

if (!empty($org)) {
    /** @var Uzivatel $org */
    $this->info()->nazev($org->jmenoNaWebu() . $pripojkaRocniku);
    $t->assign([
        'jmeno' => htmlspecialchars($org->jmenoNaWebu()),
        'popis' => $org->oSobe()
            ?: '<p><em>' . htmlspecialchars(t('aktivity.vypravec_bez_popisu')) . '</em></p>',
        'fotka' => $org->fotkaAuto()->kvalita(85)->pokryjOrez(180, 180),
    ]);
    $t->parse('aktivity.hlavickaVypravec');
} elseif ($typ) {
    // dlouhý název je z nadpisu české úvodní stránky linie, jinde název linie v jazyce stránky
    $nazevLinie = AktualniJazyk::jazyk()->jeVychozi()
        ? $typ->nazevDlouhy()
        : $typ->getNazevVJazyce();
    $this->info()->nazev(mb_ucfirst($nazevLinie) . $pripojkaRocniku);

    // stejný kontakt dostane mladší účastník v upozornění na věk; bez hlavičky zástupné texty
    $sefSekce = KontaktSefaLinie::zTypu($typ->id());

    $picture_path = URL_WEBU . '/' . cestaObrazkuLinie($typ->id());

    /* 'ikonaLiniePopis' => $varIkonaLiniePopis, */
    $t->assign([
        'popisLinie'      => $typ->oTypu(),
        'ikonaLinie'      => $picture_path,
        // texty z adminu jsou prostý text jako t(), do HTML se escapují
        'ikonaLinieSekce' => htmlspecialchars($sefSekce->getSekceVJazyce() ?? t('linie.hlavicka.sekce')),
        'ikonaLinieJmeno' => htmlspecialchars($sefSekce->getJmeno() ?? t('linie.hlavicka.jmeno')),
        'ikonaLinieEmail' => htmlspecialchars($sefSekce->getEmail()),
        'specTridy'       => $typ->id() == TypAktivity::DRD
            ? 'aktivity_aktivity-drd'
            : null,
    ]);

    // podstránky linie
    $stranky = serazenePodle($typ->stranky(), 'poradi');
    $t->parseEach($stranky, 'stranka', 'aktivity.stranka');
    // přehled vypravěčů je jen letošní a sekce má jen pro linie z menu; bez vypravěčů by odkaz vedl na prázdný výpis
    if (!$minulyRocnik && $typ->isVMenu() && $vypraveciProFiltr) {
        $t->assign('urlLinie', $typ->url());
        $t->parse('aktivity.vypraveciLinie');
    }
    // archiv proběhlých přednášek nemá ročník, odkaz je i u minulého ročníku
    if ($typ->id() === TypAktivity::PREDNASKA) {
        $t->assign('urlArchivuPrednasek', ArchivPrednasekStranka::getCesta($typ));
        $t->parse('aktivity.archivPrednasek');
    }

    if (!$systemoveNastaveni->jsmeNaOstre() && $u && $u->jeOrganizator()) {
        $t->assign('urlEditaceStranek', URL_ADMIN . '/web/editace-stranek');
        $t->assign('urlEditaceHlavickyLinie', URL_ADMIN . '/web/hlavicky-linii');
        $t->assign('prikladUrlStranky', $typ->url() . '/nemas-zdani-co-je-k-mani');
        $t->parse('aktivity.strankaNavod');
    }
}

?>

<link rel="stylesheet" href="<?= zabalWebSoubor('soubory/ui/style.css') ?>">
<div id="preact-aktivity-modal"></div>
<?php
    $konstanty   = json_encode(Program::gameconKonstanty(false, "aktivity"));
    $prednacteni = json_encode(
        [
            'přihlášenýUživatel' => Uzivatel::apiPrihlasenyUzivatel(),
            'filtrAktivit'       => $filtrAktivit,
            'rocnikyAktivit'     => $rocnikyAktivit,
        ],
        // HEX_TAG, ať názvy nemůžou ukončit <script>
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG,
    );
?>
<script>
    // Konstanty předáváné do Preactu (env.ts)
    window.GAMECON_KONSTANTY = <?= $konstanty ?>;
    window.gameconPřednačtení = <?= $prednacteni ?>;
</script>

<script type="module" src="<?= zabalWebSoubor('soubory/ui/bundle.js') ?>"></script>
