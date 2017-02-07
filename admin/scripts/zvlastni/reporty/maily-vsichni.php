<?php

require_once('sdilene-hlavicky.php');

$o = '
  SELECT u.email1_uzivatele
  FROM uzivatele_hodnoty u
  WHERE u.email1_uzivatele LIKE "%@%" AND u.nechce_maily IS NULL
  ORDER BY u.email1_uzivatele
';

$report = Report::zSql($o);
$report->tFormat(get('format'));
