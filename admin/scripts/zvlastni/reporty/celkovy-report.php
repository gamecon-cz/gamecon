<?php

require_once('sdilene-hlavicky.hhp');

$gcDoted = array();
for($i = 2009; $i < ROK; $i++) {
  $gcDoted[$i] = 'účast '.$i;
}

$hlavicka1=array_merge(
  array('Účastník','','','','','','Datum narození','','','Bydliště','','',
  'Ubytovací informace','','',''),
  array_fill(0,count($gcDoted),''),
  array('Celkové náklady','','',
  'Ostatní platby','','','','','','','','','','')
);
$hlavicka2=array_merge(
  array('ID','Příjmení','Jméno','Přezdívka','Mail','Pozice','Den','Měsíc','Rok','Město','Ulice',
  'PSČ','Příchod','Poslední noc (počátek)','Typ','Dorazil na GC'),
  $gcDoted,
  array(
  'Celkem dní','Náklady / den','Ubytování','Předměty',
  'Aktivity','vypravěčská sleva využitá','vypravěčská sleva přiznaná','stav','zůstatek z minula','připsané platby','první blok','poslední blok','Slevy','Objednávky')
);
$o=dbQuery('
  SELECT 
    -- p.*, 
    u.*, 
    ( SELECT MIN(p.ubytovani_den) FROM shop_nakupy n JOIN shop_predmety p USING(id_predmetu) WHERE n.rok='.ROK.' AND n.id_uzivatele=z.id_uzivatele AND p.typ=2 ) den_prvni, 
    ( SELECT MAX(p.ubytovani_den) FROM shop_nakupy n JOIN shop_predmety p USING(id_predmetu) WHERE n.rok='.ROK.' AND n.id_uzivatele=z.id_uzivatele AND p.typ=2 ) as den_posledni,
    ( SELECT SUBSTR(MAX(p.nazev),1,10) FROM shop_nakupy n JOIN shop_predmety p USING(id_predmetu) WHERE n.rok='.ROK.' AND n.id_uzivatele=z.id_uzivatele AND p.typ=2 ) as ubytovani_typ
  -- FROM prihlaska_ostatni p
  FROM r_uzivatele_zidle z
  JOIN uzivatele_hodnoty u ON(z.id_uzivatele=u.id_uzivatele)
  WHERE z.id_zidle='.Z_PRIHLASEN.'
  ');
if(mysql_num_rows($o)==0) 
  exit('V tabulce nejsou žádná data.');

$obsah[] = $hlavicka2;
while($r=mysql_fetch_assoc($o))
{
  $un=new Uzivatel($r);
  $un->nactiPrava(); //sql subdotaz, zlo
  $stav='účastník';
  if($un->maPravo(ID_PRAVO_ORG_SEKCE))
    $stav='organizátor';
  elseif($un->maPravo(ID_PRAVO_ORG_AKCI))
    $stav='vypravěč';
  $ucastiHistorie=array();
  foreach($gcDoted as $rok => $nul)
    $ucastiHistorie[]=$un->maPravo((int)( '-'.substr($rok,2).'02' ))?'ano':'ne';
  //datum
  $denPrvni=new DateTime(DEN_PRVNI_DATE);
  $obsah[] = array_merge(
    array(
      $r['id_uzivatele'],
      $r['prijmeni_uzivatele'],
      $r['jmeno_uzivatele'],
      $r['login_uzivatele'],
      $r['email1_uzivatele'],
      $stav,
      date('j',strtotime($r['datum_narozeni'])),
      date('n',strtotime($r['datum_narozeni'])),
      date('Y',strtotime($r['datum_narozeni'])),
      $r['mesto_uzivatele'],
      $r['ulice_a_cp_uzivatele'],
      $r['psc_uzivatele'],
      $r['den_prvni']!==null ? $denPrvni->add( DateInterval::createFromDateString(($r['den_prvni']-1).' days') )->format('j.n.Y') : '-',
      $r['den_posledni'] ? $denPrvni->add(new DateInterval('P'.($r['den_posledni']-$r['den_prvni']).'D'))->format('j.n.Y') : '-',
      $r['ubytovani_typ'],
      $un->gcPritomen()?'ano':'ne'
    ),
    $ucastiHistorie,
    array(
      $pobyt=( $r['den_prvni'] ? $r['den_posledni']-$r['den_prvni']+1 : 0 ),
      0&&$pobyt ? $un->finance()->cenaUbytovani()/$pobyt : 0,
      $un->finance()->cenaUbytovani(),
      $un->finance()->cenaPredmety(),
      $un->finance()->cenaAktivity(),
      $un->finance()->slevaVypravecVyuzita(),
      $un->finance()->slevaVypravecMax(),
      $un->finance()->stav(),
      $r['zustatek'],
      $un->finance()->platby(),
      $un->prvniBlok(),
      $un->posledniBlok(),
      implode(", ",array_merge($un->finance()->slevyVse(),$un->finance()->slevyAktivity())),
      strip_tags(strtr($un->finance()->prehledHtml(),array('</tr>'=>", ", '</td>'=>' '))),
    )
  );
}

$report = Report::zPoli($hlavicka1, $obsah); // TODO druhá hlavička
$report->tCsv();
