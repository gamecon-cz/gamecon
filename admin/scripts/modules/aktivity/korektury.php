<?php

declare(strict_types=1);

use Gamecon\Ai\Exceptions\ChybaApiAi;
use Gamecon\Ai\PoskytovatelAi;
use Gamecon\Ai\PripojeniAi;
use Gamecon\Aktivita\Korektura\Exceptions\ExportUzBezi;
use Gamecon\Aktivita\Korektura\Exceptions\KorekturuNelzeVyridit;
use Gamecon\Aktivita\Korektura\Exceptions\OdesilaniAiUzBezi;
use Gamecon\Aktivita\Korektura\KorekturyTextu;
use Gamecon\Aktivita\Korektura\NahraneOdpovedi;
use Gamecon\Aktivita\Korektura\RozdilTextu;
use Gamecon\Aktivita\Korektura\TextyAktivity;
use Gamecon\Pravo;
use Gamecon\XTemplate\XTemplate;

/**
 * Korektura textů aktivit pomocí AI: balík textů se stáhne do AI chatu a odpověď se nahraje zpět, nebo jde AI
 * přes API, když ho vývojář zapne v Dev → Připojení AI. Korektor pak každý návrh přijme, upraví, nebo zamítne.
 *
 * nazev: Korektury textů
 * ikona: spell-check
 * pravo: 1034
 * submenu_group: 1
 * submenu_order: 4
 */

/**
 * @var Uzivatel                                      $u
 * @var Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni
 */
$urlModulu = 'aktivity/korektury';
$klicImportu = 'korektury_vysledek_importu';
$klicKontrolyAi = 'korektury_ai_zkontrolovano';
// each look at a batch is a request to the API, a click on Přijmout must not wait for it every time
$kontrolaAiNejvyseZaSekund = 60;
$naStrankuNavrhu = 20;
$pripojeniAi = new PripojeniAi();
$davkyAi = $pripojeniAi->dejPouzivaneDavky();
$korektury = new KorekturyTextu(
    $systemoveNastaveni,
    davkyAi: $davkyAi,
    // batches sent before a switch of the provider are read with its own token
    davkyDalsichPoskytovatelu: static fn (PoskytovatelAi $poskytovatel) => $pripojeniAi->dejDavky($poskytovatel),
);

if (get('stahnout') === 'zip') {
    $zip = $korektury->zipBaliku();
    if ($zip === null) {
        chyba('Žádný balík nečeká na odpověď AI, není co stáhnout.');
    }
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $korektury->nazevZipu() . '"');
    header('Content-Length: ' . strlen($zip));
    echo $zip;

    exit;
}

if (get('stahnout') !== null) {
    $balik = get('balik') !== null && get('balik') !== ''
        ? (int) get('balik')
        : null;
    $obsah = $korektury->soubor($balik);
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $korektury->nazevSouboru($balik) . '"');
    echo $obsah;

    // exit, not $BEZ_DEKORACE: the file goes out on its own, with nothing of the admin page around it
    exit;
}

if (post('exportovat')) {
    $velikostBaliku = max(1, min(200, (int) post('velikostBaliku') ?: KorekturyTextu::VYCHOZI_VELIKOST_BALIKU));
    try {
        $baliky = $korektury->exportuj($velikostBaliku, $u->id());
    } catch (ExportUzBezi $exportUzBezi) {
        chyba(htmlspecialchars($exportUzBezi->getMessage(), ENT_QUOTES));
    }
    if ($baliky === []) {
        oznameni('Není co exportovat, všechny texty jsou opravené nebo už na korektuře.');
    }
    oznameni(count($baliky) === 1
        ? "Připraven balík {$baliky[0]}."
        : 'Připraveny balíky ' . reset($baliky) . '–' . end($baliky) . '.');
}

