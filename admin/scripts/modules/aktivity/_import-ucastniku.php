<?php

use Gamecon\Admin\Modules\Aktivity\Import\Activities\ActivitiesImportLogger;
use Gamecon\Admin\Modules\Aktivity\Import\ImporterUcastnikuNaAktivitu;
use Gamecon\Cas\DateTimeCz;
use Gamecon\Web\Urls;
use Gamecon\XTemplate\XTemplate;

/** @var \Gamecon\XTemplate\XTemplate $template */
/** @var \Uzivatel $u */
/** @var Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('output_buffering', '0');

$activitiesImportLogger = new ActivitiesImportLogger();
$now                    = new \DateTimeImmutable();
if (defined('TESTING') && TESTING && (int)$now->format('Y') !== $systemoveNastaveni->rocnik()) {
    $now = DateTimeImmutable::createFromFormat(DateTimeCz::FORMAT_DB, GC_BEZI_OD);
}
$urlNaEditaciAktivity = Urls::urlAdminDetailAktivity(null);

$importFile = postFile('import-ucastniku');
if (!empty($importFile)) {
    $importerUcastniku = new ImporterUcastnikuNaAktivitu($systemoveNastaveni);
    [
        'prihlasenoCelkem' => $prihlasenoCelkem,
        'odhlasenoCelkem'  => $odhlasenoCelkem,
        'varovani'         => $varovani,
    ] = $importerUcastniku->importFile($importFile, $u);
    oznameni(
        sprintf(
            'Import proběhl úspěšně. Přihlášeno %d účastníků, odhlášeno %d účastníků.',
            $prihlasenoCelkem,
            $odhlasenoCelkem,
        ),
        false,
    );
}

if (!empty($varovani)) {
    $importOznameniTemplate = new XTemplate(__DIR__ . '/_import-oznameni.xtpl');
    foreach ($varovani as $zpravaVarovani) {
        $importOznameniTemplate->assign('message', $zpravaVarovani);
        $importOznameniTemplate->parse('oznameni.warnings.warning.message');
        $importOznameniTemplate->parse('oznameni.warnings.warning');
    }
    $importOznameniTemplate->parse('oznameni.warnings');
    $importOznameniTemplate->parse('oznameni');
    $importOznameni = $importOznameniTemplate->text('oznameni');
    $template->assign('importOznameni', $importOznameni);
    $template->parse('import.oznameni');
}
$template->parse('import');
$template->out('import');
