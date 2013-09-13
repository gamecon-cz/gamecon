<?php

/** 
 * Stránka s linky na reporty
 * Reporty jsou obecně neoptimalizovaný kód (cyklické db dotazy apod.), nepočítá
 * se s jejich časově kritickým použitím. To platí i pro tuto stránku jako
 * celek. V případě problémů s rychlostí ideálě přesypat některé live generované
 * info odsud do samostatného reportu. 
 *
 * nazev: Reporty
 * pravo: 104
 */

?>



<style>
  .off { text-decoration: none; color: #888; }
</style>

<h2>Univerzální reporty</h2>

<a href="./reporty/aktivity">Historie přihlášení na aktivity</a> (csv) <br>
<a href="./reporty/neplatici">Neplatiči letos</a> (csv) <br>
<a href="./reporty/spolupracovnici">Spolupracovníci (orgové, info, zázemí, vypravěči+aktivity)</a> (csv – v sloupci aktivity zapnout zalamování řádků) <br>
<a href="./reporty/pocty-her">Účastníci a počty jejich aktivit</a> (csv), <a href="./reporty/pocty-her-graf" onclick="return!window.open(this.href)">Graf rozložení rozmanitosti her</a> <br>
<a href="./reporty/rozesilani-ankety" onclick="return!window.open(this.href)">Rozesílání ankety s tokenem</a> <br>
<a href="./reporty/parovani-ankety" onclick="return!window.open(this.href)">Párování ankety a údajů uživatelů</a> <br>
<a href="./reporty/grafy-ankety" onclick="return!window.open(this.href)">Grafy k anketě</a> <br>
<a href="./reporty/update-zustatku" onclick="return!window.open(this.href)">UPDATE příkaz zůstatků pro letošní GC</a> (textově, neprovede se) <br>
<a href="./reporty/ubytovani">Ubytování</a> (csv) <br>
<br>
<a href="./reporty/celkovy-report">Celkový report <?php echo ROK ?></a> (BFGR)<br>

<h3>Seznamy mailů</h3>
<a href="./reporty/maily1" onclick="return!window.open(this.href)">nepřihlášení na letošní GC</a>, 
<a href="./reporty/maily2" onclick="return!window.open(this.href)">přihlášení na letošní GC</a>,
<a href="./reporty/maily3" onclick="return!window.open(this.href)">vypravěči (aktuální)</a>



<?php

return;


$a=dbQuery('
  SELECT 
    CASE p.tricko 
      WHEN "dS" THEN "S (dámské)" 
      WHEN "dM" THEN "M (dámské)" 
      WHEN "dL" THEN "L (dámské)"
      ELSE p.tricko END as Velikost, 
    CASE pr.pravo
      WHEN 2 THEN "červené"
      WHEN 1 THEN "modré"
      ELSE "černé" END as Druh, 
    count(1) as Kusů
  FROM prihlaska_ostatni p
  LEFT JOIN (             
    SELECT id_uzivatele, MAX(CASE id_prava WHEN '.P_TRIKO_ZDARMA.' THEN 2 WHEN '.P_TRIKO_ZAPUL.' THEN 1 ELSE 0 END) as pravo
    FROM r_uzivatele_zidle
    LEFT JOIN r_prava_zidle USING(id_zidle)
    GROUP BY id_uzivatele 
  ) pr USING(id_uzivatele)
  WHERE p.rok='.ROK.'        
  AND p.tricko!="0"       
  GROUP BY tricko, pravo  
  ORDER BY pravo DESC, tricko');
$trika=tabMysql($a);

$a=dbQuery('
  SELECT 
    CASE ubytovani
      WHEN 0 THEN "žádné"
      WHEN 1 THEN "trojlůžkový pokoj"
      WHEN 2 THEN "spacák"
      WHEN 3 THEN "dvoulůžkový pokoj"
      END as "Ubytování celkem",
    count(1) as Postelí
  FROM prihlaska_ostatni
  WHERE rok='.ROK.'
  GROUP BY ubytovani
  ORDER BY Postelí DESC');
$ubytovani=tabMysql($a);

$a=dbQuery('
  SELECT 
    CASE ubytovani
      WHEN 0 THEN "žádné"
      WHEN 1 THEN "trojlůžkový pokoj"
      WHEN 2 THEN "spacák"
      WHEN 3 THEN "dvoulůžkový pokoj"
      END as "Ubytování orgové",
    count(1) as Postelí
  FROM prihlaska_ostatni
  WHERE rok='.ROK.'
  AND id_uzivatele IN(
    SELECT id_uzivatele
    FROM r_uzivatele_zidle
    JOIN r_prava_zidle USING(id_zidle)
    WHERE id_prava='.P_PLNY_SERVIS.'
    GROUP BY id_uzivatele)
  GROUP BY ubytovani
  ORDER BY Postelí DESC');
$ubytovaniOrg=tabMysql($a);

//neoptimalizované dotazy dál, fixme nebo něco
$a=dbQuery('
  SELECT 
    CASE ubytovani
      WHEN 0 THEN "žádné"
      WHEN 1 THEN "trojlůžkový pokoj"
      WHEN 2 THEN "spacák"
      WHEN 3 THEN "dvoulůžkový pokoj"
      END as "Ubytování orgové",
    count(1) as Postelí
  FROM prihlaska_ostatni
  WHERE rok='.ROK.'
  AND id_uzivatele IN(
    SELECT id_uzivatele
    FROM r_uzivatele_zidle
    JOIN r_prava_zidle USING(id_zidle)
    WHERE id_prava='.P_PLNY_SERVIS.'
    GROUP BY id_uzivatele)
  GROUP BY ubytovani
  ORDER BY Postelí DESC');
$ubytovaniOrg=tabMysql($a);

$a=dbQuery('
  SELECT 
    CASE ubytovani
      WHEN 0 THEN "žádné"
      WHEN 1 THEN "trojlůžkový pokoj"
      WHEN 2 THEN "spacák"
      WHEN 3 THEN "dvoulůžkový pokoj"
      END as "Ubytování vypravěči",
    count(1) as Postelí
  FROM prihlaska_ostatni
  WHERE rok='.ROK.'
  AND id_uzivatele IN(
    SELECT id_uzivatele
    FROM r_uzivatele_zidle
    JOIN r_prava_zidle USING(id_zidle)
    WHERE id_prava='.P_ORG_AKCI.'
    GROUP BY id_uzivatele)
  AND id_uzivatele NOT IN(
    SELECT id_uzivatele
    FROM r_uzivatele_zidle
    JOIN r_prava_zidle USING(id_zidle)
    WHERE id_prava='.P_PLNY_SERVIS.'
    GROUP BY id_uzivatele)
  GROUP BY ubytovani
  ORDER BY Postelí DESC');
$ubytovaniVyp=tabMysql($a);

?>

<style>
  tr tr td:last-child { text-align: right; }
</style>

<h2>Letošní statistiky</h2>
<table class="cista">
  <tr>
    <td><?php echo $trika ?></td>
    <td><?php echo $ubytovani ?></td>
    <td><?php echo $ubytovaniOrg ?></td>
    <td><?php echo $ubytovaniVyp ?></td>
  </tr>
</table>

<h2>Univerzální reporty</h2>

<a href="./reporty/aktivity">Historie přihlášení na aktivity</a> (csv) <br>
<a href="./reporty/neplatici">Neplatiči letos</a> (csv) <br>
<a href="./reporty/spolupracovnici">Spolupracovníci letos (orgové, info, zázemí, vypravěči+aktivity)</a> (csv) <br>
<a href="./reporty/ubytovani">Ubytování</a> (csv) <br>
<a href="./reporty/s-poznamkou">Ubytovaní s poznámkou</a> (csv) <br>
<a href="./reporty/pocty-her">Účastníci a počty jejich aktivit</a> (csv), <a href="./reporty/pocty-her-graf" onclick="return!window.open(this.href)">Graf rozložení rozmanitosti her</a> <br>
<a href="./reporty/rozesilani-ankety" onclick="return!window.open(this.href)">Rozesílání ankety s tokenem</a> <br>
<a href="./reporty/parovani-ankety" onclick="return!window.open(this.href)">Párování ankety a údajů uživatelů</a> <br>
<a href="./reporty/grafy-ankety" onclick="return!window.open(this.href)">Grafy k anketě</a> <br>
<a href="./reporty/update-zustatku" onclick="return!window.open(this.href)">UPDATE příkaz zůstatků pro letošní GC</a> (textově, neprovede se) <br>
<br>
<a href="./reporty/celkovy-report">Celkový report <?php echo ROK ?></a> (BFGR)<br>

<h3>Seznamy mailů</h3>
<a href="./reporty/maily1" onclick="return!window.open(this.href)">nepřihlášení na letošní GC</a>, 
<a href="./reporty/maily2" onclick="return!window.open(this.href)">přihlášení na letošní GC</a>,
<a href="./reporty/maily3" onclick="return!window.open(this.href)">vypravěči (aktuální)</a>

<h2>Reporty 2011</h2>

<a href="./reporty/anketa?id=7">Dotazník orgů</a> (csv) <br>
<a href="./reporty/anketa?id=6">Dotazník účastníků</a> (csv) (moc sloupců „co se ti líbilo na GC“)<br>
