<?php

use Gamecon\Admin\Modules\Aktivity\Export\ExportAktivitSloupce;
use Gamecon\Admin\Modules\Aktivity\Import\ActivitiesImporter;
use Gamecon\Admin\Modules\Aktivity\Import\ActivitiesImportLogger;
use Gamecon\Mutex\Mutex;
use Gamecon\Role\Role;
use Gamecon\Vyjimkovac\Logovac;
use Gamecon\Cas\DateTimeCz;
use Gamecon\Web\Urls;
use Gamecon\Admin\Modules\Aktivity\Import\ImporterUcastnikuNaAktivitu;

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
    ['prihlasenoCelkem' => $prihlasenoCelkem, 'odhlasenoCelkem' => $odhlasenoCelkem]
        = $importerUcastniku->importFile($importFile, $u);
    oznameni(
        sprintf(
            'Import proběhl úspěšně. Přihlášeno %d účastníků, odhlášeno %d účastníků.',
            $prihlasenoCelkem,
            $odhlasenoCelkem,
        ),
    );
}

$template->parse('import');
$template->out('import');