if (post('poslatAi') && $korektury->jeAiPripojena()) {
    $balik = post('balik') !== null && post('balik') !== ''
        ? (int) post('balik')
        : null;
    try {
        $pocet = $korektury->posliAi($balik);
        oznameni($pocet === 0
            ? 'Není co poslat, všechny texty už u AI jsou.'
            : "Posláno AI, aktivit: {$pocet}. Odpovědi bývají do hodiny, nejpozději do 24 hodin. Stránka si je načte, až ji otevřeš.", false);
    } catch (ChybaApiAi $chybaApiAi) {
        chyba('AI texty nepřijala. ' . htmlspecialchars($chybaApiAi->getMessage(), ENT_QUOTES), false);
    } catch (OdesilaniAiUzBezi $odesilaniAiUzBezi) {
        chyba(htmlspecialchars($odesilaniAiUzBezi->getMessage(), ENT_QUOTES), false);
    }
    back(URL_ADMIN . '/' . $urlModulu);
}

if (post('zrusitBalik')) {
    $pocet = $korektury->zrusBalik((int) post('balik'));
    oznameni("Balík zrušen, jeho aktivity se vrátily mezi neopravené (počet: {$pocet}).");
}

// PHP drops a POST over post_max_size whole, the page would look as if nothing was sent
if ($_POST === [] && $_FILES === [] && $_SERVER['REQUEST_METHOD'] === 'POST' && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    chyba('Odeslané soubory jsou dohromady větší, než server přijme (' . ini_get('post_max_size') . '), nahraj je po menších částech.');
}

if (post('importovat')) {
    // several files at once, an answer of each chat in its own, each parsed on its own (KorekturyTextu::importujOdpovedi())
    $nahrane = NahraneOdpovedi::zFormulare($_FILES['souboryOdpovedi'] ?? null, (int) ini_get('max_file_uploads'));
    $odpovedi = $nahrane->odpovediPodleSouboru;
    if (trim((string) post('odpoved')) !== '') {
        $odpovedi = [
            'vložená odpověď' => (string) post('odpoved'),
        ] + $odpovedi;
    }
    if ($odpovedi === [] && $nahrane->varovani === []) {
        chyba('Vlož odpověď AI do pole, nebo vyber soubory s ní.');
    }
    $vysledek = $korektury->importujOdpovedi($odpovedi);
    $_SESSION[$klicImportu] = [
        'pocetNavrhu'  => $vysledek->pocetNavrhu,
        'pocetBezZmen' => $vysledek->pocetBezZmen,
        'varovani'     => [...$nahrane->varovani, ...$vysledek->varovani],
    ];
    back(URL_ADMIN . '/' . $urlModulu . '#import');
}

if (post('prijmoutBezZmen')) {
    $pocet = $korektury->prijmiVsechnyBezZmen($u->id());
    oznameni("Označeno jako opravené, počet aktivit: {$pocet}.");
}

if (post('prijmout') || post('zamitnout')) {
    try {
        if (post('prijmout')) {
            $korektury->prijmi(
                (int) post('idKorektury'),
                new TextyAktivity((string) post('nazev'), (string) post('popisKratky'), (string) post('popis')),
                $u->id(),
            );
            oznameni('Texty uloženy a aktivita označena jako opravená.', false);
        } else {
            $korektury->zamitni((int) post('idKorektury'), $u->id());
            oznameni('Návrh zamítnut, aktivita zůstává neopravená.', false);
        }
    } catch (KorekturuNelzeVyridit $korekturuNelzeVyridit) {
        chyba(htmlspecialchars($korekturuNelzeVyridit->getMessage(), ENT_QUOTES), false);
    }
    back(URL_ADMIN . '/' . $urlModulu . '#schvalovani');
}

