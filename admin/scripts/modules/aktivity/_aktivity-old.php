<?php

/** 
 * Stránka pro tvorbu a správu aktivit. Povětšinou starý kód
 *
 * nazev: Aktivity
 * pravo: 102
 */

isset($_REQUEST['akce'])?0:$_REQUEST['akce']='aktivity'; //staré post api

$db_jmeno=$db_spojeni=null;

function stav_aktivity($stav){
  switch ($stav){
    case 0: return "V přípravě";
    break;
    case 1: return "Aktivní";
    break;
    case 2: return "Archivní";
    break;
  }
}

function typ_aktivity($typ){
  switch ($typ){
    case 1: return "Deskovka";
    break;
    case 2: return "LARP";
    break;
    case 3: return "Přednáška";
    break;
    case 4: return "RPG";
    break;
    case 5: return "Dílna";
    break;
    case 6: return "WAR";
    break;
    case 7: return "Bonusy";
    break;
    default: return "error!";
    break;
  }
}

function lokace_slovy($id_lokace){
global $db_jmeno,$db_spojeni;
  $sql="
    select
      nazev
    from
      akce_lokace
    where
      id_lokace=$id_lokace
  ";
  $result=dbQuery($db_jmeno,$sql,$db_spojeni);
  if (mysql_num_rows($result)){    
    return mysql_result($result,0,0);
  }
  else {
    return "error!";
  }
}


if (($_REQUEST["akce"] == "prednasejici") and ($_REQUEST["detail_akce"] == "novy_prednasejici")){
  echo "<h2>Vložení přednášejícího</h2>";
  if (!empty($_POST["jmeno"])){
    $sql="
      insert into
        prednasejici_seznam (jmeno)
      values
        ('$_POST[jmeno]')
    ";
    if (dbQuery($db_jmeno,$sql,$db_spojeni)){
      echo "<strong>Uživatel vložen!</strong>";
    }
    else {
      echo "<strong>Chyba, uživatel nebyl vložen!</strong>";
    }
  }
  else {
    echo "<strong>Musí být vyplněno jméno!</strong>";
  }
}
?>

<?
if (($_REQUEST["akce"] == "prednasejici") and (empty($_REQUEST["detail_akce"]))){?>
  <h2>Přidat organizátora</h2>
  <form action="<?echo $_SERVER['REQUEST_URI']?>" method="post">
    Přidat nového organizátora (vypravěče atp...): <br />
    Zobrazované jméno:<input type="text" name="jmeno" />
    <input type="hidden" name="akce" value="prednasejici" />
    <input type="hidden" name="detail_akce" value="novy_prednasejici" />
    <input type="submit" value="Přidat" />
  </form><br />
  
  <h2>Seznam organizátorů</h2>
  <table>
  <tr>
    <td>zobrazované jméno</td>
    <td>funkce</td>
    <td>upravit</td>
    <td>smazat</td>
    <td>akti/neaktiv</td>
  </tr>
    
     <?
      $sql="
        select
          id_prednasejiciho,
          jmeno
        from
          prednasejici_seznam
        order by
          aktivni desc, jmeno asc
      ";
      $result=dbQuery($db_jmeno,$sql,$db_spojeni);
      while($zaznam=mysql_fetch_row($result)){
        echo "
        <tr>
          <td>$zaznam[1]</td>
          <td>funkce</td>
          <td><a href=\"javascript: document.getElementById('upravorga_$zaznam[0]').submit()\">upravit</a><form style=\"display: inline;\" id=\"upravorga_$zaznam[0]\" method=\"post\" /><input type=\"hidden\" name=\"akce\" value=\"prednasejici\"><input type=\"hidden\" name=\"detail_akce\" value=\"uprav_orga\"><input type=\"hidden\" name=\"id_orga\" value=\"$zaznam[0]\"></form></td>
          <td>smazat</td>
          <td>zneaktivnit</td>
        </tr>
        ";
      }
      ?>
  </table>    
<?}

if (($_REQUEST["akce"] == "prednasejici") and ($_REQUEST["detail_akce"] == "uprav_orga")){
  if($_POST["smaz_funkci"] == 1){
  $sql="
    delete from
      organizator_funkce_pevne
    where
      id_organizatora=$_POST[id_orga]
      and id_funkce=$_POST[id_funkce]
  ";
  $result=dbQuery($db_jmeno,$sql,$db_spojeni);
  }
  if($_POST["pridej_funkci"] == 1){
  $sql="
    insert into
      organizator_funkce_pevne
      (id_organizatora, id_funkce)
    values
      ($_POST[id_orga],$_POST[id_funkce])
  ";
  $result=dbQuery($db_jmeno,$sql,$db_spojeni);
  }

  $sql="
    select
      jmeno,
      drd_jmeno,
      kontakt,
      profil,
      drd_profil,
      foto
    from
      prednasejici_seznam
    where
      id_prednasejiciho=$_POST[id_orga]
  "; 
  $result=dbQuery($db_jmeno,$sql,$db_spojeni);
  $jmeno=mysql_result($result,0,0);
  $drd_jmeno=mysql_result($result,0,1);
  $kontakt=mysql_result($result,0,2);
  $profil=mysql_result($result,0,3);
  $drd_profil=mysql_result($result,0,4);
  $foto=mysql_result($result,0,5);
  
  $sql="
    select
      funkce_slovne
    from
      organizatori_funkce
    where
      id_funkce in (
        select
          id_funkce
        from
          organizatori_funkce_pevne
        where
          id_organizatora=$_POST[id_orga]
      )
    order by poradi;
  ";
  $result=dbQuery($db_jmeno,$sql,$db_spojeni);
  $poc_fuknkci=0;
  if (mysql_num_rows($result)){
    while($zaznam=mysql_fetch_row($result)){
      if ($poc_funkci > 0){
        $funkce_pevne .= ", ".$zaznam[0];
      }
      else {
        $funkce_pevne=$zaznam[0];
        $poc_funkci++;
      }
    }
  }
  ?>
  <h2>Úprava organizátora</h2>
  <form action="<?echo $_SERVER['REQUEST_URI']?>" method="post">
    <input type="hidden" name="akce" value="prednasejici" />
    
  zobrazované jméno: <input type="text" name="jmeno" value="<?echo htmlspecialchars($jmeno)?>" /><br>
  drd jméno: <input type="text" name="jmeno" value="<?echo $drd_jmeno?>" /><br>
  kontaktní mail:<input type="text" name="kontakt" value="<?echo $kontakt?>" /><br>
  foto (soubor): <input type="text" name="foto" value="<?echo $foto?>" /><br>
  seznam funkcí: <?echo $funkce_pevne?><br><br>
  Profil:
  <textarea name="profil"><?echo $profil?></textarea><br>
  DrD profil:                           
  <textarea name="profil"><?echo $drd_profil?></textarea><br>
  <form action="<?echo $_SERVER['REQUEST_URI']?>" method="post">
    <input type="hidden" name="akce" value="prednasejici" />
    <input type="hidden" name="detail_akce" value="uprav_orga" />
    blavla
    <input type="submit" value="Uložit a vratit" />
  </form>
    
  <input type="submit" value="Uložit" />  
  </form>
  
  <?

}

if (($_REQUEST["akce"] == "funkce") and (empty($_REQUEST["detail_akce"]) || $_REQUEST["detail_akce"] == "nahoru" || $_REQUEST["detail_akce"] == "dolu" || $_REQUEST["detail_akce"] == "smazat")){
  //provedeme nahorovani a dolovani
  if ($_REQUEST["detail_akce"] == "nahoru"){
    $sql="
      select
        poradi
      from
        organizatori_funkce
      where
        id_funkce=$_POST[id_funkce]
    ";
    $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    $poradi=mysql_result($result,0,0);
    
    $sql="
      update
        organizatori_funkce
      set
        poradi=$poradi
      where
        poradi=$poradi-1
    ";
    dbQuery($db_jmeno,$sql,$db_spojeni);
    
    $sql="
      update
        organizatori_funkce
      set
        poradi=$poradi-1
      where
        id_funkce=$_POST[id_funkce]
    ";
    dbQuery($db_jmeno,$sql,$db_spojeni);  
  }
  
  if ($_REQUEST["detail_akce"] == "dolu"){
    $sql="
      select
        poradi
      from
        organizatori_funkce
      where
        id_funkce=$_POST[id_funkce]
    ";
    $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    $poradi=mysql_result($result,0,0);
    
    $sql="
      update
        organizatori_funkce
      set
        poradi=$poradi
      where
        poradi=$poradi+1
    ";
    dbQuery($db_jmeno,$sql,$db_spojeni);
    
    $sql="
      update
        organizatori_funkce
      set
        poradi=$poradi+1
      where
        id_funkce=$_POST[id_funkce]
    ";
    dbQuery($db_jmeno,$sql,$db_spojeni);  
  } 
  
  if ($_REQUEST["detail_akce"] == "smazat"){
    $sql="
      select
        poradi
      from
        organizatori_funkce
      where
        id_funkce=$_POST[id_funkce]
    ";
    $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    $poradi=mysql_result($result,0,0);
    
    $sql="
      update
        organizatori_funkce
      set
        poradi=poradi -1
      where
        poradi > $poradi
    ";
    dbQuery($db_jmeno,$sql,$db_spojeni);
    
    $sql="
      delete from
        organizatori_funkce
      where
        id_funkce=$_POST[id_funkce]
    ";
    dbQuery($db_jmeno,$sql,$db_spojeni);
  }
  
  
  ?>
  <h2>Seznam funkcí na GameConu</h2>
  <p>Funkce jsou vypsány dle pořadí, v jakém se zobrazují na webu.</p>
  <p>
  <ul>
    <li><a href="javascript: document.getElementById('funkce_nova').submit()">Vložit novou funkci</a></li>
  </ul>
  </p>
  <form style="display: inline;" id="funkce_nova"  method="post" /><input type="hidden" name="akce" value="funkce"><input type="hidden" name="detail_akce" value="funkce_nova"></form>
  
  <table style="width: 100%;">
    <tr>
      <td><strong>Název funkce</strong></td>
      <td>nahoru</td>
      <td>dolů</td>
      <td>smazat</td>
    </tr>
  <?
  $sql="
  select
    max(poradi)
  from
    organizatori_funkce
  ";    
  $result=dbQuery($db_jmeno,$sql,$db_spojeni);
  $max_poradi=mysql_result($result,0,0);
  
  $sql="
  select
      id_funkce
    , funkce_slovne
    , poradi
  from
    organizatori_funkce
  order by 
    poradi asc
  ";    
  $result=dbQuery($db_jmeno,$sql,$db_spojeni);
  if (mysql_num_rows($result)){
    while ($zaznam=mysql_fetch_row($result)){
      $id_funkce=$zaznam[0];
      $slovne=$zaznam[1];
      $poradi=$zaznam[2];
      echo "
      <tr>
        <td>$slovne</td>";
      if ($poradi == 1){
        echo "<td>&nbsp;</td>";
      }
      else {?>
        <td>
          <a href="javascript: document.getElementById('nahoru_<?echo $id_funkce?>').submit()">nahoru</a>
          <form  style="display: inline;"  id="nahoru_<?echo $id_funkce?>"  method="post" />
            <input type="hidden" name="akce" value="funkce">
            <input type="hidden" name="detail_akce" value="nahoru">
            <input type="hidden" name="id_funkce" value="<?echo $id_funkce?>">
          </form> 
        </td>
      <?}
      if ($poradi == $max_poradi){
        echo "<td>&nbsp;</td>";
      }
      else {?>
        <td>
          <a href="javascript: document.getElementById('dolu_<?echo $id_funkce?>').submit()">dolů</a>
          <form  style="display: inline;"  id="dolu_<?echo $id_funkce?>"  method="post" />
            <input type="hidden" name="akce" value="funkce">
            <input type="hidden" name="detail_akce" value="dolu">
            <input type="hidden" name="id_funkce" value="<?echo $id_funkce?>">
          </form>
        </td> 
      <?}
      ?>
      <td>
        <a href="javascript: document.getElementById('smazat_<?echo $id_funkce?>').submit()">smazat</a>
        <form  style="display: inline;"  id="smazat_<?echo $id_funkce?>"  method="post" />
          <input type="hidden" name="akce" value="funkce">
          <input type="hidden" name="detail_akce" value="smazat">
          <input type="hidden" name="id_funkce" value="<?echo $id_funkce?>">
        </form> 
      </td>
      </tr>
      <?      
    }
  }
  else {
    echo "<tr><td colspan=\"4\"><strong>Není nadefinována žádná funkce</strong></td></tr>";
  }
  echo "</table>";  
}

