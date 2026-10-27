<?php

declare(strict_types=1);

use Gamecon\Aktivita\Korektura\Exceptions\KorekturuNelzeVyridit;
use Gamecon\Aktivita\Korektura\KorekturyTextu;
use Gamecon\Aktivita\Korektura\RozdilTextu;
use Gamecon\Aktivita\Korektura\TextyAktivity;
use Gamecon\XTemplate\XTemplate;

/**
 * Korektura textů aktivit přes AI chat: texty se stáhnou po balících, odpověď AI se nahraje zpět
 * a korektor každý návrh přijme, upraví, nebo zamítne.
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
$naStrankuNavrhu = 20;
$korektury = new KorekturyTextu($systemoveNastaveni);

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
    $baliky = $korektury->exportuj($velikostBaliku, $u->id());
    if ($baliky === []) {
        oznameni('Není co exportovat, všechny texty jsou opravené nebo už na korektuře.');
    }
    oznameni(count($baliky) === 1
        ? "Připraven balík {$baliky[0]}."
        : 'Připraveny balíky ' . reset($baliky) . '–' . end($baliky) . '.');
}

if (post('zrusitBalik')) {
    $pocet = $korektury->zrusBalik((int) post('balik'));
    oznameni("Balík zrušen, jeho aktivity se vrátily mezi neopravené (počet: {$pocet}).");
}

if (post('importovat')) {
    $odpoved = (string) post('odpoved');
    $soubor = postFile('souborOdpovedi');
    if ($soubor !== '' && is_uploaded_file($soubor)) {
        $odpoved .= "\n" . file_get_contents($soubor);
    }
    if (trim($odpoved) === '') {
        chyba('Vlož odpověď AI do pole, nebo vyber soubor s ní.');
    }
    $vysledek = $korektury->importuj($odpoved);
    $_SESSION[$klicImportu] = [
        'pocetNavrhu'  => $vysledek->pocetNavrhu,
        'pocetBezZmen' => $vysledek->pocetBezZmen,
        'varovani'     => $vysledek->varovani,
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

$t = new XTemplate(__DIR__ . '/korektury.xtpl');
$t->assign([
    'urlModulu'      => $urlModulu,
    'velikostBaliku' => KorekturyTextu::VYCHOZI_VELIKOST_BALIKU,
    'pocetKExportu'  => $korektury->pocetKExportu(),
]);

$cekajiciBaliky = $korektury->dejCekajiciBaliky();
foreach ($cekajiciBaliky as $balik => $pocetAktivit) {
    $t->assign([
        'balik'        => $balik,
        'pocetAktivit' => $pocetAktivit,
    ]);
    $t->parse('korektury.baliky.balik');
}
if ($cekajiciBaliky !== []) {
    $t->assign('pocetCekajicich', array_sum($cekajiciBaliky));
    $t->parse('korektury.baliky');
}

$vysledekImportu = $_SESSION[$klicImportu] ?? null;
unset($_SESSION[$klicImportu]);
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
