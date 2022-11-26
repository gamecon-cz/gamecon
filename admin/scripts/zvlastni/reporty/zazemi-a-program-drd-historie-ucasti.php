<?php
require __DIR__ . '/sdilene-hlavicky.php';

$typDrd = \Gamecon\Aktivita\TypAktivity::DRD;

$report = Report::zSql(<<<SQL
SELECT
  akce_prihlaseni.id_uzivatele,
  TIMESTAMPDIFF(YEAR,u.datum_narozeni,CURDATE()) AS vek,
  GROUP_CONCAT(distinct akce_seznam.rok ORDER BY rok) AS roky
FROM akce_prihlaseni
JOIN akce_seznam ON akce_seznam.id_akce = akce_prihlaseni.id_akce AND akce_seznam.typ = $typDrd AND akce_seznam.nazev_akce NOT LIKE '%registrace%'
JOIN uzivatele_hodnoty u ON u.id_uzivatele = akce_prihlaseni.id_uzivatele
GROUP BY akce_prihlaseni.id_uzivatele
SQL
);
$report->tFormat(get('format'));
