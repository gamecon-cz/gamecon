<?php

/**
 * Počty her a jednotlivých druhý her pro jednotlivé účastníky
 */ 

require_once('sdilene-hlavicky.hhp');

$o=dbQuery('
  SELECT
    p.id_uzivatele as "ID uživatele",
    count(1) as "Počet aktivit",
    count(if(a.typ=0,1,null)) as "Systémové",
    count(if(a.typ=1,1,null)) as "Deskovkové turnaje",
    count(if(a.typ=2,1,null)) as "Larpy",
    count(if(a.typ=3,1,null)) as "Přednášky",
    count(if(a.typ=4 || a.typ=8,1,null)) as "RPG a LKD",
    count(if(a.typ=5,1,null)) as "Dílny",
    count(if(a.typ=6,1,null)) as "Wargaming",
    count(if(a.typ=7,1,null)) as "Bonusy",
    sum(a.konec-a.zacatek+1) as "Σ délka"
  FROM akce_prihlaseni p
  JOIN akce_seznam a USING(id_akce)
  WHERE a.rok='.ROK.'
  GROUP BY p.id_uzivatele');
if(mysqli_num_rows($o)==0)
  exit('V tabulce nejsou žádná data.');

header('Content-type: application/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$NAZEV_SKRIPTU.'.csv"');
echo(chr(0xEF).chr(0xBB).chr(0xBF)); //BOM bajty pro nastavení UTF-8 ve výsledném souboru
//echo '<pre>';

$out=fopen('php://output','w'); //získáme filedescriptor výstupu stránky pro použití v fputcsv

$r=mysqli_fetch_assoc($o);
fputcsv($out,array_merge(array_keys($r),["Σ cena",'Diverzifikace']),$CSV_SEP);
do
{
   //sql subdotazy, zlo
  $un=new Uzivatel(dbOneLine('SELECT * FROM uzivatele_hodnoty WHERE id_uzivatele='.$r['ID uživatele']));
  $un->nactiPrava();
  //dobro
  $div=sprintf('%.3f',aktivityDiverzifikace(array_slice($r,2,8)));
  $r[]=$un->finance()->cenaAktivity();
  $r[]=$div;
  fputcsv($out,$r,$CSV_SEP);
}
while($r=mysqli_fetch_assoc($o));

fclose($out);


?>
