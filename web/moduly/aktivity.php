<?php

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\FiltrAktivity;
use Gamecon\Aktivita\Program;
use Gamecon\Aktivita\StavAktivity;
use Gamecon\Aktivita\Tag;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\KategorieTagu;

/** @var Modul $this */
/** @var Url $url */
/** @var \Gamecon\XTemplate\XTemplate $t */
/** @var Uzivatel|null $u */
/** @var Uzivatel|null|void $org */
/** @var Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */
/** @var null|\Gamecon\Aktivita\TypAktivity $typ */

$this->blackarrowStyl(true);
$this->pridejJsSoubor(__DIR__ . '/../soubory/blackarrow/_spolecne/zachovej-scroll.js');

if (!function_exists('zabalWebSoubor')) {
    function zabalWebSoubor(string $cestaKSouboru): string
    {
        return $cestaKSouboru . '?version=' . md5_file(WWW . '/' . $cestaKSouboru);
    }
}

$typ = $this->param('typ');

// zpracování POST požadavků

Aktivita::prihlasovatkoZpracuj($u, $u);

// ročník, dřívější jde vybrat v liště nad aktivitami (?rocnik=2024)

$letosniRocnik = $systemoveNastaveni->rocnik();
// ve kterých ročnících tu něco bylo, stejně jako filtr aktivit níž (FiltrAktivity::JEN_VIDITELNE a BEZ_DALSICH_KOL),
// jinak by šel vybrat ročník bez jediné karty (vypravěč, který vedl jen semifinále DrD)
$rocniky = array_map('intval', dbOneArray(<<<'SQL'
SELECT DISTINCT akce_seznam.rok
FROM akce_seznam
WHERE akce_seznam.rok <= $0
    AND akce_seznam.stav IN ($1)
    AND NOT (akce_seznam.typ IN ($2) AND akce_seznam.stav IN ($3))
    AND NOT (akce_seznam.typ IN ($6) AND akce_seznam.cena = 0)
    AND ($4 IS NULL OR akce_seznam.typ = $4)
    AND ($5 IS NULL OR akce_seznam.id_akce IN (
        SELECT akce_organizatori.id_akce FROM akce_organizatori WHERE akce_organizatori.id_uzivatele = $5
    ))
ORDER BY akce_seznam.rok DESC
SQL, [
    0 => $letosniRocnik,
    1 => StavAktivity::bezneViditelneStavy(),
    2 => TypAktivity::interniTypy(),
    3 => StavAktivity::probehnuteStavy(),
    4 => $typ?->id(),
    5 => !empty($org)
        ? $org->id()
        : null,
    6 => [TypAktivity::DRD, TypAktivity::LKD],
]));
// letošní i bez aktivit, ať se jde vrátit
$rocniky = array_values(array_unique(array_merge([$letosniRocnik], $rocniky)));

$rocnik = (int)get('rocnik');
if (!in_array($rocnik, $rocniky, true)) {
    $rocnik = $letosniRocnik;
}
$minulyRocnik = $rocnik !== $letosniRocnik;

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
$nekteraPrihlasovatelna = false;

