<?php
require __DIR__ . '/sdilene-hlavicky.php';

$report = Report::zSql(<<<SQL
SELECT lokace.id_lokace, lokace.nazev, lokace.dvere,
       lokace.poznamka, lokace.poradi, lokace.rok
FROM lokace
ORDER BY lokace.id_lokace
SQL
);

$report->tFormat(get('format'));