if (($_REQUEST["akce"] == "funkce") and ($_REQUEST["detail_akce"] == "funkce_nova")){?>
  <h2>Vložit novou funkci</h2>
  <form action="<?echo $_SERVER['REQUEST_URI']?>" method="post">
    Přidat novou funkci:<br />
    Název funkce: <input type="text" name="jmeno" /><br>
    <input type="hidden" name="akce" value="funkce" />
    <input type="hidden" name="detail_akce" value="nova" />
    <input type="submit" value="Vložit" />
  </form><br />
  <?}
  
if (($_REQUEST["akce"] == "funkce") and ($_REQUEST["detail_akce"] == "nova")){?>
  <h2>Vložení funkce</h2>
  <?
  $sql="
    select
      max(poradi)
    from
      organizatori_funkce
  ";
  $result=dbQuery($db_jmeno,$sql,$db_spojeni);
  if (mysql_num_rows($result) == 0){
    $poradi=1;
  }
  else {  
    $poradi=mysql_result($result,0,0)+1;
  }
  
  if (!empty($_POST["jmeno"])){
    $sql="
      insert into
        organizatori_funkce (funkce_slovne,poradi)
      values
        ('$_POST[jmeno]',$poradi)
    ";
    if (dbQuery($db_jmeno,$sql,$db_spojeni)){
      echo "<strong>Funkce vložena!</strong>";
    }
    else {
      echo "<strong>Chyba, funkce nebyla vložena!</strong>";
    }
  }
  else {
    echo "<strong>Musí být vyplněn název!</strong>";
  }
}
?>

<?
/////////////////////////////////////
//           AKTIVITY              //
/////////////////////////////////////

if (($_REQUEST["akce"] == "aktivity") and (empty($_REQUEST["detail_akce"]))){
  if(!empty($_POST["patri_pod"])){
    $sql="
      select
        skryta
      from
        menu_seznam
      where
        id_polozky=$_POST[patri_pod] 
    ";
    $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    $skryta=mysql_result($result,0,0);
    if($skryta == 1){
      $sql="
      update
        menu_seznam
      set
        skryta=0
      where
        id_polozky=$_POST[patri_pod] 
      ";
    }
    else{
      $sql="
      update
        menu_seznam
      set
        skryta=1
      where
        id_polozky=$_POST[patri_pod] 
      ";
    }
    dbQuery($db_jmeno,$sql,$db_spojeni);
  }
  
  if(!empty($_POST["zkratka"])){
    $sql="
      select
        aktivni
      from
        side_seznam
      where
        SUBSTRING_INDEX(nazev_obr, '.', 1)=\"$_POST[zkratka]\" 
    ";
    $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    $aktivni=mysql_result($result,0,0);
    if($aktivni == 1){
      $sql="
      update
        side_seznam
      set
        aktivni=0
      where
        SUBSTRING_INDEX(nazev_obr, '.', 1)=\"$_POST[zkratka]\" 
      ";
    }
    else{
      $sql="
      update
        side_seznam
      set
        aktivni=1
      where
        SUBSTRING_INDEX(nazev_obr, '.', 1)=\"$_POST[zkratka]\" 
      ";
    }
    dbQuery($db_jmeno,$sql,$db_spojeni);
  }
  
  if(!empty($_POST["zmenit_stav"])){
    $sql="
      select
        stav
      from
        akce_seznam
      where
        id_akce=\"$_POST[zmenit_stav]\" 
    ";
    $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    $stav=mysql_result($result,0,0);
    if($stav == 1){
      $sql="
      update
        akce_seznam
      set
        stav=0
      where
        id_akce=\"$_POST[zmenit_stav]\"  
      ";
    }
    else{
      $sql="
      update
        akce_seznam
      set
        stav=1
      where
        id_akce=\"$_POST[zmenit_stav]\"  
      "; 
    }
    dbQuery($db_jmeno,$sql,$db_spojeni);
  }

  echo "<h2>Nová aktivita</h2>";?>
  <p>
    <a href="javascript: document.getElementById('nova').submit()">Vložit aktivitu</a>
    <form id="nova"  method="post" /><input type="hidden" name="akce" value="aktivity"><input type="hidden" name="detail_akce" value="nova"></form>
  </p>
  <?
  echo "<h2>Výpis aktivit</h2><p>";
  $sql="
    select
        akce.id_akce
      , akce.nazev_akce
      , (select prednasejici.jmeno from prednasejici_seznam prednasejici where id_prednasejiciho=akce.prednasejici) prednasejici
      , akce.den
      , akce.zacatek
      , (akce.konec-akce.zacatek+1) delka
      , akce.lokace
      , akce.kapacita
      , akce.typ
      , akce.stav
      , (select menu.skryta from menu_seznam menu where akce.patri_pod=menu.id_polozky) skryta
      , akce.patri_pod
      , akce.nazev_akce
      , (select side.aktivni from side_seznam side, menu_seznam menu where menu.id_polozky=akce.patri_pod and menu.nazev_polozky_zkr=SUBSTRING_INDEX(side.nazev_obr, '.', 1) and side.rok=2011) aktivni
      , instance
    from
      akce_seznam akce
    where
      rok=".var_getvalue_sn('rok')." 
    order by
      akce.stav, akce.nazev_akce, akce.den
  ";
  $result=dbQuery($db_jmeno,$sql,$db_spojeni);
  //echo $sql;
  if (mysql_num_rows($result)){
    ?>
    <table style="width: 100%;">
      <tr>
        <td><strong>Stav</strong></td>
        <td><strong>Název aktivity</strong></td>
        <td><strong>Přednášející</strong></td>
        <td><strong>Den</strong></td>
        <td><strong>Začátek</strong></td>
        <td><strong>Délka</strong></td>
        <td><strong>Lokace</strong></td>
        <td><strong>Kapacita</strong></td>
        <td><strong>Typ</strong></td>
        <td><strong>Side</strong></td>
        <td><strong>Web</strong></td>
        <td>uprav</td>
        <td>instance</td>
      </tr>
    <?
    while ($zaznam=mysql_fetch_row($result)){
    switch($zaznam[10]){
      case "": $web="";
      break;
      case "0": $web="OK"; $web_bunka=" style=\"background-color: lightgreen;\"";
      break;
      case "1": $web="skrytá"; $web_bunka=" style=\"background-color: pink;\"";
      break;
    }
    
    switch($zaznam[9]){
      case "1": $stav_bunka=" style=\"background-color: lightgreen;\"";
      break;
      case "0": $stav_bunka=" style=\"background-color: orange;\"";
      break;
    }
    
    $zkr_kontrola=minitext_nazvy($zaznam[12]);
    //echo $zkr_kontrola;
    if (@fopen("http://www.gamecon.cz/system_styly/side/$zkr_kontrola.gif", "r")) {
      switch($zaznam[13]){
        case "": $side="";
        break;
        case "1": $side="OK"; $side_bunka=" style=\"background-color: lightgreen;\"";
        break;
        case "0": $side="skrytá"; $side_bunka=" style=\"background-color: pink;\"";
        break;
      } 
    } 
    else {
      $side="není obrázek";
      $side_bunka=" style=\"background-color: pink;\"";
    }
      echo "
        <tr>
          <td $stav_bunka><a href=\"javascript: document.getElementById('stav_$zaznam[0]').submit()\">".stav_aktivity($zaznam[9])."</a></td>
          <td>$zaznam[1]</td>
          <td>$zaznam[2]</td>
          <td>$zaznam[3]</td>
          <td>$zaznam[4]</td>
          <td>$zaznam[5]</td>
          <td>".lokace_slovy($zaznam[6])."</td>
          <td>$zaznam[7]</td>
          <td>".typ_aktivity($zaznam[8])."</td>
          <td $side_bunka><a href=\"javascript: document.getElementById('side_$zkr_kontrola').submit()\">$side</a></td>";
          if ($zaznam[14]==0){
            echo "<td $web_bunka><a href=\"javascript: document.getElementById('web_$zaznam[11]').submit()\">$web</a></td>";
          } else {
            echo "<td></td>";
          }
          if ($zaznam[14]==0){
            echo "<td><a href=\"javascript: document.getElementById('uprav_$zaznam[0]').submit()\">upravit</a></td>";
          } else {
            echo "<td></td>";          
          }
          if ($zaznam[14]==0){
            echo "<td><a href=\"javascript: document.getElementById('instance_$zaznam[0]').submit()\">instance</a></td>";
          } else {
            echo "<td><a href=\"javascript: document.getElementById('instance_upravit_$zaznam[0]').submit()\">upravit</a></td>";
          }
          echo "  
          <form id=\"uprav_$zaznam[0]\"  method=\"post\" /><input type=\"hidden\" name=\"akce\" value=\"aktivity\"><input type=\"hidden\" name=\"detail_akce\" value=\"upravit\"  /><input type=\"hidden\" name=\"cislo_aktivity\" value=\"$zaznam[0]\" /></form>
          <form id=\"instance_$zaznam[0]\"  method=\"post\" /><input type=\"hidden\" name=\"akce\" value=\"aktivity\"><input type=\"hidden\" name=\"detail_akce\" value=\"instance\"  /><input type=\"hidden\" name=\"cislo_aktivity\" value=\"$zaznam[0]\" /></form>
          <form id=\"instance_upravit_$zaznam[0]\"  method=\"post\" /><input type=\"hidden\" name=\"akce\" value=\"aktivity\"><input type=\"hidden\" name=\"detail_akce\" value=\"instance_upravit\"  /><input type=\"hidden\" name=\"cislo_aktivity\" value=\"$zaznam[0]\" /></form>
          <form id=\"web_$zaznam[11]\"  method=\"post\" /><input type=\"hidden\" name=\"akce\" value=\"aktivity\"><input type=\"hidden\" name=\"patri_pod\" value=\"$zaznam[11]\" /></form>
          <form id=\"stav_$zaznam[0]\"  method=\"post\" /><input type=\"hidden\" name=\"akce\" value=\"aktivity\"><input type=\"hidden\" name=\"zmenit_stav\" value=\"$zaznam[0]\" /></form>
          <form id=\"side_$zkr_kontrola\"  method=\"post\" /><input type=\"hidden\" name=\"akce\" value=\"aktivity\"><input type=\"hidden\" name=\"zkratka\" value=\"$zkr_kontrola\" /></form>
      </tr>
      ";
    }
    echo "</table>";
    
    
  }
  else {
    echo "<strong>Není vytvořena žádná aktivita pro aktuální rok.</strong>";
  }
  echo "</p>";
}

