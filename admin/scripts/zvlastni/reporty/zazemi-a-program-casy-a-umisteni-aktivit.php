<?php
require __DIR__ . '/sdilene-hlavicky.php';

$report = Report::zSql(<<<SQL
SELECT akce_seznam.nazev_akce,
       akce_lokace.nazev AS mistnost,
       akce_seznam.zacatek,
       akce_seznam.konec
FROM akce_seznam
LEFT JOIN akce_lokace ON akce_lokace.id_lokace = akce_seznam.lokace
WHERE akce_seznam.rok = $1
ORDER BY akce_seznam.zacatek
SQL
  , [ROK]
);
$report->tFormat(get('format'));
