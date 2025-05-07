<?php

use Gamecon\Admin\Modules\Aktivity\Import\Activities\ActivitiesImportLogger;
use Gamecon\Admin\Modules\Aktivity\Import\ImporterUcastnikuNaAktivitu;
use Gamecon\Cas\DateTimeCz;
use Gamecon\Web\Urls;
use Gamecon\XTemplate\XTemplate;

/** @var \Gamecon\XTemplate\XTemplate $template */
/** @var \Uzivatel $u */
/** @var Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */

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
        'chyby'            => $chyby,
    ] = $importerUcastniku->importFile($importFile, $u);

    if (empty($chyby)) {
        oznameni(
            sprintf(
                'Import proběhl úspěšně. Přihlášeno %d účastníků, odhlášeno %d účastníků.',
                $prihlasenoCelkem,
                $odhlasenoCelkem,
            ),
            false,
        );
    }
}

if (!empty($varovani) || !empty($chyby)) {
    $importOznameniTemplate = new XTemplate(__DIR__ . '/_import-oznameni.xtpl');
    if (!empty($varovani)) {
        foreach ($varovani as $zpravaVarovani) {
            $importOznameniTemplate->assign('message', $zpravaVarovani);
            $importOznameniTemplate->parse('oznameni.warnings.warning.message');
            $importOznameniTemplate->parse('oznameni.warnings.warning');
        }
        $importOznameniTemplate->parse('oznameni.warnings');
    }
    if (!empty($chyby)) {
        foreach ($chyby as $chyba) {
            $importOznameniTemplate->assign('message', $chyba);
            $importOznameniTemplate->parse('oznameni.errors.error.message');
            $importOznameniTemplate->parse('oznameni.errors.error');
        }
        $importOznameniTemplate->parse('oznameni.errors.stoppedHeader');
        $importOznameniTemplate->parse('oznameni.errors');
    }
    $importOznameniTemplate->parse('oznameni');
    $importOznameni = $importOznameniTemplate->text('oznameni');
    $template->assign('importOznameni', $importOznameni);
    $template->parse('import.oznameni');
}

$template->parse('import');
$template->out('import');