if (($_REQUEST["akce"] == "aktivity") and ($_POST["detail_akce"] == "nova")){
  echo "<h2>Vytvoření aktivity</h2>";
  ?>
  <form  method="post" /">
    <input type="hidden" name="akce" value="aktivity" />
    <input type="hidden" name="detail_akce" value="nova_ulozit" />
    <strong>Název aktivity</strong><br />
    <em>název aktivity je zároveň východiskem pro zklácený název stránky na webu, musí být jedinečný</em><br />
    <input type="text" name="nazev_stranky" style="width: 250px" />  <br />
    <strong>Organizátor aktivity</strong><br />
    <em>organizátor může být přidán v administraci: akce-> vložit přednášejícího</em> <br>
    <select name="prednasejici" style="width: 250px">
    <?
    $sql="
      select
          id_prednasejiciho
        , jmeno
      from
        prednasejici_seznam
      where
        aktivni=1
      order by jmeno
    ";
    $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    if (mysql_num_rows($result)){
      while ($zaznam=mysql_fetch_row($result)){
        echo "<option value=\"$zaznam[0]\">$zaznam[1]</option>";
      }
    }
    ?>
    </select>
    <br />
    <strong>Lokace</strong><br />
    <em>místo, kde se lokace koná. Při výběru "Nemá lokaci!" se aktivita nezobrazí v programu (možno použít pro systémové aktivity jako např. registrace...)</em><br />
    <select name="lokace" style="width: 250px">
      <option value="0">Nemá lokaci!</option>
    <?
    $sql="
      select
          id_lokace
        , nazev
      from
        akce_lokace
      order by poradi
    ";
    $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    if (mysql_num_rows($result)){
      while ($zaznam=mysql_fetch_row($result)){
        echo "<option value=\"$zaznam[0]\">$zaznam[1]</option>";
      }
    }
    ?>
    </select>
    <br />
    
    <strong>Den, začátek a délka aktivity</strong><br />
    <select name="den"  style="width: 250px">
      <option value="0">nezobrazovat čas aktivity</option>
      <option value="1">čtvrtek</option>
      <option value="2">pátek</option>
      <option value="3">sobota</option>
      <option value="4">neděle</option>
    </select><br />
    <strong>Začátek</strong><br />
    <input type="text" name="zacatek" style="width: 50px;" /><br />
    <strong>Délka aktivity</strong><br />
    <input type="text" name="delka"  style="width: 50px;" /><br />
    <em>Den - nezobrazovat - aktivita se zobrazí na webu s poznámkou "čas ještě není znám" a nebude možné se na ní registrovat<br />
    Začátek - hodina začátku, pouze celé hodiny, rozmezí 8 - 23<br />
    Délka - v celých hodinách (min. 1)<br /> 
    Pozn - při vyplnění začátku na 0 a délky akce na 1 (pozor, den musí být vyplněn jinak než na "Nezobrazovat") se akce vypíše bez času a zobrazí se hláška "akce na přání (viz níže)"</em><br /><br />
    <strong>Kapacita aktivity</strong><br />
    <input type="text" name="kapacita" value="0" style="width: 50px;" /><br />
    <em>0 pro neomezenou kapacitu - přednášky, velké larpy...</em><br />
    <strong>Cena aktivity</strong><br />
    <input type="text" name="cena" value="0"  style="width: 50px;" /><br />
    <em>plná cena, slevy se dopočítavají automaticky, 0 pro akce bez ceny (přednášky...)</em><br /><br />
    <strong>Typ aktivity</strong><br />
    <select name="typ" style="width: 250px">
      <option value="0">žádný (systémové využití)</option>
      <option value="1">Deskovka</option>
      <option value="2">Larp</option>
      <option value="3">Přednáška</option>
      <option value="4">RPG</option>
      <option value="5">Workshop</option>
      <option value="6">Wargaming</option>
      <option value="7">Bonusy</option>
    </select><br />
    <em>typ aktivity, aby systém věděl, kde vytvořit stránku s informacemi</em>
    <br />    
    
    <strong>Vlastní text aktivity:</strong><br />
    <textarea name="obsah" style="width: 700px; height: 300px; scroll: auto;">
<p style="clear: both;">
První odstavec textu
</p>
<p>
Druhý odstavec textu
</p>
<p>
Lorem ipsum donor...(třetí odstavec)
</p>

<p>
<ul>
<li>odrážka1</li>
<li>odrážka2 (nezavírat <ul>)</li>

    </textarea>
    <br />
    <input type="submit" value="Uložit aktivitu"> <br /><br />
  </form>  
    
    <strong>Vzor pro psaní aktivity</strong><br />
      &lt;p style="clear: both;"&gt;První odstavec textu&lt;/p&gt; - všechny texty oddělovat do logických odstavců pomocí &lt;p&gt; a &lt;/p&gt;<br />
      &lt;p&gt;Druhý odstavec textu&lt;/p&gt; - všechny texty oddělovat do logických odstavců pomocí &lt;p&gt; a &lt;/p&gt;<br /> 
      &lt;p&gt;<br />
      &nbsp;&nbsp;Lorem ipsum donor...(třetí odstavec)<br />
      &lt;/p&gt; - text uvozený v tagu odstavce &lt;/p&gt;<br /><br />
      
      &lt;p&gt;<br />
      &nbsp;&nbsp;&lt;ul&gt;<br /> - na konci vzdy nechat otevreny tag UL! Takze i kdyz zadnou odrazku nechci, tak tady nechat &lt;p&gt;&lt;ul&gt; (vypisuje se do nej navigace mezi aktivitami)<br>  
      &nbsp;&nbsp;&nbsp;&nbsp;&lt;li&gt;odrážka1 (třeba více informací o aktivitě, rpg...)&lt;/li&gt;<br />
      &nbsp;&nbsp;&nbsp;&nbsp;&lt;li&gt;odrážka1&lt;/li&gt;<br />
      &nbsp;&nbsp;&nbsp;&nbsp;&lt;li&gt;odrážka1&lt;/li&gt;- odrážky pomocí GC kostiček<br /><br />
    
      <br />
      &nbsp;&nbsp;Vnitřní odkazy &lt;a href="/novinky"&gt;relativní adresou&lt;/a&gt; a vnější odkazy &lt;a href="http://www.elden.cz" onclick="window.open(this.href,'_blank'); return false"&gt;javascriptem&lt;/a&gt;<br />
      <br />  
  <?
}

