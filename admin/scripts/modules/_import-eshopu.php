<?php

use Gamecon\XTemplate\XTemplate;
use Gamecon\Shop\EshopImporter;
use Gamecon\Shop\LetosniPredmetyZdarma;

/** @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */

$postName        = 'importEshopu';
$souborInputName = 'eshopSoubor';

if (!post($postName)) {
    $importTemplate = new XTemplate(__DIR__ . '/_import-eshopu.xtpl');
    $importTemplate->assign(
        'eshopReport',
        basename(__DIR__ . '/../zvlastni/reporty/finance-report-eshop.php', '.php'),
    );
    $importTemplate->assign('postName', $postName);
    $importTemplate->assign('souborInputName', $souborInputName);
    $importTemplate->assign('baseUrl', URL_ADMIN);

    foreach ((new LetosniPredmetyZdarma($systemoveNastaveni->rocnik()))->stav() as $radek) {
        $importTemplate->assign([
            'pravidlo' => $radek['pravidlo'],
            'predmet'  => match (true) {
                $radek['nazevPredmetu'] !== null => "{$radek['nazevPredmetu']} ({$radek['kod']})",
                $radek['kod'] !== null           => "⚠️ „{$radek['kod']}“ v letošní nabídce není — nikdo ho nedostane zdarma",
                default                          => '⚠️ neurčeno — nikdo ho nedostane zdarma',
            },
        ]);
        $importTemplate->parse('import.letosni.radek');
    }
    $importTemplate->parse('import.letosni');

    $importTemplate->parse('import');
    $importTemplate->out('import');

    return;
}

$vstupniSoubor = $_FILES[$souborInputName]['tmp_name'] ?? '';

$importer = new EshopImporter($vstupniSoubor, $systemoveNastaveni->rocnik());
$vysledek = $importer->importuj();

$zprava = "Import dokončen. Přidáno {$vysledek->pocetNovych} nových položek, upraveno {$vysledek->pocetZmenenych} stávajících, vyřazeno {$vysledek->pocetVyrazenych} starých.";
if ($vysledek->varovani !== []) {
    varovani($zprava . ' ⚠️ ' . implode(' ', $vysledek->varovani));
}
oznameni($zprava);
