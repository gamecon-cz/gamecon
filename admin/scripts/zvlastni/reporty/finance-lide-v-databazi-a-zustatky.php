<?php
require __DIR__ . '/sdilene-hlavicky.php';

$report = Report::zSql(<<<SQL
SELECT
  uzivatele_hodnoty.id_uzivatele,
  uzivatele_hodnoty.jmeno_uzivatele,
  uzivatele_hodnoty.prijmeni_uzivatele,
  uzivatele_hodnoty.mesto_uzivatele,
  uzivatele_hodnoty.ulice_a_cp_uzivatele,
  uzivatele_hodnoty.psc_uzivatele,
  uzivatele_hodnoty.email1_uzivatele,
  uzivatele_hodnoty.telefon_uzivatele,
  uzivatele_hodnoty.zustatek,
  ucast.roky AS účast,
  kladny_pohyb.datum AS "poslední kladný pohyb na účtu",
  zaporny_pohyb.datum AS "poslední záporný pohyb na účtu"
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
) AS kladny_pohyb ON kladny_pohyb.id_uzivatele = uzivatele_hodnoty.id_uzivatele
LEFT JOIN ( -- poslední záporný pohyb na účtu
  SELECT
    id_uzivatele,
    MAX(provedeno) AS datum
  FROM platby
  WHERE castka < 0
  GROUP BY id_uzivatele
) AS zaporny_pohyb ON zaporny_pohyb.id_uzivatele = uzivatele_hodnoty.id_uzivatele
SQL
);
$report->tFormat(get('format'));
