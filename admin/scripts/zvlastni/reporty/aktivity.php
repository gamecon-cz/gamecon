<?php

require_once('sdilene-hlavicky.hhp');

$o=dbQuery('
  SELECT 
    a.nazev_akce, 
    count(p.id_uzivatele) as prihlaseno, 
    a.kapacita+a.kapacita_m+a.kapacita_f as kapacita, 
    a.rok, 
    a.den, 
    a.zacatek, 
    t.typ_1p as typ,
    a.cena,
    a.konec-a.zacatek+1 as delka
  FROM akce_seznam a
  LEFT JOIN akce_prihlaseni p ON (a.id_akce=p.id_akce)
  LEFT JOIN akce_typy t ON (a.typ=t.id_typu)
  GROUP BY p.id_akce
  ORDER BY a.typ, a.nazev_akce, a.rok');
  
$r=mysql_fetch_assoc($o);
if(!$r) exit('V tabulce nejsou žádná data.');

header('Content-type: application/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$NAZEV_SKRIPTU.'.csv"');

echo(chr(0xEF).chr(0xBB).chr(0xBF)); //BOM bajty pro nastavení UTF-8 ve výsledném souboru

$out=fopen('php://output','w'); //získáme filedescriptor výstupu stránky pro použití v fputcsv

fputcsv($out,array_keys($r),$CSV_SEP);

do
{
  fputcsv($out,$r,$CSV_SEP);
}while($r=mysql_fetch_assoc($o));

fclose($out);

?>
