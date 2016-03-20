<?php

require_once('sdilene-hlavicky.hhp');

$o=dbQuery('
  SELECT u.*
  FROM r_uzivatele_zidle z
  JOIN uzivatele_hodnoty u USING(id_uzivatele)
  WHERE id_zidle='.Z_PRIHLASEN);
  
$r=mysql_fetch_assoc($o);
if(!$r) exit('V tabulce nejsou žádná data.');

header('Content-type: application/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$NAZEV_SKRIPTU.'.csv"');
echo(chr(0xEF).chr(0xBB).chr(0xBF)); //BOM bajty pro nastavení UTF-8 ve výsledném souboru
//echo '<pre>';

$out=fopen('php://output','w'); //získáme filedescriptor výstupu stránky pro použití v fputcsv

$seznam=[];

//načtení uživatelů jednotlivě, filtr těch s záporným zůstatkem
do
{
  $un=new Uzivatel($r);
  $un->nactiPrava(); //sql subdotaz, zlo
  $stav='';
  if($un->maPravo(ID_PRAVO_ORG_SEKCE))
    $stav='org';
  elseif($un->maPravo(ID_PRAVO_ORG_AKCI))
    $stav='vypravěč';
  //sql subdotaz, zlo
  //počty v skupinách aktivit
  $drd=$rpg=$larp=$deskovky=$bonusy=0;
  $subs=dbQuery('SELECT typ, count(typ) as pocet
    FROM akce_prihlaseni p
    JOIN akce_seznam a USING(id_akce)
    WHERE id_uzivatele='.$un->id().' AND rok='.ROK.'
    GROUP BY typ');
  while($typ=mysql_fetch_assoc($subs))
  {
    switch($typ['typ'])
    {
      case 0: $drd=1; break;
      case 1: $deskovky=$typ['pocet']; break;
      case 2: $larp=$typ['pocet']; break;
      case 4: $rpg=$typ['pocet']; break;
      case 7: $bonusy=$typ['pocet']; break;
    }
  }
  if($un->finance()->stav()<0)
    $seznam[]=[
      $r['id_uzivatele'],
      $r['login_uzivatele'],
      $r['jmeno_uzivatele'],
      $r['prijmeni_uzivatele'],
      sprintf('%d',$un->finance()->stav()),
      $un->finance()->cenaUbytovani(),
      $un->finance()->cenaPredmety(),
      $un->finance()->cenaAktivity(),
      $drd,$rpg,$larp,$deskovky,$bonusy,
      $stav];
}while($r=mysql_fetch_assoc($o));

//ksort($seznam);

fputcsv($out,['ID','login','jméno','příjmení','stav účtu',
  'ubytování','předměty','aktivity',
  'DrD','RPG','larpy','deskovky','bonusy',
  'poznámka'],$CSV_SEP);
foreach($seznam as $r)
  fputcsv($out,$r,$CSV_SEP);

fclose($out);


?>
