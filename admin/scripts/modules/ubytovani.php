<?php

/** 
 * Stránka k editaci ubytovacích informací
 *
 * nazev: Ubytování
 * pravo: 101
 */

if($uPracovni)
{
  $shop=new Shop($uPracovni);
  if($shop->zpracujUbytovani())  // došlo k uložení změn, reloadnout stránku
    back();
}

if(isset($_POST['pokoje']))
{
  if(empty($_FILES['pokcsv']['tmp_name']))
    die('soubor nenačten');
  $f=fopen($_FILES['pokcsv']['tmp_name'],'r');
  $q='INSERT INTO ubytovani(id_uzivatele,den,pokoj,rok) VALUES '."\n";
  while($r=fgetcsv($f,512,';'))
  {
    if(count($r)<2 || count($r)>6) die('nesprávný počet sloupců');
    //var_dump($r);
    $uid=$r[0];
    unset($r[0]);
    foreach($r as $i=>$pokoj)
      if($pokoj)
        $q.='('.$uid.','.($i-1).','.$pokoj.','.ROK."),\n";
  }
  $q=substr($q,0,-2).';';
  fclose($f);
  //echo '<pre>';
  $ok=1;
  $ok&=dbQuery('DELETE FROM ubytovani WHERE rok='.ROK.";\n");
  $ok&=dbQuery($q);
  if(!$ok) die();
  chyba('Soubor načten');
}

if(isset($_POST['pridelitPokoj']))
{
  $pokoj=$_POST['pokoj'];
  $uid=$_POST['uid'];
  $o=dbQuery('SELECT ubytovani_den 
  FROM shop_nakupy n
  JOIN shop_predmety p USING(id_predmetu) 
  WHERE n.id_uzivatele='.(int)$uid.' AND n.rok='.ROK.' AND p.typ=2 ');
  dbQuery('DELETE FROM ubytovani WHERE rok='.ROK.' AND id_uzivatele='.$uid);
  $q='INSERT INTO ubytovani(id_uzivatele,den,pokoj,rok) VALUES '."\n";
  while($r=mysql_fetch_assoc($o))
    $q.='('.$uid.','.$r['ubytovani_den'].','.$pokoj.','.ROK."),\n";
  $q=substr($q,0,-2).';';
  dbQuery($q);
  back();
}

$hlaska=Chyba::vyzvedni();

?>



<h1>Ubytování</h1>

<?php if($uPracovni){ ?>
  <?php if($uPracovni->gcPrihlasen()){ ?>
    <form method="post">
    <?=$shop->ubytovaniHtml()?><br><br>
    <input type="submit" value="Upravit">
    </form>
  <?php }else if($uPracovni->pohlavi()=='f'){ ?>
    <div class="error">Uživatelka není přihlášena na GameCon.</div>
  <?php }else{ ?>
    <div class="error">Uživatel není přihlášen na GameCon.</div>
  <?php } ?>
<?php }else{ ?>
  <div class="warning">Vyberte uživatele (pole nahoře)</div>
<?php } ?>

<div class="aBox">
  <h3>Nastavení pokojů</h3>
  Nastavení přepíše stávající stav.<br>
  <form method="post">
  ID uživatele: <input type="integer" value="<?=$uPracovni?$uPracovni->id():''?>" name="uid"><br>
  Pokoj: <input type="integer" name="pokoj"><br>
  <input type="submit" name="pridelitPokoj" value="Přidělit">
  </form>
  <!--
  <h3>Načítání pokojů</h3>
  <form method="post" enctype="multipart/form-data">
    <input type="file" name="pokcsv">
    <input type="submit" name="pokoje" value="Nahrát">
    <span style="color:green"><?=$hlaska?></span>
  </form><br>
  Nahraný soubor přiřadí uživatelům čísla pokojů. Musí být ve formátu:
  <ul>
  <li><i>id_uživatele</i> ; <i>pokoj_středa</i> ; <i>pokoj_čtvrtek</i> ; <i>pokoj_pátek</i> ; <i>pokoj_sobota</i> ; <i>pokoj_neděle</i>
  <li>Sloupce nesmí mít hlavičkové buňky a musí být přesně v pořadí jak je uvedeno výš, jinak to nebude fungovat. Nahráním souboru se přepíší stávající data. 
  <li>Jako formát souboru je potřeba zvolit „CSV (oddělené středníkem)“.
  </ul>
  -->
</div>

