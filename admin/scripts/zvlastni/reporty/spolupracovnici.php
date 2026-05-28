<?php

require_once('sdilene-hlavicky.hhp');

$o=dbQuery('
  SELECT 
    u.id_uzivatele, u.jmeno_uzivatele, u.prijmeni_uzivatele, u.login_uzivatele, u.ulice_a_cp_uzivatele, u.mesto_uzivatele, u.psc_uzivatele,
    p.placka, p.kostka, p.tricko, p.vzkaz, -- počet prvků potud je důležitý při tisku
    a.nazev_akce, a.kapacita+a.kapacita_m+a.kapacita_f as kapacita, a.konec-a.zacatek+1 as delka
  FROM uzivatele_hodnoty u
  JOIN r_uzivatele_zidle z USING(id_uzivatele)
  JOIN r_prava_zidle USING(id_zidle)
  LEFT JOIN (SELECT * FROM akce_seznam WHERE rok='.ROK.')a ON(a.organizator=u.id_uzivatele) -- nutný subselect, where klausule by zabila orgy, kteří měli akce jen v minulých letech a teď už ne
  LEFT JOIN (SELECT * FROM prihlaska_ostatni WHERE rok='.ROK.') p USING(id_uzivatele)
  WHERE id_prava='.P_SPOULUPRACOVNIK.'
  AND (p.rok='.ROK.' OR isnull(p.rok))
  ORDER BY id_uzivatele  
');
  
$r=mysql_fetch_assoc($o);
if(!$r) exit('V tabulce nejsou žádná data.');

header('Content-type: application/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$NAZEV_SKRIPTU.'.csv"');
echo(chr(0xEF).chr(0xBB).chr(0xBF)); //BOM bajty pro nastavení UTF-8 ve výsledném souboru

$au=0;
echo('ID;Jméno;Příjmení;Nick;Ulice;Město;PSČ;Placka;Kostka;Tričko;Vzkaz');
for($i=1;$i<=6;$i++)
  echo(';"Aktivita '.$i.'";"Kapacita '.$i.'";"Délka '.$i.'"');
do
{
  if($r['id_uzivatele']!=$au)
  {
    $au=$r['id_uzivatele'];
    echo "\n";
    $r['vzkaz']='"'.addslashes($r['vzkaz']).'"';
    echo implode(';',$r);
  }
  else
  {
    echo ';'.implode(';',array_slice($r,11));
  }
}while($r=mysql_fetch_assoc($o));

?>
