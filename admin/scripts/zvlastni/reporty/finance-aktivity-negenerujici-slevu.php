<?php
require __DIR__ . '/sdilene-hlavicky.php';

$report = Report::zSql(<<<SQL
SELECT nazev_akce, zacatek
FROM akce_seznam
WHERE nedava_slevu = 1 AND rok = $1
SQL
  , [ROK]
);
$report->tFormat(get('format'));
