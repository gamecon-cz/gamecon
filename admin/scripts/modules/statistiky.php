<?php

/** 
 * Stránka statistik GC
 *
 * nazev: Statistiky
 * pravo: 107
 */
 

$xtpl2=new XTemplate('statistiky.xtpl');


////// účastníci //////
$tab=array();
  $tab[0][]='';
  $tab[1][]='celkem účastníci';
  $tab[2][]='&emsp;studenti';
  $tab[3][]='&emsp;ostatní';
  $tab[4][]='organizátoři*';
$celek=array();
//získání studentů/nestudentů
$a=dbQuery('SELECT rok, student, count(1) as pocet
  FROM prihlaska_ostatni
  GROUP BY rok, student');
while($r=mysql_fetch_assoc($a))
  $celek[$r['rok']][$r['student']]=$r['pocet'];
//získání organizátorů
$a=dbQuery('SELECT rok, student, count(1) as pocet
  FROM r_prava_zidle
  JOIN r_uzivatele_zidle USING(id_zidle)
  JOIN prihlaska_ostatni USING(id_uzivatele)
  WHERE id_prava=2
  GROUP BY rok, student');
while($r=mysql_fetch_assoc($a))
  $celek[$r['rok']][$r['student']+2]=$r['pocet'];
//sestavení tabulky
foreach($celek as $rok=>$p)
{
  $tab[0][]=$rok;
  $tab[1][]=($p[0]+$p[1]-$p[2]-$p[3]);
  $tab[2][]=($p[1]-$p[3]);
  $tab[3][]=($p[0]-$p[2]);
  $tab[4][]=($p[2]+$p[3]);
}
$xtpl2->assign('tabulkaUcOrg',tabHtml($tab));


////// účastníci dle pohlaví //////
$tab=array();
  $tab[0][]='<th></th>';
  $tab[1][]='<td>celkem</td>';
  $tab[2][]='<td>&emsp;ženy</td>';
  $tab[3][]='<td>&emsp;muži</td>';
  $tab[4][]='<td></td>';
$celek=array();
//získání holek a kluků
$a=dbQuery('SELECT rok, pohlavi, count(1) as pocet
  FROM prihlaska_ostatni
  JOIN uzivatele_hodnoty USING(id_uzivatele)
  GROUP BY rok, pohlavi');
while($r=mysql_fetch_assoc($a))
  $celek[$r['rok']][$r['pohlavi']]=$r['pocet'];
//sestavení tabulky
foreach($celek as $rok=>$p)
{
  $tab[0][]='<th>'.$rok.'</th>';
  $tab[1][]='<td>'.($p['f']+$p['m']).'</td>';
  $tab[2][]='<td>'.$p['f'].'</td>';
  $tab[3][]='<td>'.$p['m'].'</td>';
  $tab[4][]='<td>'.round($p['f']/($p['m']+$p['f']),2).'</td>';
}
$tabOut='<table>';
foreach($tab as $radek)
  $tabOut.='<tr>'.implode('',$radek)."</tr>\n";
$tabOut.='</table>';
$xtpl2->assign('tabulkaMZ',$tabOut);


////// věci //////
$xtpl2->assign('tabulkaPredmety',tabMysqlR(dbQuery('
  SELECT
    rok as "",
    COUNT(nullif(kostka,0)) as kostky,
    COUNT(nullif(placka,0)) as placky,
    COUNT(IF(tricko="0",null,1)) as trička
  FROM prihlaska_ostatni 
  GROUP BY rok')));
$a=dbQuery('
  SELECT artefakt, pa.rok, count(id_prodeje) as prodanych
  FROM prodej_artefakty pa
  LEFT JOIN prodej_zaznam USING(id_artefaktu)
  GROUP BY id_artefaktu
  HAVING prodanych>0
  ORDER BY pa.rok, id_artefaktu');
$roky=array();
$artefakty=array();
$celek=array();
$tab=array();
while($r=mysql_fetch_assoc($a))
{
  $roky[$r['rok']]=null;
  $artefakty[$r['artefakt']]=null;
  $celek[$r['artefakt']][$r['rok']]=$r['prodanych'];
}
$tab[0]=array_merge(array(''),array_keys($roky));
$row=1;
foreach($artefakty as $artefakt=>$i)
{
  $tab[$row][]=$artefakt;
  foreach($roky as $rok=>$i)
  {
    $tab[$row][]=isset($celek[$artefakt][$rok])?$celek[$artefakt][$rok]:0;
  }
  $row++;
}
$xtpl2->assign('tabulkaPredmety2',tabHtml($tab));


////// ubytování //////
$tab=array();
$tab[][]='';
$tab[][]='Postel';
$tab[][]='&emsp;'.$PROGRAM_DNY[0];
$tab[][]='&emsp;'.$PROGRAM_DNY[1];
$tab[][]='&emsp;'.$PROGRAM_DNY[2];
$tab[][]='&emsp;'.$PROGRAM_DNY[3];
$tab[][]='&emsp;kapacita';
$tab[][]='Spacák';
$tab[][]='&emsp;'.$PROGRAM_DNY[0];
$tab[][]='&emsp;'.$PROGRAM_DNY[1];
$tab[][]='&emsp;'.$PROGRAM_DNY[2];
$tab[][]='&emsp;'.$PROGRAM_DNY[3];
$tab[][]='&emsp;kapacita';
$a=dbQuery('
  SELECT rok, den, IF(ubytovani=2,2,1) as typ, count(1) as pocet
  FROM prihlaska_ubytovani
  JOIN prihlaska_ostatni USING(id_uzivatele,rok)
  GROUP BY rok, den, typ');
$roky=array();
$tab[11]['2009']=0; //hack kvůli 0 spacákistům v neděli 2009
while($r=mysql_fetch_assoc($a))
{
  $roky[(int)$r['rok']]=0;
  $tab[ ($r['typ']*6-6)+$r['den']+1 ][$r['rok']]=$r['pocet'];
}
foreach(array_keys($roky) as $rok)
{
  $tab[1][$rok]=$tab[2][$rok]+$tab[3][$rok]+$tab[4][$rok]+$tab[5][$rok];
  $tab[6][$rok]=$GLOBALS['KAPACITA_POSTELE'][$rok];
  $tab[7][$rok]=$tab[8][$rok]+$tab[9][$rok]+$tab[10][$rok]+$tab[11][$rok];
  $tab[12][$rok]=$GLOBALS['KAPACITA_SPACAKY'][$rok];
}
$tab[0]=array_merge(array(''),array_keys($roky));
$xtpl2->assign('tabulkaUbytovani',tabHtml($tab));







// --- konec ---
$xtpl2->parse('statistiky');
$xtpl2->out('statistiky');




/* //staré




// --- kostky, placky ---
$xtpl2->assign($p=dbOneLine('
  SELECT
    count(nullif(kostka,0)) as kostka,
    count(nullif(placka,0)) as placka
  FROM prihlaska_ostatni 
  WHERE rok='.$ROK_AKTUALNI.'
  GROUP BY rok'));

$xtpl2->assign($r=dbOneLine('
  SELECT 
    count(nullif(p.kostka,0)) as kostkaOrg,
    count(nullif(p.placka,0)) as plackaOrg 
  FROM prihlaska_ostatni p
  JOIN r_uzivatele_zidle z using(id_uzivatele)
  WHERE rok=2011
  AND id_zidle=2'));

$xtpl2->assign('kostkaUc',$p['kostka']-$r['kostkaOrg']);
$xtpl2->assign('plackaUc',$p['placka']-$r['plackaOrg']);


// --- trička ---
$odpoved=dbQuery('
  SELECT count(p.student) as student, count(p.tricko) as pocet, tricko as velikost 
  FROM prihlaska_ostatni p
  JOIN r_uzivatele_zidle z using(id_uzivatele)
  WHERE rok='.$ROK_AKTUALNI.'
  AND id_zidle=2
  AND p.tricko!="0"
  GROUP BY tricko');
while($r=mysql_fetch_assoc($odpoved))
  $tricka[$r['velikost']]['org']=$r['pocet'];

$odpoved=dbQuery('
  SELECT count(p.tricko) as pocet, tricko as velikost FROM prihlaska_ostatni p
  JOIN (
    SELECT *
    FROM r_uzivatele_zidle
    WHERE id_zidle!="2"
    GROUP BY id_uzivatele) 
    z using(id_uzivatele)
  WHERE rok='.$ROK_AKTUALNI.'
  AND p.tricko!="0"
  GROUP BY tricko');
while($r=mysql_fetch_assoc($odpoved))
  $tricka[$r['velikost']]['uc']=$r['pocet']-$tricka[$r['velikost']]['org']; //trička to spočítá pro org i neorg - nutný odečet

ksort($tricka);

foreach($tricka as $velikost => $pocet)
{
  //if(!$pocet) continue;
  $xtpl2->assign('triko',$velikost);
  if(strpos($velikost,'d')!==false)
    $xtpl2->assign('triko',strtr($velikost,array('d'=>' dámské ')));
  $xtpl2->assign('pocetOrg',$pocet['org']?$pocet['org']:0);
  $xtpl2->assign('pocetUc',$pocet['uc']?$pocet['uc']:0);
  $xtpl2->assign('pocetCelkem',$pocet['uc']+$pocet['org']);  
  $xtpl2->parse('statistiky.triko');
}


// --- stav regu ---
$org=dbOneLine('
  SELECT count(1) as org, count(nullif(p.student,0)) as orgStudent
  FROM prihlaska_ostatni p
  JOIN r_uzivatele_zidle z USING(id_uzivatele)
  WHERE rok='.$ROK_AKTUALNI.'
  AND z.id_zidle=2');
$celek=dbOneLine('
  SELECT count(1) as kazdy, count(nullif(student,0)) as student
  FROM prihlaska_ostatni
  WHERE rok='.$ROK_AKTUALNI);
$xtpl2->assign(array(
  'org'=>$org['org'],
  'neorg'=>$celek['kazdy']-$org['org'],
  'neorgstud'=>$celek['student']-$org['orgStudent'],
  'neorgprac'=>$celek['kazdy']-$celek['student']-($org['org']-$org['orgStudent'])
  ));


// --- ubytování ---
$odpoved=dbQuery('SELECT ubytovani, count(1) as pocet
  FROM prihlaska_ostatni
  WHERE rok=2011
  GROUP BY ubytovani');
while($radek=mysql_fetch_array($odpoved))
  $xtpl2->assign('ubytovani'.$radek['ubytovani'],$radek['pocet']);




*/

?>
