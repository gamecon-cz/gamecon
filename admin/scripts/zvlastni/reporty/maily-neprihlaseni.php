<?php

require __DIR__ . '/sdilene-hlavicky.php';

$o = '
  SELECT u.email1_uzivatele
  FROM uzivatele_hodnoty u
  LEFT JOIN r_uzivatele_zidle prihlasen ON prihlasen.id_uzivatele = u.id_uzivatele AND prihlasen.id_zidle = ' . ZIDLE_PRIHLASEN . '
  WHERE u.email1_uzivatele LIKE "%@%"
  AND u.nechce_maily IS NULL
  AND prihlasen.id_zidle IS NULL -- nepřihlášen
  ORDER BY email1_uzivatele
';

$report = Report::zSql($o);
$report->tFormat(get('format'));
