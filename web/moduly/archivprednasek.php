<?php

use Gamecon\Aktivita\TypAktivity;
use Gamecon\ArchivPrednasek\ArchivPrednasekStranka;
use Gamecon\ArchivPrednasek\PrednaskaNaWebu;
use Gamecon\ArchivPrednasek\PrednaskyNaWebu;
use Gamecon\ArchivPrednasek\StitekNaWebu;
use Gamecon\ArchivPrednasek\YoutubeOdkaz;
use Gamecon\Jazyk\AktualniJazyk;

/**
 * Archive of lectures of past years (/prednasky/archiv), docs/generated/archiv-prednasek.md.
 * Cards are put out here, the filter (ui/src/pages/archivPrednasek) only hides them.
 *
 * @var Modul $this
 * @var \Gamecon\XTemplate\XTemplate $t
 * @var Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni
 */

// only under the line of lectures, the router in web/index.php gives it; the bare /archivprednasek is not a page
$liniePrednasek = $this->param('typ');
if (! $liniePrednasek instanceof TypAktivity) {
    throw new Nenalezeno();
}

$this->blackarrowStyl(true);
$this->info()
    ->nazev(t('aktivity.archiv_prednasek'))
    ->popis(t('linie.archiv_prednasek.popis'));

$prednasky = (new PrednaskyNaWebu($systemoveNastaveni->rocnik(), $liniePrednasek))->getPrednasky();
$e = static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES);
$cestaArchivu = ArchivPrednasekStranka::getCesta($liniePrednasek);

// without JavaScript the name of a speaker links to ?prednasejici=ID (the parameter of the filter, ids separated by
// commas): the cards of other speakers are hidden here, as the filter would hide them; unknown ids are dropped, as
// the filter drops them
$jmenaPrednasejicich = [];
foreach ($prednasky as $prednaska) {
    foreach ($prednaska->prednasejici as $prednasejici) {
        $jmenaPrednasejicich[$prednasejici->id] ??= $prednasejici->jmeno;
    }
}
$vybraniPrednasejici = array_values(array_intersect(
    array_map('intval', explode(',', (string) get('prednasejici'))),
    array_keys($jmenaPrednasejicich),
));
$jeVybrana = static fn (PrednaskaNaWebu $prednaska): bool => $vybraniPrednasejici === []
    || array_intersect(array_map(static fn ($prednasejici) => $prednasejici->id, $prednaska->prednasejici), $vybraniPrednasejici) !== [];

// texts of the interface are plain text from t(), escaped into HTML; numbers go in as strings, ICU would group digits
$t->assign([
    'urlLinie'     => $liniePrednasek->url(),
    'cestaArchivu' => $e($cestaArchivu),
]);
if ($vybraniPrednasejici !== []) {
    $t->assign('vybraniPrednasejici', $e(t('linie.archiv_prednasek.vybrani', [
        'prednasejici' => implode(', ', array_map(static fn (int $id) => $jmenaPrednasejicich[$id], $vybraniPrednasejici)),
        'pocet'        => (string) count(array_filter($prednasky, $jeVybrana)),
    ])));
    $t->parse('archivprednasek.vybraniPrednasejici');
} else {
    $t->assign('pocet', $e(t('linie.archiv_prednasek.pocet', ['pocet' => (string) count($prednasky)])));
    $t->parse('archivprednasek.pocet');
}

// data for the filter, one item per lecture
$prednaskyProFiltr = [];
$stitkyProFiltr = [];

$podleRoku = [];
foreach ($prednasky as $prednaska) {
    $podleRoku[$prednaska->rok][] = $prednaska;
}