if (($_REQUEST["akce"] == "aktivity") and ($_POST["detail_akce"] == "nova_ulozit")){
  switch ($_POST["typ"]){
  case 1: $patri_pod=39;//deskovka
  break;
  case 2: $patri_pod=38;//larp
  break;
  case 3: $patri_pod=40;//přednáška
  break;
  case 4: $patri_pod=37;//rpg
  break;
  case 5: $patri_pod=42;//dilny
  break;
  case 6: $patri_pod=245;//wargaming
  break;
  case 7: $patri_pod=332;//Bonusy
  break;
  }

  if ($_POST["nazev_stranky"] == ""){
    $chyba .= "Nevyplněn název aktivity (název stránky)!<br />";
  }
  if ($_POST["zacatek"] == ""){
    $chyba .= "Nevyplněn začátek!<br />";
  }
  if ($_POST["delka"] == ""){
    $chyba .= "Nevyplněna délka!<br />";
  }
  if ($_POST["kapacita"] == ""){
    $chyba .= "Nevyplněna kapacita!<br />";
  }
  if ($_POST["cena"] == ""){
    $chyba .= "Nevyplněna cena!<br />";
  }
  if ($_POST["typ"] == "0"){
    $chyba .= "Nevyplněn typ akce!<br />";
  }
  $nazev_stranky_zkr=minitext_nazvy($_POST["nazev_stranky"]);
  if (!empty($nazev_stranky_zkr)){ 
    $sql="
    select 
      count(*)
    from
      menu_seznam
    where
      nazev_polozky_zkr=\"$nazev_stranky_zkr\"    
    ";
    //echo $sql;
    $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    $pocet_stejnych=mysql_result($result,0,0);
    if ($pocet_stejnych > 0){
      $chyba .= "Stránka se stejným jménem už existuje!<br />";
    }
  }  
  if ($chyba == ""){
    $nazev_stranky_zkr=minitext_nazvy($_POST["nazev_stranky"]);
  
    $sql="
    select
      max(poradi_polozky)
    from
      menu_seznam
    where
          nadrazeny_prvek=$patri_pod
      and nemenna=0
      and nazev_polozky < \"$_POST[nazev_stranky]\"";
    $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    //echo $sql."<br />";
    
    $poradi=mysql_result($result,0,0);
    if ($poradi == ""){
      $sql="
      select
        max(poradi_polozky)
      from
        menu_seznam
      where
            nadrazeny_prvek=$patri_pod
        and nemenna=1;";
      $result=dbQuery($db_jmeno,$sql,$db_spojeni);
      $poradi=mysql_result($result,0,0);
      //echo $sql."<br />";
    }
    $poradi++;
    
    $sql="
    update
      menu_seznam
    set
      poradi_polozky=poradi_polozky+1
    where
          nadrazeny_prvek=$patri_pod
      and poradi_polozky >= $poradi
    ";    
    dbQuery($db_jmeno,$sql,$db_spojeni);
    //echo $sql."<br />";
    
    $sql="
    insert into
      menu_seznam
      (nazev_polozky,nazev_polozky_zkr,nadrazeny_prvek,poradi_polozky,skryta,nemenna)
    values
      ('$_POST[nazev_stranky]','$nazev_stranky_zkr',$patri_pod,$poradi,1,0)";
    dbQuery($db_jmeno,$sql,$db_spojeni);
    //echo $sql."<br />";
    
    $aktualni_id=mysql_insert_id();
    $cesta=url_stranky($aktualni_id); 
    
    $sql="insert into stranky_seznam (id_stranky,url_stranky) values ($aktualni_id,'$cesta')";
    dbQuery($db_jmeno,$sql,$db_spojeni);
    //echo $sql."<br />";
        
    $sql="insert into stranky_meta (id_stranky,title_stranky,keywords_stranky,description_stranky) values ($aktualni_id,'GameCon - $_POST[nazev_stranky]','KW','DS')";
    dbQuery($db_jmeno,$sql,$db_spojeni);
    //echo $sql."<br />";
        
    $sql="insert into stranky_obsah (id_stranky,obsah_stranky) VALUES ($aktualni_id,'$_POST[obsah]')";
    dbQuery($db_jmeno,$sql,$db_spojeni);
    //echo $sql."<br />";
    
    $sql="insert into side_seznam (odkaz,nazev_obr,nazev,sekce,rok,aktivni) VALUES ('/$cesta','$nazev_stranky_zkr.gif','$_POST[nazev_stranky]',$_POST[typ],2011,0)";
    dbQuery($db_jmeno,$sql,$db_spojeni);
    
    $konec=$_POST["zacatek"]+$_POST["delka"]-1;
    
    $sql="insert into akce_seznam
            (patri_pod,nazev_akce,prednasejici,den,zacatek,konec,lokace,kapacita,cena,sleva,typ,rok,stav)
            VALUES
            ($aktualni_id,'$_POST[nazev_stranky]',$_POST[prednasejici],$_POST[den],$_POST[zacatek],$konec,$_POST[lokace],$_POST[kapacita],$_POST[cena],1,$_POST[typ],2011,0)";
    dbQuery($db_jmeno,$sql,$db_spojeni);
    echo $sql."<br />";
    
    
    
    echo "<br /><strong>Stránka vložena</strong><br />";
  }
  else {
    echo "<br /><br /><strong style=\"color: red;\">Chyba!<br />$chyba</strong>";
    ?>
    <h2>Vytvoření aktivity</h2>
  <form  method="post" /">
    <input type="hidden" name="akce" value="aktivity" />
    <input type="hidden" name="detail_akce" value="nova_ulozit" />
    <strong>Název aktivity</strong><br />
    <em>název aktivity je zároveň východiskem pro zklácený název stránky na webu, musí být jedinečný</em><br />
    <input type="text" name="nazev_stranky" style="width: 250px" value="<?echo $_POST["nazev_stranky"]?>"/>  <br />
    <strong>Organizátor aktivity</strong><br />
    <em>organizátor může být přidán v administraci: akce-> vložit přednášejícího</em> <br>
    <select name="prednasejici" style="width: 250px">
    <?
    $sql="
      select
          id_prednasejiciho
        , jmeno
      from
        prednasejici_seznam
      where
        aktivni=1
      order by jmeno
    ";
    $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    if (mysql_num_rows($result)){
      while ($zaznam=mysql_fetch_row($result)){
        if ($_POST["prednasejici"] == $zaznam[0]){
          $radek_prednasejici="selected=\"selected\"";
        }
        else {
          $radek_prednasejici="";
        }
        echo "<option value=\"$zaznam[0]\" $radek_prednasejici >$zaznam[1]</option>";
      }
    }
    ?>
    </select>
    <br />
    <strong>Lokace</strong><br />
    <em>místo, kde se lokace koná. Při výběru "Nemá lokaci!" se aktivita nezobrazí v programu (možno použít pro systémové aktivity jako např. registrace...)</em><br />
    <select name="lokace" style="width: 250px">
      <option value="0">Nemá lokaci!</option>
    <?
    $sql="
      select
          id_lokace
        , nazev
      from
        akce_lokace
      order by poradi
    ";
    $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    if (mysql_num_rows($result)){
      while ($zaznam=mysql_fetch_row($result)){
        if ($_POST["lokace"] == $zaznam[0]){
          $radek_lokace="selected=\"selected\"";
        }
        else {
          $radek_lokace="";
        }
        echo "<option value=\"$zaznam[0]\" $radek_lokace>$zaznam[1]</option>";
      }
    }
    ?>
    </select>
    <br />
    
    <strong>Den, začátek a délka aktivity</strong><br />
    <select name="den"  style="width: 250px">
      <option value="0" <? if ($_POST["den"] == 0){echo "selected=\"selected\"";} ?>>nezobrazovat čas aktivity</option>
      <option value="1" <? if ($_POST["den"] == 1){echo "selected=\"selected\"";} ?>>čtvrtek</option>
      <option value="2" <? if ($_POST["den"] == 2){echo "selected=\"selected\"";} ?>>pátek</option>
      <option value="3" <? if ($_POST["den"] == 3){echo "selected=\"selected\"";} ?>>sobota</option>
      <option value="4" <? if ($_POST["den"] == 4){echo "selected=\"selected\"";} ?>>neděle</option>
    </select><br />
    <strong>Začátek</strong><br />
    <input type="text" name="zacatek" style="width: 50px;" value="<?echo $_POST["zacatek"]?>" /><br />
    <strong>Délka aktivity</strong><br />
    <input type="text" name="delka"  style="width: 50px;" value="<?echo $_POST["delka"]?>" /><br />
    <em>Den - nezobrazovat - aktivita se zobrazí na webu s poznámkou "čas ještě není znám" a nebude možné se na ní registrovat<br />
    Začátek - hodina začátku, pouze celé hodiny, rozmezí 8 - 23<br />
    Délka - v celých hodinách (min. 1)<br /> 
    Pozn - při vyplnění začátku na 0 a délky akce na 1 (pozor, den musí být vyplněn jinak než na "Nezobrazovat") se akce vypíše bez času a zobrazí se hláška "akce na přání (viz níže)"</em><br /><br />
    <strong>Kapacita aktivity</strong><br />
    <input type="text" name="kapacita"  value="<?echo $_POST["kapacita"]?>" /><br />
    <em>0 pro neomezenou kapacitu - přednášky, velké larpy...</em><br />
    <strong>Cena aktivity</strong><br />
    <input type="text" name="cena"  value="<?echo $_POST["cena"]?>"  style="width: 50px;" /><br />
    <em>plná cena, slevy se dopočítavají automaticky, 0 pro akce bez ceny (přednášky...)</em><br /><br />
    <strong>Typ aktivity</strong><br />
    <select name="typ" style="width: 250px">
      <option value="0" <? if ($_POST["typ"] == 0){echo "selected=\"selected\"";} ?>>žádný (systémové využití)</option>
      <option value="1" <? if ($_POST["typ"] == 1){echo "selected=\"selected\"";} ?>>Deskovka</option>
      <option value="2" <? if ($_POST["typ"] == 2){echo "selected=\"selected\"";} ?>>Larp</option>
      <option value="3" <? if ($_POST["typ"] == 3){echo "selected=\"selected\"";} ?>>Přednáška</option>
      <option value="4" <? if ($_POST["typ"] == 4){echo "selected=\"selected\"";} ?>>RPG</option>
      <option value="5" <? if ($_POST["typ"] == 5){echo "selected=\"selected\"";} ?>>Workshop</option>
      <option value="6" <? if ($_POST["typ"] == 6){echo "selected=\"selected\"";} ?>>Wargaming</option>
      <option value="7" <? if ($_POST["typ"] == 7){echo "selected=\"selected\"";} ?>>Bonusy</option>
    </select><br />
    <em>typ aktivity, aby systém věděl, kde vytvořit stránku s informacemi</em>
    <br />    
    
    <strong>Vlastní text aktivity:</strong><br />
    <textarea name="obsah" style="width: 700px; height: 300px; scroll: auto;"><?echo $_POST["obsah"]?>
    </textarea>
    <br />
    <input type="submit" value="Uložit aktivitu"> <br /><br />
  </form>  
    
    <strong>Vzor pro psaní aktivity</strong><br />
      &lt;p style="clear: both;"&gt;První odstavec textu&lt;/p&gt; - všechny texty oddělovat do logických odstavců pomocí &lt;p&gt; a &lt;/p&gt;<br />
      &lt;p&gt;Druhý odstavec textu&lt;/p&gt; - všechny texty oddělovat do logických odstavců pomocí &lt;p&gt; a &lt;/p&gt;<br /> 
      &lt;p&gt;<br />
      &nbsp;&nbsp;Lorem ipsum donor...(třetí odstavec)<br />
      &lt;/p&gt; - text uvozený v tagu odstavce &lt;/p&gt;<br /><br />
      
      &lt;p&gt;<br />
      &nbsp;&nbsp;&lt;ul&gt;<br /> - na konci vzdy nechat otevreny tag UL! Takze i kdyz zadnou odrazku nechci, tak tady nechat &lt;p&gt;&lt;ul&gt; (vypisuje se do nej navigace mezi aktivitami)<br>  
      &nbsp;&nbsp;&nbsp;&nbsp;&lt;li&gt;odrážka1 (třeba více informací o aktivitě, rpg...)&lt;/li&gt;<br />
      &nbsp;&nbsp;&nbsp;&nbsp;&lt;li&gt;odrážka1&lt;/li&gt;<br />
      &nbsp;&nbsp;&nbsp;&nbsp;&lt;li&gt;odrážka1&lt;/li&gt;- odrážky pomocí GC kostiček<br /><br />
    
      <br />
      &nbsp;&nbsp;Vnitřní odkazy &lt;a href="/novinky"&gt;relativní adresou&lt;/a&gt; a vnější odkazy &lt;a href="http://www.elden.cz" onclick="window.open(this.href,'_blank'); return false"&gt;javascriptem&lt;/a&gt;<br />
      <br />  
    <?
    
  }
}

if (($_REQUEST["akce"] == "aktivity") and ($_POST["detail_akce"] == "instance_upravit")){
?>
<h2>Úprava instance</h2>
  <form  method="post" /">
    <input type="hidden" name="akce" value="aktivity" />
    <input type="hidden" name="detail_akce" value="instance_upravit2" />
    <input type="hidden" name="cislo_aktivity" value="<?echo $_POST["cislo_aktivity"]?>">
    <strong>Název aktivity</strong><br />
    <?
    $sql="
    select
        nazev_akce
      , patri_pod
      , prednasejici
      , den
      , zacatek
      , konec
      , lokace
      , kapacita
      , cena
      , typ
    from
      akce_seznam
    where
      id_akce=$_POST[cislo_aktivity]
    ";
    $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    $nazev_akce=mysql_result($result,0,0);
    $patri_pod=mysql_result($result,0,1);
    $prednasejici=mysql_result($result,0,2);
    $den=mysql_result($result,0,3);
    $zacatek=mysql_result($result,0,4);
    $konec=mysql_result($result,0,5);
    $lokace=mysql_result($result,0,6);
    $kapacita=mysql_result($result,0,7);
    $cena=mysql_result($result,0,8);
    $typ=mysql_result($result,0,9);
    
    $sql="
    select
      obsah_stranky
    from
      stranky_obsah
    where
      id_stranky=$patri_pod
    ";
    $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    $obsah=mysql_result($result,0,0);
    ?>
    <input type="hidden" name="id_akce" value="<?echo $_POST["cislo_aktivity"]?>" />
      
    <input type="hidden" name="patri_pod" value="<?echo $patri_pod?>" />
    <input type="hidden" name="nazev_akce" value="<?echo $nazev_akce?>" />
    <input type="hidden" name="prednasejici" value="<?echo $prednasejici?>" />
    <input type="hidden" name="cena" value="<?echo $cena?>" />
    <input type="hidden" name="sleva" value="1" />
    <input type="hidden" name="typ" value="<?echo $typ?>" />
    <input type="hidden" name="rok" value="<?echo var_getvalue_sn('rok')?>" />
    <input type="hidden" name="stav" value="1" />
    
    <input type="text" name="nazev_stranky" style="width: 250px" value="<?echo $nazev_akce?>" readonly="readonly"/>  <br />

    <br />
    <strong>Lokace</strong><br />
    <em>místo, kde se lokace koná. Při výběru "Nemá lokaci!" se aktivita nezobrazí v programu (možno použít pro systémové aktivity jako např. registrace...)</em><br />
    <select name="lokace" style="width: 250px">
      <option value="0">Nemá lokaci!</option>
    <?
    $sql="
      select
          id_lokace
        , nazev
      from
        akce_lokace
      order by poradi
    ";
    $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    if (mysql_num_rows($result)){
      while ($zaznam=mysql_fetch_row($result)){
        if ($lokace == $zaznam[0]){
          $radek_lokace="selected=\"selected\"";
        }
        else {
          $radek_lokace="";
        }
        echo "<option value=\"$zaznam[0]\" $radek_lokace>$zaznam[1]</option>";
      }
    }
    ?>
    </select>
    <br />
    
    <strong>Den, začátek a délka instance</strong><br />
    <select name="den"  style="width: 250px">
      <option value="0" <? if ($den == 0){echo "selected=\"selected\"";} ?>>nezobrazovat čas instance</option>
      <option value="1" <? if ($den == 1){echo "selected=\"selected\"";} ?>>čtvrtek</option>
      <option value="2" <? if ($den == 2){echo "selected=\"selected\"";} ?>>pátek</option>
      <option value="3" <? if ($den == 3){echo "selected=\"selected\"";} ?>>sobota</option>
      <option value="4" <? if ($den == 4){echo "selected=\"selected\"";} ?>>neděle</option>
    </select><br />
    <strong>Začátek</strong><br />
    <input type="text" name="zacatek" style="width: 50px;" value="<?echo $zacatek?>" /><br />
    <strong>Délka aktivity</strong><br />
    <input type="text" name="delka"  style="width: 50px;" value="<?echo ($konec - $zacatek + 1)?>" /><br />
    <em>Den - nezobrazovat - aktivita se zobrazí na webu s poznámkou "čas ještě není znám" a nebude možné se na ní registrovat<br />
    Začátek - hodina začátku, pouze celé hodiny, rozmezí 8 - 23<br />
    Délka - v celých hodinách (min. 1)<br /> 
    Pozn - při vyplnění začátku na 0 a délky akce na 1 (pozor, den musí být vyplněn jinak než na "Nezobrazovat") se akce vypíše bez času a zobrazí se hláška "akce na přání (viz níže)"</em><br /><br />
    <strong>Kapacita aktivity</strong><br />
    <input type="text" name="kapacita"  value="<?echo $kapacita?>" /><br />
    <em>0 pro neomezenou kapacitu - přednášky, velké larpy...</em><br /><br />
    <input type="submit" Value="Uložit instanci" />
    </form>
       

<?
}

