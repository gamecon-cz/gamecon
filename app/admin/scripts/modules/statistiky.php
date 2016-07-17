<?php

/**
 * Stránka statistik GC
 *
 * nazev: Statistiky
 * pravo: 107
 */

$ucast = tabMysql(dbQuery('
  SELECT
    if(jmeno_zidle="Vypravěč","Vypravěč (-org)",jmeno_zidle) as " ", 
    count(z.id_uzivatele) as "Celkem",
    count(tPrihlasen.id_zidle) as Přihlášen
  FROM r_zidle_soupis zs
  LEFT JOIN r_uzivatele_zidle z ON(zs.id_zidle=z.id_zidle AND 
    IF(zs.id_zidle='.Z_ORG_AKCI.',
      IF( z.id_uzivatele IN (SELECT id_uzivatele FROM r_uzivatele_zidle WHERE id_zidle=2),
        0,
        1
      ),
      1
    )
  )
  LEFT JOIN r_uzivatele_zidle tPrihlasen ON(tPrihlasen.id_zidle='.ID_ZIDLE_PRIHLASEN.' AND tPrihlasen.id_uzivatele=z.id_uzivatele)
  WHERE zs.id_zidle IN (10,6,7,2,'.ID_ZIDLE_PRIHLASEN.','.ID_ZIDLE_PRITOMEN.')
  GROUP BY zs.id_zidle
'));

$predmety = tabMysql(dbQuery('
  SELECT
    p.nazev Název,
    p.model_rok Model,
    COUNT(n.id_predmetu) Počet
  FROM shop_nakupy n
  JOIN shop_predmety p ON(n.id_predmetu=p.id_predmetu)
  WHERE n.rok='.ROK.' AND (p.typ=1 OR p.typ=3)
  GROUP BY n.id_predmetu
  -- ORDER BY p.typ, Počet DESC
'));

$ubytovani = tabMysql(dbQuery('
  SELECT
    p.nazev Název,
    COUNT(n.id_predmetu) Počet
  FROM shop_nakupy n
  JOIN shop_predmety p ON(n.id_predmetu=p.id_predmetu)
  WHERE n.rok='.ROK.' AND (p.typ=2)
  GROUP BY n.id_predmetu
'));

$ubytovaniKratce = tabMysql(dbQuery('
  SELECT
    SUBSTR(p.nazev,11) Den,
    COUNT(n.id_predmetu) Počet
  FROM shop_nakupy n
  JOIN shop_predmety p ON(n.id_predmetu=p.id_predmetu)
  WHERE n.rok='.ROK.' AND (p.typ=2)
  GROUP BY p.ubytovani_den
UNION ALL
  SELECT "neubytovaní" as Den, COUNT(1) as Počet 
  FROM r_uzivatele_zidle z
  LEFT JOIN(
    SELECT n.id_uzivatele 
    FROM shop_nakupy n 
    JOIN shop_predmety p ON(n.id_predmetu=p.id_predmetu AND p.typ=2)
    WHERE n.rok='.ROK.'
    GROUP BY n.id_uzivatele
  ) nn ON(nn.id_uzivatele=z.id_uzivatele)
  WHERE id_zidle='.Z_PRIHLASEN.' AND ISNULL(nn.id_uzivatele)
'));

$jidlo = tabMysql(dbQuery('
  SELECT
    TRIM(p.nazev) Název,
    COUNT(n.id_predmetu) Počet,
    COUNT(slevy.id_uzivatele) as Sleva
  FROM shop_nakupy n
  JOIN shop_predmety p ON n.id_predmetu = p.id_predmetu
  LEFT JOIN (
    SELECT uz.id_uzivatele -- id uživatelů s právy uvedenými níž
    FROM r_uzivatele_zidle uz
    JOIN r_prava_zidle pz ON pz.id_zidle = uz.id_zidle AND pz.id_prava IN('.P_JIDLO_ZDARMA.', '.P_JIDLO_SLEVA.')
    GROUP BY uz.id_uzivatele
  ) slevy ON slevy.id_uzivatele = n.id_uzivatele
  WHERE n.rok = '.ROK.' AND p.typ = 4
  GROUP BY n.id_predmetu
  ORDER BY p.ubytovani_den, p.nazev
'));

$pohlavi = tabMysqlR(dbQuery('
  SELECT
    "Počet" as " ", -- formátování
    SUM(IF(u.pohlavi="m",1,0)) as Muži,
    SUM(IF(u.pohlavi="f",1,0)) as Ženy,
    ROUND(SUM(IF(u.pohlavi="f",1,0))/COUNT(1),2) as Poměr
  FROM r_prava_zidle pz
  JOIN r_uzivatele_zidle uz ON(pz.id_zidle=uz.id_zidle)
  JOIN uzivatele_hodnoty u ON(uz.id_uzivatele=u.id_uzivatele)
  WHERE pz.id_prava='.ID_PRAVO_PRIHLASEN.'
'));

$zbyva=new DateTime(DEN_PRVNI_DATE);
$zbyva=$zbyva->diff(new DateTime());
$zbyva=$zbyva->format('%a dní').' ('.round($zbyva->format('%a')/7,1).' týdnů)';

$q='SELECT 
    DATE(z.posazen) as den, 
    COUNT(1) as prihlasen,
    COUNT(IF(YEAR(u.registrovan)='.ROK.',1,NULL)) as novy
  FROM r_uzivatele_zidle z
  JOIN uzivatele_hodnoty u USING(id_uzivatele)
  WHERE z.id_zidle='.ID_ZIDLE_PRIHLASEN.'
  GROUP BY DATE(posazen)';
$o = dbQuery($q);
$zacatek = new DateTime(ROK.'-05-02');
$pocet = 0;
do {
  $pocet += @$r['prihlasen']; // první prázdný ignorovat, další brát "o kolo zpět"
  $r = mysqli_fetch_assoc($o);
  $den = new DateTimeCz($r['den']);
} while( $den->pred($zacatek) && $r['den'] ); // kontrola dne proti zacyklení
// dny před GC
$dny = $prihlaseni = '';
$konecDatum = new DateTime(DEN_PRVNI_DATE);
$konecDatum->add(new DateInterval('P4D'));
for( 
  $den = $zacatek;
  $den->diff($konecDatum)->format('%a') > 0; 
  $den->add(new DateInterval('P1D')) )
{
  $denDb=new DateTime($r['den']);
  if($r===FALSE)
  { // z DB už vše vyčteno
    if($den->getTimestamp() < time()-24*60*60) // dnešek nezobrazujeme pokud přibylo 0, včerejšek a dříve už ano
      $prihlaseni.=$pocet.',';
    else
      $prihlaseni.='null,';
  }
  else if($den->getTimestamp() < $denDb->getTimestamp())
  {
    $prihlaseni.=$pocet.',';
  }
  else if($den->getTimestamp() == $denDb->getTimestamp())
  {
    $pocet+=$r['prihlasen'];
    $prihlaseni.=$pocet.',';
    $r=mysqli_fetch_assoc($o);
  }
  else
  {
    $prihlaseni.='null,';
  }
  $dny.='\''.$den->format('j.n.').'\',';
}
$dny='['.substr($dny,0,-1).']';
$prihlaseni='['.substr($prihlaseni,0,-1).']';

?>



<style>
  tr td { text-align: right; }
  tr td:first-child { text-align: left; }
</style>
<script>
  $(function(){
    $('#vyvojRegu').highcharts({
      chart: {
        type: 'line'
      },
      title: { text: null },
      legend: { enabled: false },
      credits: { enabled: false },
      xAxis: {
        categories: <?=$dny?>,
        labels: {
          rotation: -90,
          style: { fontSize: '8px' }
        }
      },
      yAxis: {
        min: 0,
        minRange: 250,
        title: { text: null }
      },
      plotOptions: {
        line: {
          marker: { radius: 2, symbol:'circle' },
          connectNulls: true,
          animation: false
        }
      },
      series: [
        {
          name: 'Přihlášení 2012',
          data: [
            null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,
            0,null,null,null,null,null,null,null,null,null,null,null,null,null,null,null,
            122,null,null,null,null,null,null,
            171,null, 191, 195, 198, 204, 205,
            207, 212, 212, 217, 219, 225, 229,
            239, 247, 248, 250, 252, 256, 263,
            265, 268, 268, 269, 276, 284, 287,
            291, 294, 295, 301, 306, 316, 325,
            342, 353, 354, 357
          ]
        },{
          name: 'Přihlášení 2013',
          data: [null,null,0,55,80,94,101,106,109,116,119,123,127,133,133,138,141,143,150,151,164,169,177,184,185,192,193,199,203,207,212,213,218,226,237,242,249,252,256,261,262,266,268,270,271,274,274,283,285,298,305,310,311,313,314,321,321,326,329,335,336,339,340,341,342,342,342,344,346,351,352,354,357,361,362,364,368,382,387,395,402,417,426,433],
        },{
          name: 'Přihlášení 2014',
          data: [null,null,2,85,145,165,176,197,223,235,242,246,251,257,269,275,277,280,282,287,292,300,309,316,326,329,334,340,340,347,349,352,353,354,355,356,356,357,358,361,363,364,371,374,375,375,375,378,379,380,383,383,385,389,389,390,390,393,395,399,402,404,407,414,418,422,426,429,430,432,434,434,438,439,441,444,447,454,461,469,479,493,520,520],
        },{
          name: 'Přihlášení 2015',
          data: [2,60,123,141,148,163,178,192,230,254,266,269,281,284,290,294,299,304,308,310,312,318,321,325,329,332,335,338,340,352,353,359,361,362,368,376,381,382,385,385,386,386,386,388,388,389,399,404,408,410,421,422,430,434,436,437,442,447,448,448,454,455,470,476,483,486,490,493,494,495,495,501,503,509,514,516,521,531,541,545,559,568,590,595],
        },{
          name: 'Přihlášení',
          data: <?=$prihlaseni?>,
        }
      ],
      colors: [
        '#2f7ed8',
        '#8bbc21',
        '#910000',
        '#1aadce',
        '#492970',
        '#f28f43',
        '#77a1e5',
        '#c42525',
        '#a6c96a'
      ]
    });
  });
</script>
<script src="files/highcharts.js"></script>

<h2>Aktuální statistiky</h2>

<div style="float:left">
  <?=$ucast?><br>
  <?=$pohlavi?><br>
  Do gameconu zbývá <?=$zbyva?><br><br>
  <span class="hinted">Vysvětlivky ke grafu<span class="hint">
    Data z předchozích let jsou převedena tak, aby počet dní do GameConu na loňské křivce odpovídal počtu dní do GameConu na letošní křivce.<br>
    Svislá čára představuje začátek GameConu. Počet platí pro dané datum v 23:59. 
  </span></span>
</div>
<div style="float:left;margin-left:20px;width:650px;height:300px" id="vyvojRegu"></div>
<div style="position:absolute; margin: 10px 0 0 855px; height:254px; width:1px; background-color:#ccc;"></div>
<div style="clear:both"></div><br>

<div style="float:left"><?=$predmety?></div>
<div style="float:left;margin-left:20px"><?=$ubytovani?></div>
<div style="float:left;margin-left:20px"><?=$ubytovaniKratce?></div>
<div style="float:left;margin-left:20px"><?=$jidlo?></div>

<div style="clear:both"></div>

<h2>Dlouhodobé statistiky</h2>

<style>
  .dlouhodobeStaty th:first-child { width: 110px; }
</style>
<div class="dlouhodobeStaty">

<table>
  <tr><th></th>                       <th>2009</th>   <th>2010</th>   <th>2011</th>   <th>2012</th>   <th>2013</th>   <th>2014</th>   <th>2015</th>   <th>2016</th></tr>
  <tr><td>Registrovaní</td>           <td>339</td>    <td>377</td>    <td>383</td>    <td>357</td>    <td>433</td>    <td>520</td>    <td>595</td>    <td>?</td></tr>
  <tr><td>Dorazilo</td>               <td>68?</td>    <td>350</td>    <td>339</td>    <td>319</td>    <td>389</td>    <td>470</td>    <td>536</td>    <td>?</td></tr>
  <tr><td>&emsp;z toho studenti</td>  <td></td>       <td></td>       <td></td>       <td></td>       <td>149</td>    <td>172</td>    <td>148</td>    <td>?</td></tr>
  <tr><td>&emsp;z toho ostatní</td>   <td></td>       <td></td>       <td></td>       <td></td>       <td>152</td>    <td> </td>      <td>388</td>    <td>?</td></tr>
  <tr><td>Podpůrný tým</td>           <td>43</td>     <td>45</td>     <td>71</td>     <td>74</td>     <td>88</td>     <td>109</td>    <td>111</td>    <td>?</td></tr>
  <tr><td>&emsp;organizátoři</td>     <td>6</td>      <td>8</td>      <td>13</td>     <td>17</td>     <td>17</td>     <td>22</td>     <td>24</td>     <td>?</td></tr>
  <tr><td>&emsp;zázemí</td>           <td>7</td>      <td>7</td>      <td>6</td>      <td>10</td>     <td>8</td>      <td>1</td>      <td>3</td>      <td>?</td></tr>
  <tr><td>&emsp;vypravěči</td>        <td>30</td>     <td>30</td>     <td>52</td>     <td>47</td>     <td>63</td>     <td>86</td>     <td>95</td>     <td>?</td></tr>
</table>
<a href="#" onclick="return!$(this).next().toggle()">dotaz</a>
<pre style="display:none">
  -- všechny staty od Dorazilo níže se počítají z počtu dorazivších
  SELECT 2000 - (id_zidle DIV 100), count(1)
  FROM r_uzivatele_zidle
  JOIN ( -- sekundární židle
    SELECT DISTINCT id_uzivatele FROM r_uzivatele_zidle WHERE id_zidle IN(2,6,7)
  ) orgove USING(id_uzivatele)
  WHERE id_zidle < 0 AND id_zidle MOD 100 = -2
  GROUP BY id_zidle
</pre><br><br>

<?=tabMysqlR(dbQuery('
  select 
    2000-(id_zidle div 100) as "",
    count(id_zidle) "Lidé na GC celkem",
    sum(pohlavi="m") as "&emsp;z toho muži",
    sum(pohlavi="f") as "&emsp;z toho ženy",
    round(sum(pohlavi="f") / count(id_zidle), 2) as "&emsp;podíl žen"
  from r_uzivatele_zidle
  left join uzivatele_hodnoty using(id_uzivatele)
  where id_zidle < 0
  and id_zidle % 100 = -2
  group by id_zidle
  order by id_zidle desc
'))?><br>
  
<table>
  <tr><th></th>                       <th>2009</th>   <th>2010</th>   <th>2011</th>   <th>2012</th>   <th>2013</th>   <th>2014</th>   <th>2015</th>   <th>2016</th></tr>
  <tr><td>Prodané placky</td>         <td>43</td>     <td>45</td>     <td>206</td>    <td>224</td>    <td>207</td>    <td>266</td>    <td>266</td>    <td>?</td></tr>
  <tr><td>&emsp;před začátkem</td>    <td></td>       <td></td>       <td>135</td>    <td>150</td>    <td>110</td>    <td> </td>      <td> </td>      <td>?</td></tr>
  <tr><td>&emsp;na místě</td>         <td></td>       <td></td>       <td></td>       <td></td>       <td>9</td>      <td> </td>      <td> </td>      <td>?</td></tr>
  <tr><td>&emsp;zdarma</td>           <td>43</td>     <td>45</td>     <td>71</td>     <td>74</td>     <td>88</td>     <td> </td>      <td> </td>      <td>?</td></tr>
  <tr><td>Prodané kostky</td>         <td>43</td>     <td>45</td>     <td>247</td>    <td>154</td>    <td>192</td>    <td>382</td>    <td>327</td>    <td>?</td></tr>
  <tr><td>&emsp;před začátkem</td>    <td></td>       <td></td>       <td>176</td>    <td>80</td>     <td>104</td>    <td> </td>      <td> </td>      <td>?</td></tr>
  <tr><td>&emsp;na místě</td>         <td></td>       <td></td>       <td></td>       <td></td>       <td></td>       <td> </td>      <td> </td>      <td>?</td></tr>
  <tr><td>&emsp;zdarma</td>           <td>43</td>     <td>45</td>     <td>71</td>     <td>74</td>     <td>88</td>     <td> </td>      <td> </td>      <td>?</td></tr>
  <tr><td>Prodaná trička</td>         <td>6</td>      <td>8</td>      <td>104</td>    <td>121</td>    <td>139</td>    <td>179</td>    <td>176</td>    <td>?</td></tr>
  <tr><td>&emsp;zdarma</td>           <td>6</td>      <td>8</td>      <td>13</td>     <td>17</td>     <td>19</td>     <td> </td>      <td> </td>      <td>?</td></tr>
  <tr><td>&emsp;za 50%</td>           <td></td>       <td></td>       <td>34</td>     <td>40</td>     <td>35</td>     <td> </td>      <td> </td>      <td>?</td></tr>
  <tr><td>&emsp;plná cena</td>        <td></td>       <td></td>       <td>57</td>     <td>64</td>     <td>85</td>     <td> </td>      <td> </td>      <td>?</td></tr>
</table>
<a href="#" onclick="return!$(this).next().toggle()">dotaz</a>
<pre style="display:none">
  select
    n.rok as "",
    sum(p.nazev = "Placka" and n.rok = model_rok) as "Prodané placky",
    sum(p.nazev = "Kostka" and n.rok = model_rok) as "Prodané kostky",
    sum(p.nazev like "Tričko%" and n.rok = model_rok) as "Prodaná trička"
  from shop_nakupy n
  join shop_predmety p using(id_predmetu)
  group by n.rok
  order by n.rok
</pre><br><br>

<?=tabMysqlR(dbQuery('
  select
    n.rok as "",
    sum(nazev like "trojlůžák%" or nazev like "dvojlůžák%") as "Postel",
    sum((nazev like "trojlůžák%" or nazev like "dvojlůžák%") and ubytovani_den=0) as "&emsp;středa",
    sum((nazev like "trojlůžák%" or nazev like "dvojlůžák%") and ubytovani_den=1) as "&emsp;čtvrtek",
    sum((nazev like "trojlůžák%" or nazev like "dvojlůžák%") and ubytovani_den=2) as "&emsp;pátek",
    sum((nazev like "trojlůžák%" or nazev like "dvojlůžák%") and ubytovani_den=3) as "&emsp;sobota",
    sum((nazev like "trojlůžák%" or nazev like "dvojlůžák%") and ubytovani_den=4) as "&emsp;neděle",
    sum(nazev like "spacák%") as "Spacák",
    sum(nazev like "spacák%" and ubytovani_den=0) as "&emsp;středa ",
    sum(nazev like "spacák%" and ubytovani_den=1) as "&emsp;čtvrtek ",
    sum(nazev like "spacák%" and ubytovani_den=2) as "&emsp;pátek ",
    sum(nazev like "spacák%" and ubytovani_den=3) as "&emsp;sobota ",
    sum(nazev like "spacák%" and ubytovani_den=4) as "&emsp;neděle ",
    sum(nazev like "penzion%") as "Penzion",
    sum(nazev like "penzion%" and ubytovani_den=0) as "&emsp;středa  ",
    sum(nazev like "penzion%" and ubytovani_den=1) as "&emsp;čtvrtek  ",
    sum(nazev like "penzion%" and ubytovani_den=2) as "&emsp;pátek  ",
    sum(nazev like "penzion%" and ubytovani_den=3) as "&emsp;sobota  ",
    sum(nazev like "penzion%" and ubytovani_den=4) as "&emsp;neděle  ",
    sum(nazev like "chata%") as "Kemp",
    sum(nazev like "chata%" and ubytovani_den=0) as "&emsp;středa   ",
    sum(nazev like "chata%" and ubytovani_den=1) as "&emsp;čtvrtek   ",
    sum(nazev like "chata%" and ubytovani_den=2) as "&emsp;pátek   ",
    sum(nazev like "chata%" and ubytovani_den=3) as "&emsp;sobota   ",
    sum(nazev like "chata%" and ubytovani_den=4) as "&emsp;neděle   "
  from shop_nakupy n
  join shop_predmety p using(id_predmetu)
  where p.typ = 2
  group by n.rok
  order by n.rok
'))?><br>

</div>
