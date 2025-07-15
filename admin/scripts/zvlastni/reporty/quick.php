<?php

require __DIR__ . '/sdilene-hlavicky.php';

$r = dbOneLine('SELECT * FROM reporty_quick WHERE id = $1', [get('id')]);
if ($r) {
    $sql = quickReportPlaceholderReplace($r['dotaz']);
    try {
        ob_start();
        $report            = Report::zSql($sql);
        $quickReportFormat = get('format');
        if ($quickReportFormat) {
            $BEZ_DEKORACE = true;
            $report->tFormat($quickReportFormat, $r['nazev']);
        } else {
            $report->tHtml(Report::BEZ_STYLU);
        }
        ob_end_flush();
    } catch (DbException $e) {
        ob_end_clean();
        echo 'chyba: ' . $e->getMessage();
    }
}
