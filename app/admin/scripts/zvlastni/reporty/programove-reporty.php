<?php

$o = '
  SELECT email1_uzivatele
  FROM uzivatele_hodnoty
  WHERE email1_uzivatele LIKE "%@%"
  AND souhlas_maily=1
  ORDER BY email1_uzivatele;
  SELECT
';

for ($i = 16; $i <= 19; $i++) { //16 a 19 jsou data pořádání GC v roce 2015
  for ($j = 7; $j < 24; $j++) { //7 a 24 jsou relevantní hodiny pro report
    // stav = -1502 (GC 2015 přítomen)
    $o = dbOneCol("
      SELECT COUNT(*)
      FROM r_uzivatele_zidle
      WHERE id_zidle=-1502
      AND posazen between '2015-07-$i $j:30:00' AND '2015-07-".$i." ".($j+1).":30:00'
    ");
    $aktivity = dbOneCol("
      SELECT COUNT(*)
      FROM akce_prihlaseni ap
      LEFT JOIN akce_seznam ase
      ON ap.id_akce=ase.id_akce
      WHERE ap.id_stavu_prihlaseni IN (1,3,4) /* tímto vyloučíme všechny jen přihlášené, kde se neeviduje dorazivšnost - tedy přednášky, párty a většina technických aktivit (ne všechny) */
      AND ase.typ != 10 /* všechny netechnické aktivity */
      AND ase.zacatek < '2015-07-".$i." ".$j.":30:00'
      AND ase.konec > '2015-07-".$i." ".$j.":30:00'
    ");
    $obsah[] = [
    'cas' => "07-".$i." ".$j.":30",
    'proslych' => $o,
    'aktivity' => $aktivity,
    ];
  }
}

$hlavicka=["čas","prošlých infopultem","přihlášen na aktivitu (nemusel dorazit)"];
$report = Report::zPoli($hlavicka, $obsah);
$format = get('format') == 'html' ? 'tHtml' : 'tCsv';
$report->$format();
