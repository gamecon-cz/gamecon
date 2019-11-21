<?php
require_once __DIR__ . '/sdilene-hlavicky.php';

$report = Report::zSql(<<<SQL
SELECT akce_lokace.nazev AS mistnost, akce_lokace.dvere
FROM akce_lokace
ORDER BY akce_lokace.nazev
SQL
);

$report->tFormat(get('format'));