if (($_REQUEST["akce"] == "aktivity") and ($_POST["detail_akce"] == "instance_upravit2")){

  if ($_POST["zacatek"] == ""){
    $chyba .= "Nevyplněn začátek!<br />";
  }
  if ($_POST["delka"] == ""){
    $chyba .= "Nevyplněna délka!<br />";
  }
  if ($_POST["kapacita"] == ""){
    $chyba .= "Nevyplněna kapacita!<br />";
  }
  if ($chyba == ""){
  
        
    $konec=$_POST["zacatek"]+$_POST["delka"]-1;
    $sql="
      insert into
        akce_seznam
        (patri_pod, nazev_akce, prednasejici,den,zacatek,konec,lokace,kapacita,cena,sleva,typ,rok,stav,instance)
      values
        ($_POST[patri_pod], '$_POST[nazev_akce]', $_POST[prednasejici],$_POST[den],$_POST[zacatek],$konec,$_POST[lokace],$_POST[kapacita],$_POST[cena],$_POST[sleva],$_POST[typ],$_POST[rok],0,1)
    ";
    $sql="
      update
        akce_seznam
      set
        den=$_POST[den],
        zacatek=$_POST[zacatek],
        konec=$konec,
        lokace=$_POST[lokace],
        kapacita=$_POST[kapacita]
      where
        id_akce=$_POST[cislo_aktivity] 
    ";
    
    dbQuery($db_jmeno,$sql,$db_spojeni);
    //echo $sql."<br />";
    echo "<br /><strong>Instance upravena</strong><br />";
  }
  else {
    echo "<br /><br /><strong style=\"color: red;\">Chyba!<br />$chyba</strong>";
    ?>
    <h2>Úprava instance</h2>
  <form  method="post" /">
    <input type="hidden" name="akce" value="aktivity" />
    <input type="hidden" name="detail_akce" value="instance_upravit2" />
    <input type="hidden" name="cislo_aktivity" value="<?echo $_POST["cislo_aktivity"]?>">
    <strong>Název aktivity</strong><br />
    <?
    $sql="
    select
        nazev_akce
      , patri_pod
      , prednasejici
      , den
      , zacatek
      , konec
      , lokace
      , kapacita
      , cena
      , typ
    from
      akce_seznam
    where
      id_akce=$_POST[cislo_aktivity]
    ";
    $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    $nazev_akce=mysql_result($result,0,0);
    $patri_pod=mysql_result($result,0,1);
    $prednasejici=mysql_result($result,0,2);
    $den=mysql_result($result,0,3);
    $zacatek=mysql_result($result,0,4);
    $konec=mysql_result($result,0,5);
    $lokace=mysql_result($result,0,6);
    $kapacita=mysql_result($result,0,7);
    $cena=mysql_result($result,0,8);
    $typ=mysql_result($result,0,9);
    
    $sql="
    select
      obsah_stranky
    from
      stranky_obsah
    where
      id_stranky=$patri_pod
    ";
    $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    $obsah=mysql_result($result,0,0);
    ?>
     
    <input type="hidden" name="patri_pod" value="<?echo $_POST["patri_pod"]?>" />
    <input type="hidden" name="nazev_akce" value="<?echo $_POST["nazev_akce"]?>" />
    <input type="hidden" name="prednasejici" value="<?echo $_POST["prednasejici"]?>" />
    <input type="hidden" name="cena" value="<?echo $_POST["cena"]?>" />
    <input type="hidden" name="sleva" value="1" />
    <input type="hidden" name="typ" value="<?echo $_POST["typ"]?>" />
    <input type="hidden" name="rok" value="<?echo var_getvalue_sn('rok')?>" />
    <input type="hidden" name="stav" value="1" />
    
    <input type="text" name="nazev_stranky" style="width: 250px" value="<?echo $_POST["nazev_akce"]?>" readonly="readonly"/>  <br />

    <br />
    <strong>Lokace</strong><br />
    <em>místo, kde se lokace koná. Při výběru "Nemá lokaci!" se aktivita nezobrazí v programu (možno použít pro systémové aktivity jako např. registrace...)</em><br />
    <select name="lokace" style="width: 250px">
      <option value="0">Nemá lokaci!</option>
    <?
    $sql="
      select
          id_lokace
        , nazev
      from
        akce_lokace
      order by poradi
    ";
    $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    if (mysql_num_rows($result)){
      while ($zaznam=mysql_fetch_row($result)){
        if ($_POST["lokace"] == $zaznam[0]){
          $radek_lokace="selected=\"selected\"";
        }
        else {
          $radek_lokace="";
        }
        echo "<option value=\"$zaznam[0]\" $radek_lokace>$zaznam[1]</option>";
      }
    }
    ?>
    </select>
    <br />
    
    <strong>Den, začátek a délka instance</strong><br />
    <select name="den"  style="width: 250px">
      <option value="0" <? if ($_POST["den"] == 0){echo "selected=\"selected\"";} ?>>nezobrazovat čas instance</option>
      <option value="1" <? if ($_POST["den"] == 1){echo "selected=\"selected\"";} ?>>čtvrtek</option>
      <option value="2" <? if ($_POST["den"] == 2){echo "selected=\"selected\"";} ?>>pátek</option>
      <option value="3" <? if ($_POST["den"] == 3){echo "selected=\"selected\"";} ?>>sobota</option>
      <option value="4" <? if ($_POST["den"] == 4){echo "selected=\"selected\"";} ?>>neděle</option>
    </select><br />
    <strong>Začátek</strong><br />
    <input type="text" name="zacatek" style="width: 50px;" value="<?echo $zacatek?>" /><br />
    <strong>Délka aktivity</strong><br />
    <input type="text" name="delka"  style="width: 50px;" value="<?echo ($_POST["konec"] - $_POST["zacatek"] + 1)?>" /><br />
    <em>Den - nezobrazovat - aktivita se zobrazí na webu s poznámkou "čas ještě není znám" a nebude možné se na ní registrovat<br />
    Začátek - hodina začátku, pouze celé hodiny, rozmezí 8 - 23<br />
    Délka - v celých hodinách (min. 1)<br /> 
    Pozn - při vyplnění začátku na 0 a délky akce na 1 (pozor, den musí být vyplněn jinak než na "Nezobrazovat") se akce vypíše bez času a zobrazí se hláška "akce na přání (viz níže)"</em><br /><br />
    <strong>Kapacita aktivity</strong><br />
    <input type="text" name="kapacita"  value="<?echo $_POST["kapacita"]?>" /><br />
    <em>0 pro neomezenou kapacitu - přednášky, velké larpy...</em><br /><br />
    <input type="submit" Value="Uložit instanci" />
    </form>  
    <?
    
  }
}

if (($_REQUEST["akce"] == "aktivity") and ($_POST["detail_akce"] == "instance")){
?>
<h2>Vytvoření instance z aktivity</h2>
  <form  method="post" /">
    <input type="hidden" name="akce" value="aktivity" />
    <input type="hidden" name="detail_akce" value="instance2" />
    <input type="hidden" name="cislo_aktivity" value="<?echo $_POST["cislo_aktivity"]?>">
    <strong>Název aktivity</strong><br />
    <?
    $sql="
    select
        nazev_akce
      , patri_pod
      , prednasejici
      , den
      , zacatek
      , konec
      , lokace
      , kapacita
      , cena
      , typ
    from
      akce_seznam
    where
      id_akce=$_POST[cislo_aktivity]
    ";
    $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    $nazev_akce=mysql_result($result,0,0);
    $patri_pod=mysql_result($result,0,1);
    $prednasejici=mysql_result($result,0,2);
    $den=mysql_result($result,0,3);
    $zacatek=mysql_result($result,0,4);
    $konec=mysql_result($result,0,5);
    $lokace=mysql_result($result,0,6);
    $kapacita=mysql_result($result,0,7);
    $cena=mysql_result($result,0,8);
    $typ=mysql_result($result,0,9);
    
    $sql="
    select
      obsah_stranky
    from
      stranky_obsah
    where
      id_stranky=$patri_pod
    ";
    $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    $obsah=mysql_result($result,0,0);
    ?>
    <input type="hidden" name="id_akce" value="<?echo $_POST["cislo_aktivity"]?>" />
      
    <input type="hidden" name="patri_pod" value="<?echo $patri_pod?>" />
    <input type="hidden" name="nazev_akce" value="<?echo $nazev_akce?>" />
    <input type="hidden" name="prednasejici" value="<?echo $prednasejici?>" />
    <input type="hidden" name="cena" value="<?echo $cena?>" />
    <input type="hidden" name="sleva" value="1" />
    <input type="hidden" name="typ" value="<?echo $typ?>" />
    <input type="hidden" name="rok" value="<?echo var_getvalue_sn('rok')?>" />
    <input type="hidden" name="stav" value="1" />
    
    <input type="text" name="nazev_stranky" style="width: 250px" value="<?echo $nazev_akce?>" readonly="readonly"/>  <br />

    <br />
    <strong>Lokace</strong><br />
    <em>místo, kde se lokace koná. Při výběru "Nemá lokaci!" se aktivita nezobrazí v programu (možno použít pro systémové aktivity jako např. registrace...)</em><br />
    <select name="lokace" style="width: 250px">
      <option value="0">Nemá lokaci!</option>
    <?
    $sql="
      select
          id_lokace
        , nazev
      from
        akce_lokace
      order by poradi
    ";
    $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    if (mysql_num_rows($result)){
      while ($zaznam=mysql_fetch_row($result)){
        if ($lokace == $zaznam[0]){
          $radek_lokace="selected=\"selected\"";
        }
        else {
          $radek_lokace="";
        }
        echo "<option value=\"$zaznam[0]\" $radek_lokace>$zaznam[1]</option>";
      }
    }
    ?>
    </select>
    <br />
    
    <strong>Den, začátek a délka instance</strong><br />
    <select name="den"  style="width: 250px">
      <option value="0" <? if ($den == 0){echo "selected=\"selected\"";} ?>>nezobrazovat čas instance</option>
      <option value="1" <? if ($den == 1){echo "selected=\"selected\"";} ?>>čtvrtek</option>
      <option value="2" <? if ($den == 2){echo "selected=\"selected\"";} ?>>pátek</option>
      <option value="3" <? if ($den == 3){echo "selected=\"selected\"";} ?>>sobota</option>
      <option value="4" <? if ($den == 4){echo "selected=\"selected\"";} ?>>neděle</option>
    </select><br />
    <strong>Začátek</strong><br />
    <input type="text" name="zacatek" style="width: 50px;" value="<?echo $zacatek?>" /><br />
    <strong>Délka aktivity</strong><br />
    <input type="text" name="delka"  style="width: 50px;" value="<?echo ($konec - $zacatek + 1)?>" /><br />
    <em>Den - nezobrazovat - aktivita se zobrazí na webu s poznámkou "čas ještě není znám" a nebude možné se na ní registrovat<br />
    Začátek - hodina začátku, pouze celé hodiny, rozmezí 8 - 23<br />
    Délka - v celých hodinách (min. 1)<br /> 
    Pozn - při vyplnění začátku na 0 a délky akce na 1 (pozor, den musí být vyplněn jinak než na "Nezobrazovat") se akce vypíše bez času a zobrazí se hláška "akce na přání (viz níže)"</em><br /><br />
    <strong>Kapacita aktivity</strong><br />
    <input type="text" name="kapacita"  value="<?echo $kapacita?>" /><br />
    <em>0 pro neomezenou kapacitu - přednášky, velké larpy...</em><br /><br />
    <input type="submit" Value="Uložit instanci" />
    </form>
       

<?
}