// answers of the AI batches that have ended, read on a visit of the page at most once a minute
$vysledekAi = null;
$chybyAi = [];
if ($korektury->jeAiPripojena()
    && time() - (int) ($_SESSION[$klicKontrolyAi] ?? 0) >= $kontrolaAiNejvyseZaSekund
) {
    $_SESSION[$klicKontrolyAi] = time();
    $nacteniAi = $korektury->nactiOdpovediAi();
    $vysledekAi = $nacteniAi->vysledekImportu;
    $chybyAi = $nacteniAi->chyby;
}

$t = new XTemplate(__DIR__ . '/korektury.xtpl');
$t->assign([
    'urlModulu'      => $urlModulu,
    'velikostBaliku' => KorekturyTextu::VYCHOZI_VELIKOST_BALIKU,
    'pocetKExportu'  => $korektury->pocetKExportu(),
    'poskytovatelAi' => $davkyAi?->poskytovatel()->nazev() ?? '',
    'nejvysSouboru'  => (int) ini_get('max_file_uploads'),
]);
if ($korektury->jeAiPripojena()) {
    $t->parse('korektury.krokApi');
}

$cekajiciBaliky = $korektury->dejCekajiciBaliky();
$neposlanoAi = 0;
foreach ($cekajiciBaliky as $cekajiciBalik) {
    $t->assign([
        'balik'        => $cekajiciBalik->balik,
        'pocetAktivit' => $cekajiciBalik->pocetAktivit,
    ]);
    if ($korektury->jeAiPripojena()) {
        if ($cekajiciBalik->pocetUAi > 0) {
            $t->assign([
                'pocetUAi'   => $cekajiciBalik->pocetUAi,
                'odeslanoAi' => $cekajiciBalik->odeslanoAiKdy?->format('j. n. H:i'),
            ]);
            $t->parse('korektury.baliky.balik.ai.uAi');
        }
        if ($cekajiciBalik->maTextyKOdeslaniAi()) {
            $t->parse('korektury.baliky.balik.ai.poslat');
        }
        $t->parse('korektury.baliky.balik.ai');
    }
    $neposlanoAi += $cekajiciBalik->pocetAktivit - $cekajiciBalik->pocetUAi;
    $t->parse('korektury.baliky.balik');
}
if ($cekajiciBaliky !== []) {
    $t->assign('pocetCekajicich', array_sum(array_map(
        static fn ($cekajiciBalik) => $cekajiciBalik->pocetAktivit,
        $cekajiciBaliky,
    )));
    if ($korektury->jeAiPripojena()) {
        $t->parse('korektury.baliky.hlavickaAi');
        if ($neposlanoAi > 0 && count($cekajiciBaliky) > 1) {
            $t->assign('neposlanoAi', $neposlanoAi);
            $t->parse('korektury.baliky.poslatVse');
        }
    }
    $t->parse('korektury.baliky');
}
if (! $korektury->jeAiPripojena()) {
    if ($u->maPravo(Pravo::ADMINISTRACE_DEV)) {
        $t->parse('korektury.bezAi.zapnout');
    }
    $t->parse('korektury.bezAi');
}
foreach ($chybyAi as $chybaAi) {
    $t->assign('chybaAi', htmlspecialchars($chybaAi, ENT_QUOTES));
    $t->parse('korektury.chybaAi');
}

$vysledekImportu = $_SESSION[$klicImportu] ?? null;
unset($_SESSION[$klicImportu]);
if ($vysledekAi !== null) {
    // an answer pasted by hand and one from the API can come in the same visit
    $vysledekImportu = [
        'pocetNavrhu'  => ($vysledekImportu['pocetNavrhu'] ?? 0) + $vysledekAi->pocetNavrhu,
        'pocetBezZmen' => ($vysledekImportu['pocetBezZmen'] ?? 0) + $vysledekAi->pocetBezZmen,
        'varovani'     => [...$vysledekImportu['varovani'] ?? [], ...$vysledekAi->varovani],
    ];
    $t->parse('korektury.vysledekImportu.zApi');
}
if ($vysledekImportu !== null) {
    $t->assign([
        'pocetNavrhu'  => $vysledekImportu['pocetNavrhu'],
        'pocetBezZmen' => $vysledekImportu['pocetBezZmen'],
    ]);
    foreach ($vysledekImportu['varovani'] as $varovani) {
        $t->assign('varovani', htmlspecialchars($varovani, ENT_QUOTES));
        $t->parse('korektury.vysledekImportu.varovani.polozka');
    }
    if ($vysledekImportu['varovani'] !== []) {
        $t->parse('korektury.vysledekImportu.varovani');
    }
    $t->parse('korektury.vysledekImportu');
}

