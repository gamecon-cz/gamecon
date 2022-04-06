<?php
require __DIR__ . '/sdilene-hlavicky.php';

$report = Report::zSql(<<<SQL
SELECT
  provedl.id_uzivatele,
  provedl.jmeno_uzivatele,
  provedl.prijmeni_uzivatele,
  prijemce.id_uzivatele,
  prijemce.jmeno_uzivatele,
  prijemce.prijmeni_uzivatele,
  platby.castka,
  DATE(platby.provedeno) AS datum,
  TIME(platby.provedeno) AS cas,
  platby.poznamka
FROM platby
LEFT JOIN uzivatele_hodnoty provedl ON provedl.id_uzivatele = platby.provedl
LEFT JOIN uzivatele_hodnoty prijemce ON prijemce.id_uzivatele = platby.id_uzivatele
WHERE rok = $1 AND platby.provedl != 1
ORDER BY platby.id
SQL
  , [ROK]
);
$report->tFormat(get('format'));
