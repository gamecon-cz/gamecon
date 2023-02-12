<?php
require __DIR__ . '/sdilene-hlavicky.php';

use Gamecon\Role\Role;

$ucast = Role::TYP_UCAST;

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
    GROUP_CONCAT(rocnik_role ORDER BY rocnik_role ASC) AS roky,
    COUNT(uzivatele_role.id_role) AS pocet
    FROM role_seznam
    JOIN uzivatele_role ON role_seznam.id_role = uzivatele_role.id_role
  WHERE role_seznam.typ_role = '$ucast'
  GROUP BY id_uzivatele
) AS ucast ON ucast.id_uzivatele = uzivatele_hodnoty.id_uzivatele
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
