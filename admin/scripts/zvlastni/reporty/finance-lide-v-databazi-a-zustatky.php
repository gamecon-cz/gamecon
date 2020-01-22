<?php
require_once __DIR__ . '/sdilene-hlavicky.php';

$report = Report::zSql(<<<SQL
SELECT
  uzivatele_hodnoty.id_uzivatele,
  jmeno_uzivatele,
  prijmeni_uzivatele,
  mesto_uzivatele,
  ulice_a_cp_uzivatele,
  psc_uzivatele,
  zustatek,
  ucast.roky AS účast,
  pohyb.datum AS "poslední kladný pohyb na účtu"
FROM uzivatele_hodnoty
LEFT JOIN (
  SELECT
    id_uzivatele,
    GROUP_CONCAT(2000-(id_zidle DIV 100) ORDER BY id_zidle DESC) AS roky,
    COUNT(id_zidle) AS pocet
    FROM r_uzivatele_zidle
  WHERE id_zidle < 0 AND id_zidle % 100 = -2
  GROUP BY id_uzivatele
) ucast ON ucast.id_uzivatele = uzivatele_hodnoty.id_uzivatele
LEFT JOIN ( -- poslední kladný pohyb na účtu
  SELECT
    id_uzivatele,
    MAX(provedeno) AS datum
  FROM platby
  WHERE castka > 0
  GROUP BY id_uzivatele
) pohyb ON pohyb.id_uzivatele = uzivatele_hodnoty.id_uzivatele
SQL
);
$report->tFormat(get('format'));
