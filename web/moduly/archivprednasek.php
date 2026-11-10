<?php

use Gamecon\Aktivita\TypAktivity;
use Gamecon\ArchivPrednasek\ArchivPrednasekStranka;
use Gamecon\ArchivPrednasek\PrednaskaNaWebu;
use Gamecon\ArchivPrednasek\PrednaskyNaWebu;
use Gamecon\ArchivPrednasek\StitekNaWebu;
use Gamecon\ArchivPrednasek\YoutubeOdkaz;

/**
 * Archive of lectures of past years (/prednasky/archiv), docs/generated/archivprednasek.md.
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
    ->nazev('Archiv přednášek')
    ->popis('Přednášky z minulých ročníků GameConu, u mnohých i se záznamem.');

$prednasky = (new PrednaskyNaWebu($systemoveNastaveni->rocnik(), $liniePrednasek))->prednasky();
$e = static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES);
$cestaArchivu = ArchivPrednasekStranka::cesta($liniePrednasek);

$t->assign([
    'urlLinie'  => $liniePrednasek->url(),
    'pocet'     => count($prednasky),
]);

// data for the filter, one item per lecture
$prednaskyProFiltr = [];
$prednasejiciProFiltr = [];
$stitkyProFiltr = [];

$podleRoku = [];
foreach ($prednasky as $prednaska) {
    $podleRoku[$prednaska->rok][] = $prednaska;
}

foreach ($podleRoku as $rok => $prednaskyRoku) {
    /** @var PrednaskaNaWebu $prednaska */
    foreach ($prednaskyRoku as $prednaska) {
        $t->assign([
            'htmlId' => $prednaska->htmlId(),
            'id'     => $prednaska->id,
            'nazev'  => $e($prednaska->nazev),
            'rok'    => $prednaska->rok,
        ]);

        if ($prednaska->urlRoku !== null) {
            $t->assign('urlRoku', $e($prednaska->urlRoku));
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
                // without JavaScript the name is a link to the filtered archive
                'urlFiltruPrednasejiciho' => $e($cestaArchivu . '?prednasejici=' . $prednasejici->id),
            ]);
            if ($prednasejici->urlMedailonku !== null) {
                $t->assign('urlMedailonku', $e($prednasejici->urlMedailonku));
                $t->parse('archivprednasek.rok.prednaska.prednasejici.osoba.medailonek');
            }
            $t->parse('archivprednasek.rok.prednaska.prednasejici.osoba');
            $prednasejiciProFiltr[$prednasejici->id] ??= $prednasejici->jmeno;
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
                'popisekDlouheAnotace' => $prednaska->kratkaAnotace !== null
                    ? 'Celá anotace'
                    : 'Anotace',
            ]);
            $t->parse('archivprednasek.rok.prednaska.dlouhaAnotace');
        }

        if ($prednaska->youtubeId !== null) {
            $t->assign([
                'urlVidea'      => $e(YoutubeOdkaz::urlVidea($prednaska->youtubeId)),
                'urlNahledu'    => $e(YoutubeOdkaz::urlNahledu($prednaska->youtubeId)),
                'urlPrehravace' => $e(YoutubeOdkaz::urlPrehravace($prednaska->youtubeId)),
            ]);
            $t->parse('archivprednasek.rok.prednaska.video');
        }

        $t->parse('archivprednasek.rok.prednaska');

        $prednaskyProFiltr[] = [
            'htmlId'       => $prednaska->htmlId(),
            'id'           => $prednaska->id,
            'rok'          => $prednaska->rok,
            'prednasejici' => array_map(static fn ($prednasejici) => $prednasejici->id, $prednaska->prednasejici),
            'stitky'       => array_map(static fn (StitekNaWebu $stitek) => $stitek->klic, $prednaska->stitky),
            'zaznam'       => $prednaska->youtubeId !== null,
        ];
    }
    $t->assign('rok', $rok);
    $t->parse('archivprednasek.rok');
}
if ($prednasky === []) {
    $t->parse('archivprednasek.zadne');
}

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
        static fn (StitekNaWebu $stitek) => ['klic' => $stitek->klic, 'nazev' => $stitek->nazev, 'skupina' => $stitek->skupina],
        array_values([...$klicovaSlova, ...$stitkyProgramu]),
    ),
];


?>
<link rel="stylesheet" href="<?= 'soubory/ui/style.css?version=' . md5_file(WWW . '/soubory/ui/style.css') ?>">
<script>
    window.gameconPřednačtení = <?= json_encode(
        ['archivPrednasek' => $filtrArchivu],
        // HEX_TAG, ať názvy nemůžou ukončit <script>
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG,
    ) ?>;
</script>
<script type="module" src="<?= 'soubory/ui/bundle.js?version=' . md5_file(WWW . '/soubory/ui/bundle.js') ?>"></script>