if (($_REQUEST["akce"] == "aktivity") and ($_POST["detail_akce"] == "instance2")){

  if ($_POST["zacatek"] == ""){
    $chyba .= "Nevyplněn začátek!<br />";
  }
  if ($_POST["delka"] == ""){
    $chyba .= "Nevyplněna délka!<br />";
  }
  if ($_POST["kapacita"] == ""){
    $chyba .= "Nevyplněna kapacita!<br />";
  }
  if ($chyba == ""){
  
        
    $konec=$_POST["zacatek"]+$_POST["delka"]-1;
    $sql="
      insert into
        akce_seznam
        (patri_pod, nazev_akce, prednasejici,den,zacatek,konec,lokace,kapacita,cena,sleva,typ,rok,stav,instance)
      values
        ($_POST[patri_pod], '$_POST[nazev_akce]', $_POST[prednasejici],$_POST[den],$_POST[zacatek],$konec,$_POST[lokace],$_POST[kapacita],$_POST[cena],$_POST[sleva],$_POST[typ],$_POST[rok],0,1)
    ";
    dbQuery($db_jmeno,$sql,$db_spojeni);
    //echo $sql."<br />";
    echo "<br /><strong>Instance vložena</strong><br />";
  }
  else {
    echo "<br /><br /><strong style=\"color: red;\">Chyba!<br />$chyba</strong>";
    ?>
    <h2>Vytvoření instance z aktivity</h2>
  <form  method="post" /">
    <input type="hidden" name="akce" value="aktivity" />
    <input type="hidden" name="detail_akce" value="instance2" />
    <input type="hidden" name="cislo_aktivity" value="<?echo $_POST["cislo_aktivity"]?>">
    <strong>Název aktivity</strong><br />
    <?
    $sql="
    select
        nazev_akce
      , patri_pod
      , prednasejici
      , den
      , zacatek
      , konec
      , lokace
      , kapacita
      , cena
      , typ
    from
      akce_seznam
    where
      id_akce=$_POST[cislo_aktivity]
    ";
    $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    $nazev_akce=mysql_result($result,0,0);
    $patri_pod=mysql_result($result,0,1);
    $prednasejici=mysql_result($result,0,2);
    $den=mysql_result($result,0,3);
    $zacatek=mysql_result($result,0,4);
    $konec=mysql_result($result,0,5);
    $lokace=mysql_result($result,0,6);
    $kapacita=mysql_result($result,0,7);
    $cena=mysql_result($result,0,8);
    $typ=mysql_result($result,0,9);
    
    $sql="
    select
      obsah_stranky
    from
      stranky_obsah
    where
      id_stranky=$patri_pod
    ";
    $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    $obsah=mysql_result($result,0,0);
    ?>
     
    <input type="hidden" name="patri_pod" value="<?echo $_POST["patri_pod"]?>" />
    <input type="hidden" name="nazev_akce" value="<?echo $_POST["nazev_akce"]?>" />
    <input type="hidden" name="prednasejici" value="<?echo $_POST["prednasejici"]?>" />
    <input type="hidden" name="cena" value="<?echo $_POST["cena"]?>" />
    <input type="hidden" name="sleva" value="1" />
    <input type="hidden" name="typ" value="<?echo $_POST["typ"]?>" />
    <input type="hidden" name="rok" value="<?echo var_getvalue_sn('rok')?>" />
    <input type="hidden" name="stav" value="1" />
    
    <input type="text" name="nazev_stranky" style="width: 250px" value="<?echo $_POST["nazev_akce"]?>" readonly="readonly"/>  <br />

    <br />
    <strong>Lokace</strong><br />
    <em>místo, kde se lokace koná. Při výběru "Nemá lokaci!" se aktivita nezobrazí v programu (možno použít pro systémové aktivity jako např. registrace...)</em><br />
    <select name="lokace" style="width: 250px">
      <option value="0">Nemá lokaci!</option>
    <?
    $sql="
      select
          id_lokace
        , nazev
      from
        akce_lokace
      order by poradi
    ";
    $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    if (mysql_num_rows($result)){
      while ($zaznam=mysql_fetch_row($result)){
        if ($_POST["lokace"] == $zaznam[0]){
          $radek_lokace="selected=\"selected\"";
        }
        else {
          $radek_lokace="";
        }
        echo "<option value=\"$zaznam[0]\" $radek_lokace>$zaznam[1]</option>";
      }
    }
    ?>
    </select>
    <br />
    
    <strong>Den, začátek a délka instance</strong><br />
    <select name="den"  style="width: 250px">
      <option value="0" <? if ($_POST["den"] == 0){echo "selected=\"selected\"";} ?>>nezobrazovat čas instance</option>
      <option value="1" <? if ($_POST["den"] == 1){echo "selected=\"selected\"";} ?>>čtvrtek</option>
      <option value="2" <? if ($_POST["den"] == 2){echo "selected=\"selected\"";} ?>>pátek</option>
      <option value="3" <? if ($_POST["den"] == 3){echo "selected=\"selected\"";} ?>>sobota</option>
      <option value="4" <? if ($_POST["den"] == 4){echo "selected=\"selected\"";} ?>>neděle</option>
    </select><br />
    <strong>Začátek</strong><br />
    <input type="text" name="zacatek" style="width: 50px;" value="<?echo $zacatek?>" /><br />
    <strong>Délka aktivity</strong><br />
    <input type="text" name="delka"  style="width: 50px;" value="<?echo ($_POST["konec"] - $_POST["zacatek"] + 1)?>" /><br />
    <em>Den - nezobrazovat - aktivita se zobrazí na webu s poznámkou "čas ještě není znám" a nebude možné se na ní registrovat<br />
    Začátek - hodina začátku, pouze celé hodiny, rozmezí 8 - 23<br />
    Délka - v celých hodinách (min. 1)<br /> 
    Pozn - při vyplnění začátku na 0 a délky akce na 1 (pozor, den musí být vyplněn jinak než na "Nezobrazovat") se akce vypíše bez času a zobrazí se hláška "akce na přání (viz níže)"</em><br /><br />
    <strong>Kapacita aktivity</strong><br />
    <input type="text" name="kapacita"  value="<?echo $_POST["kapacita"]?>" /><br />
    <em>0 pro neomezenou kapacitu - přednášky, velké larpy...</em><br /><br />
    <input type="submit" Value="Uložit instanci" />
    </form>  
    <?
    
  }
}


