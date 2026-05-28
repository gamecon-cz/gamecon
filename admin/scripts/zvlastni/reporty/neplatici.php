<?php

require_once('sdilene-hlavicky.hhp');

$o=dbQuery('
  SELECT u.*
  FROM akce_prihlaseni
  JOIN uzivatele_hodnoty u USING(id_uzivatele)
  JOIN akce_seznam USING(id_akce)
  WHERE rok='.ROK.'
  -- AND id_uzivatele IN (SELECT id_uzivatele FROM prihlaska_ostatni WHERE rok=2012) -- v konzistentní db podmínka automaticky splněna
  GROUP BY id_uzivatele');
  
$r=mysql_fetch_assoc($o);
if(!$r) exit('V tabulce nejsou žádná data.');


header('Content-type: application/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$NAZEV_SKRIPTU.'.csv"');
echo(chr(0xEF).chr(0xBB).chr(0xBF)); //BOM bajty pro nastavení UTF-8 ve výsledném souboru
//echo '<pre>';


$out=fopen('php://output','w'); //získáme filedescriptor výstupu stránky pro použití v fputcsv

$seznam=array();

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
  if($un->finance()->gamecoruny()<0.0)
    $seznam[$un->finance()->gamecoruny()]=array(
      $r['id_uzivatele'],
      $r['login_uzivatele'],
      $r['jmeno_uzivatele'],
      $r['prijmeni_uzivatele'],
      sprintf('%d',$un->finance()->gamecoruny()),
      sprintf('%d',$un->finance()->bonus()),
      $un->finance()->cenaUbytovani(),
      $un->finance()->cenaPredmety(),
      $un->finance()->cenaAktivityGac(),
      $drd,$rpg,$larp,$deskovky,$bonusy,
      $stav);
}while($r=mysql_fetch_assoc($o));

ksort($seznam);

fputcsv($out,array('ID','login','jméno','příjmení','gamecoruny','bonus',
  'ubytování','předměty','aktivity (bez bonusu)',
  'DrD','RPG','larpy','deskovky','bonusy',
  'poznámka'),$CSV_SEP);
foreach($seznam as $r)
  fputcsv($out,$r,$CSV_SEP);

fclose($out);


?>
