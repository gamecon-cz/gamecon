<?php

require_once('sdilene-hlavicky.hhp');

$o = '
  SELECT email1_uzivatele
  FROM uzivatele_hodnoty
  WHERE email1_uzivatele LIKE "%@%"
  AND souhlas_maily=1
  ORDER BY email1_uzivatele
';

$report = Report::zSql($o);
$report->tCsv();