if (($_REQUEST["akce"] == "aktivity") and ($_POST["detail_akce"] == "upravit")){
?>
<h2>Úprava aktivity</h2>
  <form  method="post" /">
    <input type="hidden" name="akce" value="aktivity" />
    <input type="hidden" name="detail_akce" value="upravit2" />
    <strong>Název aktivity</strong><br />
    <?
    $sql="
    select
        nazev_akce
      , patri_pod
      , prednasejici
      , den
      , zacatek
      , konec
      , lokace
      , kapacita
      , cena
      , typ
    from
      akce_seznam
    where
      id_akce=$_POST[cislo_aktivity]
    ";
    $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    $nazev_akce=mysql_result($result,0,0);
    $patri_pod=mysql_result($result,0,1);
    $prednasejici=mysql_result($result,0,2);
    $den=mysql_result($result,0,3);
    $zacatek=mysql_result($result,0,4);
    $konec=mysql_result($result,0,5);
    $lokace=mysql_result($result,0,6);
    $kapacita=mysql_result($result,0,7);
    $cena=mysql_result($result,0,8);
    $typ=mysql_result($result,0,9);
    
    $sql="
    select
      obsah_stranky
    from
      stranky_obsah
    where
      id_stranky=$patri_pod
    ";
    $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    $obsah=mysql_result($result,0,0);
    ?>
    <input type="hidden" name="patri_pod" value="<?echo $patri_pod?>" />
    <input type="hidden" name="id_akce" value="<?echo $_POST["cislo_aktivity"]?>" />
    
    <input type="text" name="nazev_stranky" style="width: 250px" value="<?echo $nazev_akce?>" readonly="readonly"/>  <br />
    <strong>Organizátor aktivity</strong><br />
    <em>organizátor může být přidán v administraci: akce-> vložit přednášejícího</em> <br>
    <select name="prednasejici" style="width: 250px">
    <?
    $sql="
      select
          id_prednasejiciho
        , jmeno
      from
        prednasejici_seznam
      where
        aktivni=1
      order by jmeno
    ";
    $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    if (mysql_num_rows($result)){
      while ($zaznam=mysql_fetch_row($result)){
        if ($prednasejici == $zaznam[0]){
          $radek_prednasejici="selected=\"selected\"";
        }
        else {
          $radek_prednasejici="";
        }
        echo "<option value=\"$zaznam[0]\" $radek_prednasejici >$zaznam[1]</option>";
      }
    }
    ?>
    </select>
    <br />
    <strong>Lokace</strong><br />
    <em>místo, kde se lokace koná. Při výběru "Nemá lokaci!" se aktivita nezobrazí v programu (možno použít pro systémové aktivity jako např. registrace...)</em><br />
    <select name="lokace" style="width: 250px">
      <option value="0">Nemá lokaci!</option>
    <?
    $sql="
      select
          id_lokace
        , nazev
      from
        akce_lokace
      order by poradi
    ";
    $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    if (mysql_num_rows($result)){
      while ($zaznam=mysql_fetch_row($result)){
        if ($lokace == $zaznam[0]){
          $radek_lokace="selected=\"selected\"";
        }
        else {
          $radek_lokace="";
        }
        echo "<option value=\"$zaznam[0]\" $radek_lokace>$zaznam[1]</option>";
      }
    }
    ?>
    </select>
    <br />
    
    <strong>Den, začátek a délka aktivity</strong><br />
    <select name="den"  style="width: 250px">
      <option value="0" <? if ($den == 0){echo "selected=\"selected\"";} ?>>nezobrazovat čas aktivity</option>
      <option value="1" <? if ($den == 1){echo "selected=\"selected\"";} ?>>čtvrtek</option>
      <option value="2" <? if ($den == 2){echo "selected=\"selected\"";} ?>>pátek</option>
      <option value="3" <? if ($den == 3){echo "selected=\"selected\"";} ?>>sobota</option>
      <option value="4" <? if ($den == 4){echo "selected=\"selected\"";} ?>>neděle</option>
    </select><br />
    <strong>Začátek</strong><br />
    <input type="text" name="zacatek" style="width: 50px;" value="<?echo $zacatek?>" /><br />
    <strong>Délka aktivity</strong><br />
    <input type="text" name="delka"  style="width: 50px;" value="<?echo ($konec - $zacatek + 1)?>" /><br />
    <em>Den - nezobrazovat - aktivita se zobrazí na webu s poznámkou "čas ještě není znám" a nebude možné se na ní registrovat<br />
    Začátek - hodina začátku, pouze celé hodiny, rozmezí 8 - 23<br />
    Délka - v celých hodinách (min. 1)<br /> 
    Pozn - při vyplnění začátku na 0 a délky akce na 1 (pozor, den musí být vyplněn jinak než na "Nezobrazovat") se akce vypíše bez času a zobrazí se hláška "akce na přání (viz níže)"</em><br /><br />
    <strong>Kapacita aktivity</strong><br />
    <input type="text" name="kapacita"  value="<?echo $kapacita?>" /><br />
    <em>0 pro neomezenou kapacitu - přednášky, velké larpy...</em><br />
    <strong>Cena aktivity</strong><br />
    <input type="text" name="cena"  value="<?echo $cena?>"  style="width: 50px;" /><br />
    <em>plná cena, slevy se dopočítavají automaticky, 0 pro akce bez ceny (přednášky...)</em><br /><br />
    <strong>Typ aktivity</strong><br />
    <select name="typ" style="width: 250px" disabled="disabled">
      <option value="0" <? if ($typ == 0){echo "selected=\"selected\"";} ?>>žádný (systémové využití)</option>
      <option value="1" <? if ($typ == 1){echo "selected=\"selected\"";} ?>>Deskovka</option>
      <option value="2" <? if ($typ == 2){echo "selected=\"selected\"";} ?>>Larp</option>
      <option value="3" <? if ($typ == 3){echo "selected=\"selected\"";} ?>>Přednáška</option>
      <option value="4" <? if ($typ == 4){echo "selected=\"selected\"";} ?>>RPG</option>
      <option value="5" <? if ($typ == 5){echo "selected=\"selected\"";} ?>>Workshop</option>
      <option value="6" <? if ($typ == 6){echo "selected=\"selected\"";} ?>>Wargaming</option>
      <option value="7" <? if ($typ == 6){echo "selected=\"selected\"";} ?>>Bonusy</option>
    </select><br />
    <em>typ aktivity, aby systém věděl, kde vytvořit stránku s informacemi</em>
    <br />    
    
    <strong>Vlastní text aktivity:</strong><br />
    <textarea name="obsah" style="width: 700px; height: 300px; scroll: auto;"><?echo $obsah?>
    </textarea>
    <br />
    <input type="submit" value="Uložit aktivitu"> <br /><br />
  </form>  
    
    <strong>Vzor pro psaní aktivity</strong><br />
      &lt;p style="clear: both;"&gt;První odstavec textu&lt;/p&gt; - všechny texty oddělovat do logických odstavců pomocí &lt;p&gt; a &lt;/p&gt;<br />
      &lt;p&gt;Druhý odstavec textu&lt;/p&gt; - všechny texty oddělovat do logických odstavců pomocí &lt;p&gt; a &lt;/p&gt;<br /> 
      &lt;p&gt;<br />
      &nbsp;&nbsp;Lorem ipsum donor...(třetí odstavec)<br />
      &lt;/p&gt; - text uvozený v tagu odstavce &lt;/p&gt;<br /><br />
      
      &lt;p&gt;<br />
      &nbsp;&nbsp;&lt;ul&gt;<br /> - na konci vzdy nechat otevreny tag UL! Takze i kdyz zadnou odrazku nechci, tak tady nechat &lt;p&gt;&lt;ul&gt; (vypisuje se do nej navigace mezi aktivitami)<br>  
      &nbsp;&nbsp;&nbsp;&nbsp;&lt;li&gt;odrážka1 (třeba více informací o aktivitě, rpg...)&lt;/li&gt;<br />
      &nbsp;&nbsp;&nbsp;&nbsp;&lt;li&gt;odrážka1&lt;/li&gt;<br />
      &nbsp;&nbsp;&nbsp;&nbsp;&lt;li&gt;odrážka1&lt;/li&gt;- odrážky pomocí GC kostiček<br /><br />
    
      <br />
      &nbsp;&nbsp;Vnitřní odkazy &lt;a href="/novinky"&gt;relativní adresou&lt;/a&gt; a vnější odkazy &lt;a href="http://www.elden.cz" onclick="window.open(this.href,'_blank'); return false"&gt;javascriptem&lt;/a&gt;<br />
      <br /> 


<?
}

if (($_REQUEST["akce"] == "aktivity") and ($_POST["detail_akce"] == "upravit2")){
  switch ($_POST["typ"]){
  case 1: $patri_pod=39;//deskovka
  break;
  case 2: $patri_pod=38;//larp
  break;
  case 3: $patri_pod=40;//přednáška
  break;
  case 4: $patri_pod=37;//rpg
  break;
  case 5: $patri_pod=42;//dilny
  break;
  case 6: $patri_pod=245;//wargaming
  break;
  case 7: $patri_pod=332;//bonusy
  break;
  }

  if ($_POST["nazev_stranky"] == ""){
    $chyba .= "Nevyplněn název aktivity (název stránky)!<br />";
  }
  if ($_POST["zacatek"] == ""){
    $chyba .= "Nevyplněn začátek!<br />";
  }
  if ($_POST["delka"] == ""){
    $chyba .= "Nevyplněna délka!<br />";
  }
  if ($_POST["kapacita"] == ""){
    $chyba .= "Nevyplněna kapacita!<br />";
  }
  if ($_POST["cena"] == ""){
    $chyba .= "Nevyplněna cena!<br />";
  }
  if ($_POST["typ"] == "0"){
    $chyba .= "Nevyplněn typ akce!<br />";
  }
  
  if ($chyba == ""){
  
        
    $sql="update stranky_obsah set obsah_stranky=\"$_POST[obsah]\" where id_stranky=$_POST[patri_pod]";
    dbQuery($db_jmeno,$sql,$db_spojeni);
    //echo $sql."<br />";
    
    $konec=$_POST["zacatek"]+$_POST["delka"]-1;
    
    $sql="update
              akce_seznam
            set 
              prednasejici=$_POST[prednasejici],
              den=$_POST[den],
              zacatek=$_POST[zacatek],
              konec=$konec,
              lokace=$_POST[lokace],
              kapacita=$_POST[kapacita],
              cena=$_POST[cena]
            where
             id_akce=$_POST[id_akce]";
    dbQuery($db_jmeno,$sql,$db_spojeni);
    //echo $sql."<br />";
    
    echo "<br /><strong>Aktivita změněna</strong><br />";
  }
  else {
    echo "<br /><br /><strong style=\"color: red;\">Chyba!<br />$chyba</strong>";
    ?>
    <h2>Vytvoření aktivity</h2>
  <form  method="post" /">
    <input type="hidden" name="akce" value="aktivity" />
    <input type="hidden" name="detail_akce" value="upravit2" />
    <input type="hidden" name="patri_pod" value="<?echo $_POST["patri_pod"]?>" />
    <input type="hidden" name="id_akce" value="<?echo $_POST["id_akce"]?>" />
    <strong>Název aktivity</strong><br />
    <em>název aktivity je zároveň východiskem pro zklácený název stránky na webu, musí být jedinečný</em><br />
    <input type="text" name="nazev_stranky" style="width: 250px" value="<?echo $_POST["nazev_stranky"]?>" readonly="readonly" />  <br />
    <strong>Organizátor aktivity</strong><br />
    <em>organizátor může být přidán v administraci: akce-> vložit přednášejícího</em> <br>
    <select name="prednasejici" style="width: 250px">
    <?
    $sql="
      select
          id_prednasejiciho
        , jmeno
      from
        prednasejici_seznam
      where
        aktivni=1
      order by jmeno
    ";
    $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    if (mysql_num_rows($result)){
      while ($zaznam=mysql_fetch_row($result)){
        if ($_POST["prednasejici"] == $zaznam[0]){
          $radek_prednasejici="selected=\"selected\"";
        }
        else {
          $radek_prednasejici="";
        }
        echo "<option value=\"$zaznam[0]\" $radek_prednasejici >$zaznam[1]</option>";
      }
    }
    ?>
    </select>
    <br />
    <strong>Lokace</strong><br />
    <em>místo, kde se lokace koná. Při výběru "Nemá lokaci!" se aktivita nezobrazí v programu (možno použít pro systémové aktivity jako např. registrace...)</em><br />
    <select name="lokace" style="width: 250px">
      <option value="0">Nemá lokaci!</option>
    <?
    $sql="
      select
          id_lokace
        , nazev
      from
        akce_lokace
      order by poradi
    ";
    $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    if (mysql_num_rows($result)){
      while ($zaznam=mysql_fetch_row($result)){
        if ($_POST["lokace"] == $zaznam[0]){
          $radek_lokace="selected=\"selected\"";
        }
        else {
          $radek_lokace="";
        }
        echo "<option value=\"$zaznam[0]\" $radek_lokace>$zaznam[1]</option>";
      }
    }
    ?>
    </select>
    <br />
    
    <strong>Den, začátek a délka aktivity</strong><br />
    <select name="den"  style="width: 250px">
      <option value="0" <? if ($_POST["den"] == 0){echo "selected=\"selected\"";} ?>>nezobrazovat čas aktivity</option>
      <option value="1" <? if ($_POST["den"] == 1){echo "selected=\"selected\"";} ?>>čtvrtek</option>
      <option value="2" <? if ($_POST["den"] == 2){echo "selected=\"selected\"";} ?>>pátek</option>
      <option value="3" <? if ($_POST["den"] == 3){echo "selected=\"selected\"";} ?>>sobota</option>
      <option value="4" <? if ($_POST["den"] == 4){echo "selected=\"selected\"";} ?>>neděle</option>
    </select><br />
    <strong>Začátek</strong><br />
    <input type="text" name="zacatek" style="width: 50px;" value="<?echo $_POST["zacatek"]?>" /><br />
    <strong>Délka aktivity</strong><br />
    <input type="text" name="delka"  style="width: 50px;" value="<?echo $_POST["delka"]?>" /><br />
    <em>Den - nezobrazovat - aktivita se zobrazí na webu s poznámkou "čas ještě není znám" a nebude možné se na ní registrovat<br />
    Začátek - hodina začátku, pouze celé hodiny, rozmezí 8 - 23<br />
    Délka - v celých hodinách (min. 1)<br /> 
    Pozn - při vyplnění začátku na 0 a délky akce na 1 (pozor, den musí být vyplněn jinak než na "Nezobrazovat") se akce vypíše bez času a zobrazí se hláška "akce na přání (viz níže)"</em><br /><br />
    <strong>Kapacita aktivity</strong><br />
    <input type="text" name="kapacita"  value="<?echo $_POST["kapacita"]?>" /><br />
    <em>0 pro neomezenou kapacitu - přednášky, velké larpy...</em><br />
    <strong>Cena aktivity</strong><br />
    <input type="text" name="cena"  value="<?echo $_POST["cena"]?>"  style="width: 50px;" /><br />
    <em>plná cena, slevy se dopočítavají automaticky, 0 pro akce bez ceny (přednášky...)</em><br /><br />
    <strong>Typ aktivity</strong><br />
    <select name="typ" style="width: 250px" disabled="disabled">
      <option value="0" <? if ($_POST["typ"] == 0){echo "selected=\"selected\"";} ?>>žádný (systémové využití)</option>
      <option value="1" <? if ($_POST["typ"] == 1){echo "selected=\"selected\"";} ?>>Deskovka</option>
      <option value="2" <? if ($_POST["typ"] == 2){echo "selected=\"selected\"";} ?>>Larp</option>
      <option value="3" <? if ($_POST["typ"] == 3){echo "selected=\"selected\"";} ?>>Přednáška</option>
      <option value="4" <? if ($_POST["typ"] == 4){echo "selected=\"selected\"";} ?>>RPG</option>
      <option value="5" <? if ($_POST["typ"] == 5){echo "selected=\"selected\"";} ?>>Workshop</option>
      <option value="6" <? if ($_POST["typ"] == 6){echo "selected=\"selected\"";} ?>>Wargaming</option>
      <option value="7" <? if ($_POST["typ"] == 7){echo "selected=\"selected\"";} ?>>Bonusy</option>
    </select><br />
    <em>typ aktivity, aby systém věděl, kde vytvořit stránku s informacemi</em>
    <br />    
    
    <strong>Vlastní text aktivity:</strong><br />
    <textarea name="obsah" style="width: 700px; height: 300px; scroll: auto;"><?echo $_POST["obsah"]?>
    </textarea>
    <br />
    <input type="submit" value="Uložit aktivitu"> <br /><br />
  </form>  
    
    <strong>Vzor pro psaní aktivity</strong><br />
      &lt;p style="clear: both;"&gt;První odstavec textu&lt;/p&gt; - všechny texty oddělovat do logických odstavců pomocí &lt;p&gt; a &lt;/p&gt;<br />
      &lt;p&gt;Druhý odstavec textu&lt;/p&gt; - všechny texty oddělovat do logických odstavců pomocí &lt;p&gt; a &lt;/p&gt;<br /> 
      &lt;p&gt;<br />
      &nbsp;&nbsp;Lorem ipsum donor...(třetí odstavec)<br />
      &lt;/p&gt; - text uvozený v tagu odstavce &lt;/p&gt;<br /><br />
      
      &lt;p&gt;<br />
      &nbsp;&nbsp;&lt;ul&gt;<br /> - na konci vzdy nechat otevreny tag UL! Takze i kdyz zadnou odrazku nechci, tak tady nechat &lt;p&gt;&lt;ul&gt; (vypisuje se do nej navigace mezi aktivitami)<br>  
      &nbsp;&nbsp;&nbsp;&nbsp;&lt;li&gt;odrážka1 (třeba více informací o aktivitě, rpg...)&lt;/li&gt;<br />
      &nbsp;&nbsp;&nbsp;&nbsp;&lt;li&gt;odrážka1&lt;/li&gt;<br />
      &nbsp;&nbsp;&nbsp;&nbsp;&lt;li&gt;odrážka1&lt;/li&gt;- odrážky pomocí GC kostiček<br /><br />
    
      <br />
      &nbsp;&nbsp;Vnitřní odkazy &lt;a href="/novinky"&gt;relativní adresou&lt;/a&gt; a vnější odkazy &lt;a href="http://www.elden.cz" onclick="window.open(this.href,'_blank'); return false"&gt;javascriptem&lt;/a&gt;<br />
      <br />  
    <?
    
  }
}

