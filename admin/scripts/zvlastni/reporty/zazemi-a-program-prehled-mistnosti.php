<?php
require __DIR__ . '/sdilene-hlavicky.php';

$report = Report::zSql(<<<SQL
SELECT akce_lokace.id_lokace, akce_lokace.nazev, akce_lokace.dvere, akce_lokace.poznamka, akce_lokace.poradi, akce_lokace.rok
FROM akce_lokace
ORDER BY akce_lokace.id_lokace
SQL
);

$report->tFormat(get('format'));
