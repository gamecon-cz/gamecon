<?php

require_once('sdilene-hlavicky.hhp');

$hlavicka1=array('Účastník','','','','Datum narození','','','Bydliště','','',
  'Ubytovací informace','','','','','','Pozice','Celkové náklady','','',
  'Ostatní platby','');
$hlavicka2=array('ID','Příjmení','Jméno','Přezdívka','Den','Měsíc','Rok','Město','Ulice',
  'PSČ','Příchod','Poslední noc (počátek)','Typ','Číslo pokoje','Poznámka','Dorazil na GC','',
  'Celkem dní','Náklady / den','Náklady celkem','Předměty',
  'Aktivity (gamecorun)','stav gamecoruny/bonus','zůstatek z minula');
$o=dbQuery('
  SELECT p.*, u.*, MIN(pu.den) as den_prvni, MAX(pu.den) as den_posledni
  FROM prihlaska_ostatni p
  JOIN uzivatele_hodnoty u USING(id_uzivatele)
  LEFT JOIN prihlaska_ubytovani pu ON(p.id_uzivatele=pu.id_uzivatele AND p.rok=pu.rok)
  WHERE p.rok='.ROK.'
  GROUP BY p.id_uzivatele');
if(mysql_num_rows($o)==0) 
  exit('V tabulce nejsou žádná data.');

header('Content-type: application/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$NAZEV_SKRIPTU.'.csv"');
echo(chr(0xEF).chr(0xBB).chr(0xBF)); //BOM bajty pro nastavení UTF-8 ve výsledném souboru
//echo '<pre>';

$out=fopen('php://output','w'); //získáme filedescriptor výstupu stránky pro použití v fputcsv
fputcsv($out,$hlavicka1,$CSV_SEP);
fputcsv($out,$hlavicka2,$CSV_SEP);

while($r=mysql_fetch_assoc($o))
{
  $un=new Uzivatel($r);
  $un->nactiPrava(); //sql subdotaz, zlo
  $stav='účastník';
  if($un->maPravo(ID_PRAVO_ORG_SEKCE))
    $stav='organizátor';
  elseif($un->maPravo(ID_PRAVO_ORG_AKCI))
    $stav='vypravěč';
  //datum
  $denPrvni=new DateTime(DEN_PRVNI_DATE);
  fputcsv($out,array(
    $r['id_uzivatele'],
    $r['prijmeni_uzivatele'],
    $r['jmeno_uzivatele'],
    $r['login_uzivatele'],
    date('j',$r['datum_narozeni_uzivatele']),
    date('n',$r['datum_narozeni_uzivatele']),
    date('Y',$r['datum_narozeni_uzivatele']),
    $r['mesto_uzivatele'],
    $r['ulice_a_cp_uzivatele'],
    $r['psc_uzivatele'],
    $r['den_prvni'] ? $denPrvni->add(new DateInterval('P'.($r['den_prvni']-1).'D'))->format('j.n.Y') : '-',
    $r['den_posledni'] ? $denPrvni->add(new DateInterval('P'.($r['den_posledni']-$r['den_prvni']).'D'))->format('j.n.Y') : '-',
    ubytovaniNazev($r['ubytovani']),
    $r['pokoj'],
    $r['na_pokoji'],
    $un->gcPritomen()?'ano':'ne',
    $stav,
    $pobyt=( $r['den_prvni'] ? $r['den_posledni']-$r['den_prvni']+1 : 0 ),
    $pobyt ? $un->finance()->cenaUbytovani()/$pobyt : 0,
    $un->finance()->cenaUbytovani(),
    $un->finance()->cenaPredmety(),
    $un->finance()->cenaAktivityGac(),
    $un->finance()->hr(),
    $r['zustatek']
  ),$CSV_SEP);
}

fclose($out);


?>
