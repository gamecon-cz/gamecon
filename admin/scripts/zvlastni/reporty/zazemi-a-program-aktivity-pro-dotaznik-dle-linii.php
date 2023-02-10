<?php
require __DIR__ . '/sdilene-hlavicky.php';

$report = Report::zSql(<<<SQL
SELECT
  CONCAT(
    s.nazev_akce,
    ' (',
    CASE DATE_FORMAT(zacatek,'%w')
      WHEN 1 THEN 'pondělí'
      WHEN 2 THEN 'úterý'
      WHEN 3 THEN 'středa'
      WHEN 4 THEN 'čtvrtek'
      WHEN 5 THEN 'pátek'
      WHEN 6 THEN 'sobota'
      WHEN 0 THEN 'neděle'
    END,
    ' od ',
    DATE_FORMAT(zacatek,'%H:%i'),
    ', ',
    (
      SELECT GROUP_CONCAT(CONCAT(u.jmeno_uzivatele,' "',u.login_uzivatele,'" ', u.prijmeni_uzivatele) SEPARATOR ' ')
      FROM akce_organizatori ao
      JOIN uzivatele_hodnoty u ON ao.id_uzivatele = u.id_uzivatele
      WHERE ao.id_akce = s.id_akce
    ),
    ')',
    ' '
  ) as nazev,
  at.typ_1pmn
FROM akce_seznam s
JOIN akce_typy at ON at.id_typu = s.typ
WHERE s.rok = $1
ORDER BY s.typ, s.nazev_akce
SQL
  , [ROCNIK]
);
$report->tFormat(get('format'));
