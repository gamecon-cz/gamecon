<?php

require_once('sdilene-hlavicky.hhp');

$o = '
  SELECT email1_uzivatele
  FROM
  (
    SELECT email1_uzivatele
    FROM uzivatele_hodnoty
    WHERE email1_uzivatele LIKE "%@%"
    AND nechce_maily IS NULL
  ) as maily
  WHERE email1_uzivatele NOT IN
  (
    SELECT email1_uzivatele
    FROM r_prava_zidle
    JOIN r_uzivatele_zidle USING(id_zidle)
    JOIN uzivatele_hodnoty USING(id_uzivatele)
    WHERE id_prava='.ID_PRAVO_PRIHLASEN.'
  )
  ORDER BY email1_uzivatele
';

$report = Report::zSql($o);
$report->tCsv();
