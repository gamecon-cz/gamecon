<?php

require __DIR__ . '/sdilene-hlavicky.php';

// záměrně jsou zahrnutí i uživatelé co nechtějí maily
$o = '
  SELECT u.email1_uzivatele
  FROM uzivatele_hodnoty u
  JOIN r_uzivatele_zidle uz ON uz.id_uzivatele = u.id_uzivatele AND uz.id_zidle = ' . ZIDLE_ORG_AKTIVIT . '
  WHERE u.email1_uzivatele LIKE "%@%"
  ORDER BY u.email1_uzivatele
';

$report = Report::zSql($o);
$report->tFormat(get('format'));