foreach ($skupiny as $skupina) {
    $skupina = serazenePodle($skupina, 'zacatek');

    $dnyKarty       = [];
    $vypraveciKarty = [];
    $volnoNaKarte   = false;

    /** @var Aktivita $aktivita */
    foreach ($skupina as $aktivita) {
        // programový den jako v programu, noční hra po půlnoci patří k předchozímu dni (záměr), i když karta ukazuje skutečný den
        $den = $aktivita->denProgramu();
        if ($den) {
            $nazevDne               = $den->format('l');
            // transliterace je drahá a dnů je pár
            $klicDne                = $klicePodleNazvuDne[$nazevDne] ??= odstranDiakritiku($nazevDne);
            $dnyKarty[$klicDne]     = true;
            $dnyProFiltr[$klicDne] ??= ['datum' => $den->format('Y-m-d'), 'nazev' => $nazevDne];
        }
        // na minulý ročník se nepřihlašuje, i když v něm aktivita zůstala aktivovaná (prihlasovatelna() rok nehlídá)
        if (!$minulyRocnik) {
            $nekteraPrihlasovatelna = $nekteraPrihlasovatelna || $aktivita->prihlasovatelna();
            $volnoNaKarte           = $volnoNaKarte || $aktivita->isVolnoKPrihlaseni($u);
        }
        // u DrD a skupin má každý termín svého vypravěče, filtr kartu najde u kteréhokoli z nich
        foreach ($aktivita->organizatori() as $organizator) {
            $vypraveciKarty[$organizator->id()]    = true;
            $vypraveciProFiltr[$organizator->id()] ??= $organizator->jmenoNaWebu();
        }

        $vypravec = current($aktivita->organizatori());
        if ($vypravec && ($aktivita->typId() == TypAktivity::DRD || $aktivita->patriPod() > 0)) {
            $t->assign('vypravec', $vypravec->jmenoNaWebu());
            $t->parse('aktivity.aktivita.termin.vypravec');
        }

        if ($minulyRocnik) {
            $prihlasit = '';
        } elseif ($aktivita->tymova() && $u && $u->gcPrihlasen() && $aktivita->prihlasovatelna()) {
            $aktivitaId    = (int)$aktivita->id();
            $aktivitaNazev = addslashes($aktivita->nazev());
            $label         = $aktivita->prihlasen($u) ? 'Nastavení týmu' : 'Přihlásit tým';
            $prihlasit     = "<button onclick=\"window.preactMost.prihlaseniTymu.otevri({$aktivitaId}, '{$aktivitaNazev}')\">{$label}</button>";
        } else {
            $prihlasit = $aktivita->prihlasovatko($u);
        }

        $t->assign([
            'aktivita'   => $aktivita,
            'obsazenost' => $aktivita->obsazenost() ?: '',
            'prihlasit'  => $prihlasit,
        ]);

        $t->parse('aktivity.nahled.termin');
        $t->parse('aktivity.aktivita.termin');
    }

    $organizatori = implode(', ', array_map(static function (
        Uzivatel $organizator,
    ) {
        $maProfilovouStranku = $organizator->url() !== null;
        $jmenoNaWebu         = $organizator->jmenoNaWebu();

        return $maProfilovouStranku
            ? '<a href="' . $organizator->id() . '">' . $jmenoNaWebu . '</a>'
            // asi vypravěčská skupina nebo podobně
            : $jmenoNaWebu;
    }, $aktivita->organizatori()));

    $obrazek = $aktivita->obrazek();

    $t->assign([
        'aktivita'           => $aktivita,
        'htmlId'             => $aktivita->urlId(),
        // nelze použít prosté #htmlId, protože to rozbije base href a odkazuje to pak o úroveň výš
        'kotva'              => URL_WEBU . '/' . $url->cela()
            . ($minulyRocnik
                ? '?rocnik=' . $rocnik
                : '')
            . '#' . $aktivita->urlId(),
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

    $kartyProFiltr[] = [
        'htmlId'    => $aktivita->urlId(),
        'id'        => (int)$aktivita->id(),
        'tagy'      => $aktivita->tagyId(),
        'dny'       => array_keys($dnyKarty),
        'vypraveci' => array_keys($vypraveciKarty),
        'volno'     => $volnoNaKarte,
    ];
}

// u pár karet by filtry jen překážely
$filtrAktivit = null;
if (count($kartyProFiltr) >= 15) {
    $nazvyKategoriiTagu = [];
    foreach (KategorieTagu::zVsech() as $kategorieTagu) {
        $nazvyKategoriiTagu[$kategorieTagu->id()] = $kategorieTagu->nazev();
    }
    uasort($dnyProFiltr, static fn(
        array $den,
        array $jinyDen,
    ) => $den['datum'] <=> $jinyDen['datum']);
    (new Collator('cs'))->asort($vypraveciProFiltr);

    $filtrAktivit = [
        'karty'          => $kartyProFiltr,
        // seřazené podle pořadí kategorie a názvu, viz Tag::dotaz()
        'tagy'           => array_map(static fn(
            Tag $tag,
        ) => [
            'id'        => $tag->id(),
            'nazev'     => $tag->nazev(),
            'kategorie' => $nazvyKategoriiTagu[$tag->idKategorieTagu()] ?? '',
        ], Tag::zIds(array_values(array_unique(array_merge(...array_column($kartyProFiltr, 'tagy')))))),
        'dny'            => array_map(static fn(
            string $klic,
            array  $den,
        ) => ['klic' => $klic, 'nazev' => $den['nazev']], array_keys($dnyProFiltr), $dnyProFiltr),
        'vypraveci'      => array_map(static fn(
            int    $id,
            string $jmeno,
        ) => ['id' => $id, 'jmeno' => $jmeno], array_keys($vypraveciProFiltr), $vypraveciProFiltr),
        'lzePrihlasovat' => $nekteraPrihlasovatelna,
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
        'jmeno' => $org->jmenoNaWebu(),
        'popis' => $org->oSobe()
            ?: '<p><em>popisek od vypravěče nemáme</em></p>',
        'fotka' => $org->fotkaAuto()->kvalita(85)->pokryjOrez(180, 180),
    ]);
    // moderní vzhled: kontakt na šéfa linie na stránce vypravěče nedává smysl
    $t->assign('kontaktLinieAtributy', 'hidden');
    $t->parse('aktivity.hlavickaVypravec');
} elseif ($typ) {
    $this->info()->nazev(mb_ucfirst($typ->nazevDlouhy()) . $pripojkaRocniku);

    $hlavickaLinie = dbOneLine(
        'SELECT sekce, jmeno, email FROM akce_typy_hlavicky WHERE id_typu = $1',
        [$typ->id()],
    );

    $picture_path = URL_WEBU . '/' . cestaObrazkuLinie($typ->id());

    /* 'ikonaLiniePopis' => $varIkonaLiniePopis, */
    $t->assign([
        'popisLinie'      => $typ->oTypu(),
        'ikonaLinie'      => $picture_path,
        'ikonaLinieSekce' => !empty($hlavickaLinie['sekce']) ? $hlavickaLinie['sekce'] : 'Sekce',
        'ikonaLinieJmeno' => !empty($hlavickaLinie['jmeno']) ? $hlavickaLinie['jmeno'] : 'Jmeno "Prezdivka" Prijmeni',
        'ikonaLinieEmail' => !empty($hlavickaLinie['email']) ? $hlavickaLinie['email'] : 'info@gamecon.cz',
        'specTridy'       => $typ->id() == TypAktivity::DRD
            ? 'aktivity_aktivity-drd'
            : null,
    ]);

    // podstránky linie
    $stranky = serazenePodle($typ->stranky(), 'poradi');
    $t->parseEach($stranky, 'stranka', 'aktivity.stranka');

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
