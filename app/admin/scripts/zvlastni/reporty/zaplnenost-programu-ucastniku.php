<?php

require_once('sdilene-hlavicky.hhp');

$sums = "";
for ($i = 16; $i <= 19; $i++) { //16 a 19 jsou data pořádání GC v roce 2015
  for ($j = 7; $j < 24; $j++) { //7 a 24 jsou relevantní hodiny pro report
    $sums .= "SUM(IF('2015-07-$i $j:30:00' between ase.zacatek AND ase.konec,
      1,
      0)
    ) AS '07-$i $j:30', ";
  }
}

$sums = substr($sums, 0, -2);

$query = "SELECT ap.id_uzivatele, $sums
FROM akce_prihlaseni ap
JOIN akce_seznam ase ON ap.id_akce = ase.id_akce
WHERE ase.rok=2015
GROUP by ap.id_uzivatele";

$report = Report::zSql($query);
$format = get('format') == 'html' ? 'tHtml' : 'tCsv';
$report->$format();
