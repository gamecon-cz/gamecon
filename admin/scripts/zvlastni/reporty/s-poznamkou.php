<?php

require_once('sdilene-hlavicky.hhp');

header('Content-type: application/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$NAZEV_SKRIPTU.'.csv"');
echo(chr(0xEF).chr(0xBB).chr(0xBF)); //BOM bajty pro nastavení UTF-8 ve výsledném souboru
//echo '<pre>';

$out=fopen('php://output','w'); //získáme filedescriptor výstupu stránky pro použití v fputcsv
fputcsv($out,array('ID','login','jméno','příjmení','poznámka','přihlášen'),$CSV_SEP);
$o=dbQuery('
  SELECT u.id_uzivatele, u.login_uzivatele, u.jmeno_uzivatele, u.prijmeni_uzivatele, p.na_pokoji as poznamka, pu.datum_prihlaseni
  FROM prihlaska_ostatni p
  LEFT JOIN uzivatele_hodnoty u USING(id_uzivatele)
  LEFT JOIN prihlaska_uzivatele pu ON(p.id_uzivatele=pu.id_uzivatele AND p.rok=pu.rok)
  WHERE p.rok='.ROK.'
  AND p.na_pokoji!=""
  ORDER BY pu.datum_prihlaseni');
while($r=mysql_fetch_assoc($o))
  fputcsv($out,$r,$CSV_SEP);
fclose($out);

?>