foreach ($podleRoku as $rok => $prednaskyRoku) {
    $vybranychVRoce = 0;
    /** @var PrednaskaNaWebu $prednaska */
    foreach ($prednaskyRoku as $prednaska) {
        $vybrana = $jeVybrana($prednaska);
        $vybranychVRoce += (int) $vybrana;
        $t->assign([
            'htmlId'  => $prednaska->getHtmlId(),
            'id'      => $prednaska->id,
            'nazev'   => $e($prednaska->nazev),
            'rok'     => $prednaska->rok,
            'skryta'  => $vybrana ? '' : ' hidden',
        ]);

        if ($prednaska->urlRoku !== null) {
            $t->assign([
                'urlRoku'     => $e($prednaska->urlRoku),
                'titulekRoku' => $e(t('linie.archiv_prednasek.prednasky_rocniku', ['rok' => (string) $prednaska->rok])),
            ]);
            $t->parse('archivprednasek.rok.prednaska.rokOdkaz');
        } else {
            $t->parse('archivprednasek.rok.prednaska.rokText');
        }
        if ($prednaska->urlProgramu !== null) {
            $t->assign('urlProgramu', $e($prednaska->urlProgramu));
            $t->parse('archivprednasek.rok.prednaska.program');
        }

        foreach ($prednaska->prednasejici as $prednasejici) {
            $t->assign([
                'idPrednasejiciho'    => $prednasejici->id,
                'jmenoPrednasejiciho' => $e($prednasejici->jmeno),
                'titulekPrednasejiciho' => $e(t('linie.archiv_prednasek.prednasky_prednasejiciho', ['jmeno' => $prednasejici->jmeno])),
                'titulekMedailonku'     => $e(t('linie.archiv_prednasek.medailonek', ['jmeno' => $prednasejici->jmeno])),
                // without JavaScript the name is a link to the archive filtered here, with it a click adds him to the filter
                'urlFiltruPrednasejiciho' => $e($cestaArchivu . '?prednasejici=' . $prednasejici->id),
            ]);
            if ($prednasejici->urlMedailonku !== null) {
                $t->assign('urlMedailonku', $e($prednasejici->urlMedailonku));
                $t->parse('archivprednasek.rok.prednaska.prednasejici.osoba.medailonek');
            }
            $t->parse('archivprednasek.rok.prednaska.prednasejici.osoba');
        }
        if ($prednaska->prednasejici !== []) {
            $t->parse('archivprednasek.rok.prednaska.prednasejici');
        }

        foreach ($prednaska->stitky as $stitek) {
            $t->assign('stitek', $e($stitek->nazev));
            $t->parse('archivprednasek.rok.prednaska.stitek');
            $stitkyProFiltr[$stitek->klic] ??= $stitek;
        }
        if ($prednaska->youtubeId === null) {
            $t->parse('archivprednasek.rok.prednaska.bezZaznamu');
        }

        // HTML of the program, printed as the line page prints it
        if ($prednaska->kratkaAnotace !== null) {
            $t->assign('kratkaAnotace', $prednaska->kratkaAnotace);
            $t->parse('archivprednasek.rok.prednaska.kratkaAnotace');
        }
        if ($prednaska->dlouhaAnotace !== null) {
            $t->assign([
                'dlouhaAnotace'       => $prednaska->dlouhaAnotace,
                'popisekDlouheAnotace' => $e($prednaska->kratkaAnotace !== null
                    ? t('linie.archiv_prednasek.cela_anotace')
                    : t('linie.archiv_prednasek.anotace')),
            ]);
            $t->parse('archivprednasek.rok.prednaska.dlouhaAnotace');
        }

        if ($prednaska->youtubeId !== null) {
            $t->assign([
                'urlVidea'      => $e(YoutubeOdkaz::getUrlVidea($prednaska->youtubeId)),
                'urlNahledu'    => $e(YoutubeOdkaz::getUrlNahledu($prednaska->youtubeId)),
                'urlPrehravace' => $e(YoutubeOdkaz::getUrlPrehravace($prednaska->youtubeId)),
                'prehratZaznam' => $e(t('linie.archiv_prednasek.prehrat_zaznam', ['nazev' => $prednaska->nazev])),
            ]);
            $t->parse('archivprednasek.rok.prednaska.video');
        }

        $t->parse('archivprednasek.rok.prednaska');

        $prednaskyProFiltr[] = [
            'htmlId'       => $prednaska->getHtmlId(),
            'id'           => $prednaska->id,
            'rok'          => $prednaska->rok,
            'prednasejici' => array_map(static fn ($prednasejici) => $prednasejici->id, $prednaska->prednasejici),
            'stitky'       => array_map(static fn (StitekNaWebu $stitek) => $stitek->klic, $prednaska->stitky),
            'zaznam'       => $prednaska->youtubeId !== null,
        ];
    }
    $t->assign([
        'rok'       => $rok,
        'skrytyRok' => $vybranychVRoce > 0 ? '' : ' hidden',
    ]);
    $t->parse('archivprednasek.rok');
}
if ($prednasky === []) {
    $t->parse('archivprednasek.zadne');
}

$prednasejiciProFiltr = $jmenaPrednasejicich;
$collator = new Collator('cs');
$collator->asort($prednasejiciProFiltr);
// keywords of the archive first by alphabet, then tags of the program in the order of their categories
$klicovaSlova = array_filter($stitkyProFiltr, static fn (StitekNaWebu $stitek) => $stitek->skupina === StitekNaWebu::SKUPINA_KLICOVA_SLOVA);
uasort($klicovaSlova, static fn (StitekNaWebu $a, StitekNaWebu $b) => $collator->compare($a->nazev, $b->nazev));
$stitkyProgramu = array_diff_key($stitkyProFiltr, $klicovaSlova);

$filtrArchivu = [
    'prednasky'    => $prednaskyProFiltr,
    'roky'         => array_keys($podleRoku),
    'prednasejici' => array_map(
        static fn (int $id, string $jmeno) => ['id' => $id, 'jmeno' => $jmeno],
        array_keys($prednasejiciProFiltr),
        $prednasejiciProFiltr,
    ),
    'stitky'       => array_map(
        static fn (StitekNaWebu $stitek) => [
            'klic'    => $stitek->klic,
            'nazev'   => $stitek->nazev,
            // the filter shows the group as a heading; a category of the program is already in the language of the page
            'skupina' => $stitek->skupina === StitekNaWebu::SKUPINA_KLICOVA_SLOVA
                ? t('linie.archiv_prednasek.klicova_slova')
                : $stitek->skupina,
        ],
        array_values([...$klicovaSlova, ...$stitkyProgramu]),
    ),
];


?>
<link rel="stylesheet" href="<?= zabalWebSoubor('soubory/ui/style.css') ?>">
<script>
    // the language of the page for the texts of the filter (ui/src/i18n), the other constants keep their defaults
    window.GAMECON_KONSTANTY = <?= json_encode(['JAZYK' => AktualniJazyk::jazyk()->value]) ?>;
    window.gameconPřednačtení = <?= json_encode(
        ['archivPrednasek' => $filtrArchivu],
        // HEX_TAG, ať názvy nemůžou ukončit <script>
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG,
    ) ?>;
</script>
<script type="module" src="<?= zabalWebSoubor('soubory/ui/bundle.js') ?>"></script>
