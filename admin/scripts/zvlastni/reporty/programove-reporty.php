<?php

require_once('sdilene-hlavicky.php');

for ($i = 16; $i <= 19; $i++) { //16 a 19 jsou data pořádání GC v roce 2015
  for ($j = 7; $j < 24; $j++) { //7 a 24 jsou relevantní hodiny pro report
    // stav = -1502 (GC 2015 přítomen)
    // PŘÍTOMNO LIDÍ NA GC V DANÝ ČAS
    $o = dbOneCol("
      SELECT COUNT(*)
      FROM r_uzivatele_zidle
      WHERE id_zidle=-1502
      AND posazen between '2015-07-".$i." $j:30:00' AND '2015-07-".$i." ".($j+1).":30:00'
    ");

    // POČET ZAPLNĚNÝCH MÍST NA AKTIVITÁCH
    $aktivity = dbOneCol("
      SELECT COUNT(*)
      FROM akce_prihlaseni ap
      LEFT JOIN akce_seznam ase
      ON ap.id_akce=ase.id_akce
      WHERE ap.id_stavu_prihlaseni IN (1,3,4) /* tímto vyloučíme všechny jen přihlášené, kde se neeviduje dorazivšnost - tedy přednášky, párty a většina technických aktivit (ne všechny) */
      AND ase.typ != 10 /* všechny netechnické aktivity */
      AND ase.zacatek < '2015-07-".$i." $j:30:00'
      AND ase.konec > '2015-07-".$i." $j:30:00'
    ");

    // POČET MÍST MAJÍCÍCH JÍDLO DANÝ DEN
    switch($i) {
      case 16: //pokud je den čtvrtek přiřaď id předmětu ve čtvrtek
         $obed_id = 226;
         $vecere_id= 227;
         break;
       case 17: //pokud je den pátek přiřaď id předmětu v pátek (etc.)
         $obed_id = 229;
         $vecere_id= 230;
         break;
       case 18:
         $obed_id = 232;
         $vecere_id= 233;
         break;
       case 19:
         $obed_id = 235;
         $vecere_id= 163; // v neděli není večeře, proto nesmyslná hodnota (chata Richor 4L středa)
         break;
     }
    $obedy = dbOneCol ("
      SELECT COUNT(*)
      FROM shop_nakupy
      WHERE id_predmetu=$obed_id
    ");
    $vecere = dbOneCol ("
      SELECT COUNT(*)
      FROM shop_nakupy
      WHERE id_predmetu=$vecere_id
    ");

    // PLNĚNÍ DO POLE
    $obsah[] = [
    'cas' => "07-".$i." ".$j.":30",
    'proslych' => $o,
    'aktivity' => $aktivity,
    'obedy' => $obedy,
    'vecere' => $vecere,
    ];
  }
}

$hlavicka=["čas","prošlých infopultem","přihlášen na aktivitu (nemusel dorazit)","počet obědů daný den","počet večeří daný den"];
$report = Report::zPoli($hlavicka, $obsah);
$format = get('format') == 'html' ? 'tHtml' : 'tCsv';
$report->$format();
