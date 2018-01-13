<?php

require_once('sdilene-hlavicky.php');

//nastavení startu pro filtr v SQL dotazu - čím vyšší číslo, tím déle účastník nebyl na GC
$start = (int) get('start');

$query = '
  SELECT u.email1_uzivatele
  FROM uzivatele_hodnoty u
  LEFT JOIN r_uzivatele_zidle uz ON uz.id_uzivatele = u.id_uzivatele AND uz.id_zidle MOD 100 = -1
  WHERE u.nechce_maily IS NULL AND u.email1_uzivatele LIKE "%@%"
  GROUP BY u.id_uzivatele
  ORDER BY MIN(COALESCE(uz.id_zidle, 0))
  LIMIT '.$start.','.($start+2000); // filtruje účastníky vždy po 2000 (kvůli omezení mailchimpu)

$report = Report::zSql($query);
$report->tFormat(get('format'));