if (($_REQUEST["akce"] == "novinky") and ($_POST["detail_akce"] == "nova_ulozit")){
  echo "<h2>Vytvoření novinky</h2>";
  if (empty($_POST["obsah"])){
    echo "Chyba! Prázdný obsah!";
  }
  else {
    $sql="
    insert into
      novinky_obsah
      (stav,autor,publikoval,publikovano,posledni_zmena,upravil,obsah)
    VALUES
      ('$_POST[stav]',$_SESSION[id_admin],$_SESSION[id_admin],NOW(),NOW(),$_SESSION[id_admin],'$_POST[obsah]')
    ";
    if(dbQuery($db_jmeno,$sql,$db_spojeni)){
      echo "Novinka byla uložena";
      novinky_na_web(5);
    }
    else {
      $error=mysql_error();
      echo "Chyba! Novinka nebyla uložena.<br />$sql<br />$error";
    }
  }
}

if (($_REQUEST["akce"] == "novinky") and ($_POST["detail_akce"] == "upravit")){
  echo "<h2>Úprava novinky</h2>";
  
  $sql="
  select
      stav
    , obsah
  from
    novinky_obsah
  where
    id_novinky=$_POST[cislo_novinky]
  ";
  $result=dbQuery($db_jmeno,$sql,$db_spojeni);
  if (mysql_num_rows($result) <> 1){
    echo "Chyba! Novinka neexistuje nebo tu mámě nějakého hnusného brouka, co ji namnožil!";
  }
  else {  
    $obsah=mysql_result($result,0,1);
    $stav=mysql_result($result,0,0);
  ?>
  <form  method="post" /">
    <input type="hidden" name="akce" value="novinky" />
    <input type="hidden" name="detail_akce" value="upravit_potvrzeno" />
    <input type="hidden" name="cislo_novinky" value="<?echo $_POST["cislo_novinky"]?>" />
    <strong>Stav novinky:</strong><br />
    <select name="stav">
      <option value="P" <? if ($stav == "P"){echo "selected=\"selected\"";} ?> >V přípravě</option>
      <option value="Y" <? if ($stav == "Y"){echo "selected=\"selected\"";} ?> >Publikováno</option>
      <option value="N" <? if ($stav == "N"){echo "selected=\"selected\"";} ?> >Nepublikovat</option>
    </select>
    <br />
    <em>V přípravě</em> - novinka není na webu zobrazena, admini do ní mohou zasahovat<br />
    <em>Publikováno</em> - novinka je na webu<br />
    <em>Nepublikovat</em> - novinka není zobrazena, ani není určena k zobrazení (můžou se do ní ukládat průběžné informace atp.)<br />
    <br />
    <strong>Vlastní text novinky:</strong><br />
    <textarea name="obsah" style="width: 700px; height: 300px; scroll: auto;"><?echo $obsah?></textarea>
    <br />
    <input type="submit" value="Upravit novinku"> <br /><br />
  </form>  
    
    <strong>Vzor pro psaní novinek</strong><br />
      &lt;h2&gt;1.1.2011&lt;/h2&gt; - každá novinka musí mít uvedeno datum v &lt;h2&gt;<br />
      &lt;h3&gt;Nadpis novinky&lt;/h3&gt; - Nadpisy řešeny pomocí &lt;h3&gt;<br /> 
      &lt;p&gt;<br />
      &nbsp;&nbsp;Lorem ipsum donor...<br />
      &lt;/p&gt; - text uvozený v tagu odstavce &lt;/p&gt;<br /><br />
      
      &lt;p&gt;<br />
      &nbsp;&nbsp;&lt;ul&gt;<br />
      &nbsp;&nbsp;&nbsp;&nbsp;&lt;li&gt;odrážka1&lt;/li&gt;<br />
      &nbsp;&nbsp;&nbsp;&nbsp;&lt;li&gt;odrážka1&lt;/li&gt;<br />
      &nbsp;&nbsp;&nbsp;&nbsp;&lt;li&gt;odrážka1&lt;/li&gt;<br />
      &nbsp;&nbsp;&lt;/ul&gt;<br />
      &lt;/p&gt; - odrážky pomocí GC kostiček<br /><br />
    
      &lt;p&gt;<br />
      &nbsp;&nbsp;Vnitřní odkazy &lt;a href="/novinky"&gt;relativní adresou&lt;/a&gt; a vnější odkazy &lt;a href="http://www.elden.cz" onclick="window.open(this.href,'_blank'); return false"&gt;javascriptem&lt;/a&gt;<br />
      &lt;/p&gt;<br />  
  <?
  }
}

if (($_REQUEST["akce"] == "novinky") and ($_POST["detail_akce"] == "upravit_potvrzeno")){
  $sql="
    select
      stav
    from
      novinky_obsah
    where
      id_novinky=$_POST[cislo_novinky]
  ";
  $result=dbQuery($db_jmeno,$sql,$db_spojeni);
  if (mysql_num_rows($result) <> 1){
    echo "Chyba! Novinka neexistuje nebo má dvojče! A možná další sourozence! Aj...";  
  }
  else {
    $stav_puvodni=mysql_result($result,0,0);
    
    if ((($stav_puvodni == "P") || ($stav_puvodni == "N")) && ($_POST["stav"] == "Y")){
      $sql="
      update
        novinky_obsah
      set
          stav='$_POST[stav]'
        , publikovano=NOW()
        , publikoval=$_SESSION[id_admin]
        , posledni_zmena=NOW()
        , upravil=$_SESSION[id_admin]
        , obsah='$_POST[obsah]'
      where
        id_novinky=$_POST[cislo_novinky]
      ";
    }
    else {
      $sql="
      update
        novinky_obsah
      set
          stav='$_POST[stav]'
        , posledni_zmena=NOW()
        , upravil=$_SESSION[id_admin]
        , obsah='$_POST[obsah]'
      where
        id_novinky=$_POST[cislo_novinky]
      ";
    }
    if (dbQuery($db_jmeno,$sql,$db_spojeni)){
      echo "Novinka byla uložena!<br /><br />";
      novinky_na_web(5);
    }
    else {
      $error=mysql_error();
      echo "Chyba! Novinka nebyla uložena!<br />$sql<br />$error";
    }
  }
}

if (($_REQUEST["akce"] == "novinky") and ($_POST["detail_akce"] == "smazat")){
  echo "<h2>Mazání novinky</h2>";
  $sql="
    select
        id_novinky
      , substring(obsah,1,200)
      , date_format(publikovano,'%e.%m.%Y')
    from
      novinky_obsah
    where
      id_novinky=$_POST[cislo_novinky]
  ";
  $result=dbQuery($db_jmeno,$sql,$db_spojeni);
  if (mysql_num_rows($result)){
    $obsah=mysql_result($result,0,1);
    $publikovano=mysql_result($result,0,2);
    if (!empty($publikovano)){
      echo "<p>Opravdu smazat novinku <strong>č. $_POST[cislo_novinky]</strong>, publikovanou dne $publikovano, s obsahem <em>\"$obsah\"</em>?</p>";
    }
    else {
      echo "<p>Opravdu smazat novinku <strong>č. $_POST[cislo_novinky]</strong>, (nepublikovanou), s obsahem <em>\"$obsah\"</em>?</p>";
    }
    ?>
    <p>
    <a href="javascript: document.getElementById('smazat').submit()">Smazat novinku</a> - <a href="javascript: document.getElementById('nemazat').submit()">Nemazat novinku</a>
    <form id="nemazat"  method="post" /><input type="hidden" name="akce" value="novinky"></form>
    <form id="smazat"  method="post" /><input type="hidden" name="akce" value="novinky"><input type="hidden" name="detail_akce" value="smazat_potvrzeno" /><input type="hidden" name="cislo_novinky" value="<?echo $_POST['cislo_novinky'] ?>" /></form>
    </p>
    <?
  }
  else {
    echo "Chyba - novinka nenalezena!";
  }
}

if (($_REQUEST["akce"] == "novinky") and ($_POST["detail_akce"] == "smazat_potvrzeno")){
  $sql="
    delete from
      novinky_obsah
    where
      id_novinky=$_POST[cislo_novinky]
  ";
  if(dbQuery($db_jmeno,$sql,$db_spojeni)){
    echo "Novinka č. $_POST[cislo_novinky] byla smazána!";
    novinky_na_web(5);
  }
  else {
    echo "Chyba, novinka nebyla smazána!";
  }
}
?>