$pocetKeSchvaleni = $korektury->pocetKeSchvaleni();
$keSchvaleni = $korektury->dejKeSchvaleni($naStrankuNavrhu);
$aktualniTexty = $korektury->dejAktualniTexty(array_map(
    static fn ($korektura) => $korektura->idAktivity,
    $keSchvaleni,
));
$t->assign([
    'pocetKeSchvaleni' => $pocetKeSchvaleni,
    'zobrazeno'        => $pocetKeSchvaleni > count($keSchvaleni)
        ? ', zobrazeno prvních ' . count($keSchvaleni) . ', po vyřízení se načtou další'
        : '',
]);
if ($pocetKeSchvaleni > 0) {
    $t->parse('korektury.prijmoutBezZmen');
}

$nazvyPoli = [
    'nazev'       => 'Název',
    'popisKratky' => 'Krátký popis',
    'popis'       => 'Popis',
];
foreach ($keSchvaleni as $korektura) {
    $navrh = $korektura->navrh();
    $t->assign([
        'idKorektury'      => $korektura->id,
        'idAktivity'       => $korektura->idAktivity,
        'balik'            => $korektura->balik,
        'nazevAktivity'    => htmlspecialchars($korektura->puvodni->nazev, ENT_QUOTES),
        'zmeny'            => htmlspecialchars((string) $korektura->zmeny, ENT_QUOTES),
        'navrhNazev'       => htmlspecialchars($navrh->nazev, ENT_QUOTES),
        'navrhPopisKratky' => htmlspecialchars($navrh->popisKratky, ENT_QUOTES),
        'navrhPopis'       => htmlspecialchars($navrh->popis, ENT_QUOTES),
    ]);

    $branicPrijeti = $korektury->varovaniBranicPrijeti($aktualniTexty[$korektura->idAktivity] ?? null, $korektura);
    $varovani = $branicPrijeti !== []
        ? $branicPrijeti
        : $korektury->varovaniKeKontrole($korektura);
    foreach ($varovani as $jednoVarovani) {
        $t->assign('varovani', htmlspecialchars($jednoVarovani, ENT_QUOTES));
        $t->parse($branicPrijeti !== []
            ? 'korektury.navrh.branicPrijeti'
            : 'korektury.navrh.kontrola');
    }

    if ($korektura->zmeny !== null) {
        $t->parse('korektury.navrh.zmeny');
    }
    if (! $korektura->maNavrzeneZmeny()) {
        $t->parse('korektury.navrh.bezNavrhu');
    }
    foreach ($nazvyPoli as $pole => $nazevPole) {
        if ($navrh->{$pole} === $korektura->puvodni->{$pole}) {
            continue;
        }
        $t->assign([
            'nazevPole' => $nazevPole,
            'rozdil'    => RozdilTextu::html(
                TextyAktivity::normalizuj($korektura->puvodni->{$pole}),
                TextyAktivity::normalizuj($navrh->{$pole}),
            ),
        ]);
        $t->parse('korektury.navrh.pole');
    }

    if ($branicPrijeti === []) {
        $t->parse('korektury.navrh.prijmout');
    }
    $t->parse('korektury.navrh');
}
if ($keSchvaleni === []) {
    $t->parse('korektury.zadneNavrhy');
}

$t->parse('korektury');
$t->out('korektury');
