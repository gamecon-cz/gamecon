<?php

use Gamecon\Admin\Modules\Aktivity\Export\ExportAktivitSloupce;

return (static function (): string {
    $template = new XTemplate(basename(__DIR__ . '/_export-import-hint.xtpl'));

    $template->assign('sloupecIdAktivity', ExportAktivitSloupce::ID_AKTIVITY);
    $template->assign('sloupecNazevAktivity', ExportAktivitSloupce::NAZEV);
    $template->assign('sloupecUrlAktivity', ExportAktivitSloupce::URL);
    $template->assign('sloupecTagy', ExportAktivitSloupce::TAGY);
    $template->assign('sloupecVypraveci', ExportAktivitSloupce::VYPRAVECI);

    $template->parse('exportImportHint');

    return $template->text('exportImportHint');
})();
