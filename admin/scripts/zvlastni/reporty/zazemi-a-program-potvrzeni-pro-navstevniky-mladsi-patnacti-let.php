<?php
require __DIR__ . '/sdilene-hlavicky.php';

$report = Report::zSql(<<<SQL
SELECT id_uzivatele, login_uzivatele, jmeno_uzivatele, prijmeni_uzivatele, ulice_a_cp_uzivatele, mesto_uzivatele, stat_uzivatele, psc_uzivatele, telefon_uzivatele, datum_narozeni, funkce_uzivatele, email1_uzivatele, email2_uzivatele, jine_uzivatele, nechce_maily, mrtvy_mail, forum_razeni, zustatek, pohlavi, registrovan, ubytovan_s, skola, poznamka, pomoc_typ, pomoc_vice, op, potvrzeni_zakonneho_zastupce,

(SELECT 'prihlasen'
FROM r_uzivatele_zidle
LEFT JOIN r_zidle_soupis ON r_uzivatele_zidle.id_zidle = r_zidle_soupis.id_zidle
WHERE uzivatele_hodnoty.id_uzivatele = r_uzivatele_zidle.id_uzivatele AND r_zidle_soupis.jmeno_zidle = $2) AS prihlasen_na_gc

FROM uzivatele_hodnoty
WHERE (YEAR($1) - YEAR(datum_narozeni) -
       IF(DATE_FORMAT($1, '%m%d') < DATE_FORMAT(datum_narozeni, '%m%d'), 1, 0)) < 15
ORDER BY prihlasen_na_gc DESC,
         COALESCE(potvrzeni_zakonneho_zastupce, '0001-01-01') ASC,
         registrovan DESC;
SQL
  , [GC_BEZI_OD, sprintf('GC%s přihlášen', ROK)]
);
$report->tFormat(get('format'));
