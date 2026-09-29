<?php

use Gamecon\XTemplate\XTemplate;
use Gamecon\Shop\EshopImporter;

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

    $importTemplate->parse('import');
    $importTemplate->out('import');

    return;
}

$vstupniSoubor = $_FILES[$souborInputName]['tmp_name'] ?? '';

$importer = new EshopImporter($vstupniSoubor);
$vysledek = $importer->importuj();

oznameni("Import dokončen. Přidáno {$vysledek->pocetNovych} nových položek, upraveno {$vysledek->pocetZmenenych} stávajících, vyřazeno {$vysledek->pocetVyrazenych} starých.");
