<?php

/** 
 * Úvodní stránka sloužící pro infopult a další účely. Zajišťuje registraci na
 * DrD, Trojboj, Gamecon, Placení aj. Povětšinou starý kód
 *
 * nazev: Úvod
 * pravo: 100
 */
 
$db_jmeno=$db_spojeni=null;  

//trojboj
function trb_druzina_jmeno ($id_druziny){
GLOBAL $db_jmeno,$db_spojeni;
  $sql="
    select
      nazev
    from
      trb_druziny
    where
      id_druziny=$id_druziny;
  ";
  $result=dbQuery($db_jmeno,$sql,$db_spojeni);
  $vysledek=mysql_result($result,0,0);
  return $vysledek;
}

function trb_dekoduj_spacepat ($spacepat){
  switch ($spacepat){
    case 0: $vysledek="čas zatím nevybrán";
    break;
    case 1: $vysledek="pátek 14:00 - 18:00";
    break;
    case 2: $vysledek="sobota 14:00 - 18:00";
    break;
  }
  return $vysledek;
}

function trb_dekoduj_sifrovacka ($sifrovacka){
  switch ($sifrovacka){
    case 0: $vysledek="čas zatím nevybrán";
    break;
    case 1: $vysledek="pátek 18:00 - 22:00";
    break;
    case 2: $vysledek="sobota 9:00 - 13:00";
    break;
  }
  return $vysledek;
}

function trb_druzina_spravce ($id_druziny){
GLOBAL $db_jmeno,$db_spojeni;
  $sql="
    select
      login_uzivatele
    from
      uzivatele_hodnoty
    where
      id_uzivatele=(
        select
          spravce
        from
          trb_druziny
        where
          id_druziny=$id_druziny
      )
  ";
  $result=dbQuery($db_jmeno,$sql,$db_spojeni);
  $vysledek=mysql_result($result,0,0);
  return $vysledek;
}

function trb_druzina_poznamka ($id_druziny){
GLOBAL $db_jmeno,$db_spojeni;
  $sql="
    select
      poznamka
    from
      trb_druziny
    where
      id_druziny=$id_druziny;
  ";
  $result=dbQuery($db_jmeno,$sql,$db_spojeni);
  $vysledek=mysql_result($result,0,0);
  return $vysledek;
}

function trb_druzina_clenove ($id_druziny){
GLOBAL $db_jmeno,$db_spojeni;
  $sql="
    select
      verejna
    from
      trb_druziny
    where
      id_druziny=$id_druziny;
  ";
  $result=dbQuery($db_jmeno,$sql,$db_spojeni);
  $verejna=1;//mysql_result($result,0,0);
  
  if ($verejna == 1){
    $sql="
      select
        id_uzivatele
      from
        trb_uzivatele_druziny
      where
        id_druziny=$id_druziny;
    ";
    $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    $pocitadlo=1;
    while($zaznam=mysql_fetch_row($result)){
      echo "$pocitadlo. ";
      trb_vypis_clena($zaznam[0]);
      $pocitadlo++;
    }
  }
  else {
    echo "<em>tým je neveřejný</em>";
  }
}



function trb_vypis_clena($id_uzivatele){
GLOBAL $db_jmeno,$db_spojeni;
  $sql="
    select
      uzivatele.login_uzivatele,
      uzivatele.id_uzivatele
    from
      uzivatele_hodnoty uzivatele
    where
      uzivatele.id_uzivatele=$id_uzivatele      
  ";
  //echo $sql;
  $result=dbQuery($db_jmeno,$sql,$db_spojeni);
  echo "
  <strong>id: ".mysql_result($result,0,1)." - ".mysql_result($result,0,0)."</strong>";  
}

function trb_vypis_clena_uvnitr($id_uzivatele){
GLOBAL $db_jmeno,$db_spojeni;
  $sql="
    select
      uzivatele.login_uzivatele,
      uzivatele.id_uzivatele,
      uzivatele.email1_uzivatele
    from
      uzivatele_hodnoty uzivatele
    where
      uzivatele.id_uzivatele=$id_uzivatele      
  ";
  //echo $sql;
  $result=dbQuery($db_jmeno,$sql,$db_spojeni);
  if ((jsem_spravce(moje_druzina_cislo())) && ($id_uzivatele != $_SESSION["id_uzivatele"])){
    $vyluc="(<a href=\"javascript: document.getElementById('predej_spravcovstvi$id_uzivatele').submit()\">předej správcovství</a> / <a href=\"javascript: document.getElementById('vyluc_uzivatele$id_uzivatele').submit()\">vyloučit</a>)</a>";
    ?>
      <form action="<?echo $_SERVER["REQUEST_URI"]?>" id="predej_spravcovstvi<?echo $id_uzivatele?>" method="post">
        <input type="hidden" name="akce" value="predej_spravcovstvi">
        <input type="hidden" name="id_noveho_spravce" value="<?echo $id_uzivatele?>">
      </form>
      <form action="<?echo $_SERVER["REQUEST_URI"]?>" id="vyluc_uzivatele<?echo $id_uzivatele?>" method="post">
        <input type="hidden" name="akce" value="vyluc_uzivatele">
        <input type="hidden" name="id_vylouceneho" value="<?echo $id_uzivatele?>">
      </form>
    <?
  }
  else {
    $vyluc="";
  }
  
  
  echo "
  <li><strong>id: ".mysql_result($result,0,1)." - ".mysql_result($result,0,0)."</strong> - ".mysql_result($result,0,2)." $vyluc";  
}

function trb_prihlas_do_druziny ($id_druziny,$id_uzivatele=0){
GLOBAL $db_jmeno,$db_spojeni;
  if ($id_uzivatele == 0){
    $id_uzivatele=$_SESSION["id_uzivatele"];
  }
  $sql="
    insert into
      trb_uzivatele_druziny
      (id_druziny,id_uzivatele,rok)
    values
      ($id_druziny,$id_uzivatele,".var_getvalue_sn('rok').")
  ";
  //echo $sql;
  dbQuery($db_jmeno,$sql,$db_spojeni);

   $sql="
     delete from
       trb_prihlasky
     where
       id_uzivatele=$id_uzivatele;
    ";
    $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    
}

function trb_vyhod_z_druziny ($id_druziny,$id_uzivatele=0){
GLOBAL $db_jmeno,$db_spojeni;
  if ($id_uzivatele == 0){
    $id_uzivatele=$_SESSION["id_uzivatele"];
  }
  $sql="
      delete from
        trb_uzivatele_druziny
      where
        id_druziny=$id_druziny and
        id_uzivatele=$id_uzivatele
    ";
    //echo $sql;
  dbQuery($db_jmeno,$sql,$db_spojeni);
  odhlas_akci(286,$zaznam[0]);
  odhlas_akci(287,$zaznam[0]);
  //odhlas_akci(87,$zaznam[0]);
  //odhlas_akci(93,$zaznam[0]);
}

function trb_jsem_spravce ($id_druziny,$id_uzivatele=0){
GLOBAL $db_jmeno,$db_spojeni;
  if ($id_uzivatele == 0){
    $id_uzivatele=$_SESSION["id_uzivatele"];
  }
  $sql="
    select
      spravce
    from
      trb_druziny
    where
      id_druziny=$id_druziny
  ";
  $result=dbQuery($db_jmeno,$sql,$db_spojeni);
  $spravce=mysql_result($result,0,0);
  if ($spravce == $id_uzivatele){
    return true;
  }
  else {
    return false;
  }
}

function trb_moje_druzina_cislo ($id_uzivatele=0){
GLOBAL $db_jmeno,$db_spojeni;
  if ($id_uzivatele == 0){
    $id_uzivatele=$_SESSION["id_uzivatele"];
  }
  $sql="
    select
      id_druziny
    from
      trb_uzivatele_druziny
    where
      id_uzivatele=$id_uzivatele
      and rok=".var_getvalue_sn('rok')."
  ";
  $result=dbQuery($db_jmeno,$sql,$db_spojeni);
  $vysledek=mysql_result($result,0,0);
  return $vysledek;
}

//********************************************************trojboj

function druzina_jmeno ($id_druziny){
GLOBAL $db_jmeno,$db_spojeni;
  $sql="
    select
      nazev
    from
      drd_druziny
    where
      id_druziny=$id_druziny;
  ";
  $result=dbQuery($db_jmeno,$sql,$db_spojeni);
  $vysledek=mysql_result($result,0,0);
  return $vysledek;
}

function druzina_pj ($id_druziny){
GLOBAL $db_jmeno,$db_spojeni;
  $sql="
    select
      blok
    from
      drd_druziny
    where
      id_druziny=$id_druziny
  ";
  //echo $sql;
  $result=dbQuery($db_jmeno,$sql,$db_spojeni);
  $blok=mysql_result($result,0,0);
  
  if ($blok > 0){
    $sql="
      select
        jmeno_pj
      from
        drd_pj
      where
        blok$blok=$id_druziny
        and rok=".var_getvalue_sn('rok').";
    ";
    $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    $vysledek=mysql_result($result,0,0);
    if (ma_pravo($_SESSION["id_uzivatele"],999)){
      //echo $sql;
    }
  }
  else {
    $vysledek="nevybrán";
  }
  return $vysledek;
}

function dekoduj_blok ($blok){
  switch ($blok){
    case 0: $vysledek="nevybrán";
    break;
    case 1: $vysledek="dopoledne";
    break;
    case -1: $vysledek="dopoledne";
    break;
    case 2: $vysledek="odpoledne";
    break;
    case -2: $vysledek="odpoledne";
    break;
    case 3: $vysledek="večer";
    break;
    case -3: $vysledek="večer";
    break;
  }
  return $vysledek;
}

function druzina_spravce ($id_druziny){
GLOBAL $db_jmeno,$db_spojeni;
  $sql="
    select
      login_uzivatele
    from
      uzivatele_hodnoty
    where
      id_uzivatele=(
        select
          spravce
        from
          drd_druziny
        where
          id_druziny=$id_druziny
      )
  ";
  $result=dbQuery($db_jmeno,$sql,$db_spojeni);
  $vysledek=mysql_result($result,0,0);
  return $vysledek;
}

function druzina_poznamka ($id_druziny){
GLOBAL $db_jmeno,$db_spojeni;
  $sql="
    select
      poznamka
    from
      drd_druziny
    where
      id_druziny=$id_druziny;
  ";
  $result=dbQuery($db_jmeno,$sql,$db_spojeni);
  $vysledek=mysql_result($result,0,0);
  return $vysledek;
}

function druzina_clenove ($id_druziny){
GLOBAL $db_jmeno,$db_spojeni;
  $sql="
    select
      verejna
    from
      drd_druziny
    where
      id_druziny=$id_druziny;
  ";
  $result=dbQuery($db_jmeno,$sql,$db_spojeni);
  $verejna=mysql_result($result,0,0);
    $sql="
      select
        id_uzivatele
      from
        drd_uzivatele_druziny
      where
        id_druziny=$id_druziny;
    ";
    $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    $pocitadlo=1;
    while($zaznam=mysql_fetch_row($result)){
      echo "$pocitadlo. ";
      vypis_clena($zaznam[0]);
      $pocitadlo++;
    }
}

function vyhod_z_druziny ($id_druziny,$id_uzivatele=0){
GLOBAL $db_jmeno,$db_spojeni;
  if ($id_uzivatele == 0){
    $id_uzivatele=$_SESSION["id_uzivatele"];
  }
  $sql="
      delete from
        drd_uzivatele_druziny
      where
        id_druziny=$id_druziny and
        id_uzivatele=$id_uzivatele
    ";
    //echo $sql;
  dbQuery($db_jmeno,$sql,$db_spojeni);
  odhlas_akci($GLOBALS['ID_AKTIVITA_DRD_BLOK1'],$id_uzivatele);
  odhlas_akci($GLOBALS['ID_AKTIVITA_DRD_BLOK2'],$id_uzivatele);
  odhlas_akci($GLOBALS['ID_AKTIVITA_DRD_BLOK3'],$id_uzivatele);
}

function moje_druzina_cislo ($id_uzivatele=0){
GLOBAL $db_jmeno,$db_spojeni;
  if ($id_uzivatele == 0){
    $id_uzivatele=$_SESSION["id_uzivatele"];
  }
  $sql="
    select
      id_druziny
    from
      drd_uzivatele_druziny
    where
      id_uzivatele=$id_uzivatele
      and rok=".var_getvalue_sn('rok').";
  ";
  //echo $sql;
  $result=dbQuery($db_jmeno,$sql,$db_spojeni);
  $vysledek=mysql_result($result,0,0);
  return $vysledek;
}

function v_druzine($id_uzivatele=0){
GLOBAL $db_jmeno,$db_spojeni;
  if ($id_uzivatele == 0){
    $id_uzivatele=$_SESSION["id_uzivatele"];
  }
  $sql="
    select
      id_prihlaseni
    from
      drd_uzivatele_druziny
    where
      id_uzivatele=$id_uzivatele
      and rok=".var_getvalue_sn('rok').";
  ";
  $result=dbQuery($db_jmeno,$sql,$db_spojeni);
  if (mysql_num_rows($result)>0){
    return true;
  }
  else {
    return false;
  }
}

function trb_v_druzine($id_uzivatele=0){
GLOBAL $db_jmeno,$db_spojeni;
  if ($id_uzivatele == 0){
    $id_uzivatele=$_SESSION["id_uzivatele"];
  }
  $sql="
    select
      id_prihlaseni
    from
      trb_uzivatele_druziny
    where
      id_uzivatele=$id_uzivatele
      and rok=".var_getvalue_sn('rok').";
  ";
  $result=dbQuery($db_jmeno,$sql,$db_spojeni);
  if (mysql_num_rows($result)>0){
    return true;
  }
  else {
    return false;
  }
}

function vypis_clena($id_uzivatele){
GLOBAL $db_jmeno,$db_spojeni;
  $sql="
    select
      uzivatele.login_uzivatele,
      uzivatele.id_uzivatele,
      postava.jmeno,
      postava.rasa,
      postava.povolani,
      uzivatele.jmeno_uzivatele,
      uzivatele.prijmeni_uzivatele
      
    from
      uzivatele_hodnoty uzivatele,
      drd_postava postava
    where
      uzivatele.id_uzivatele=postava.id_uzivatele and
      uzivatele.id_uzivatele=$id_uzivatele 
      and rok=".var_getvalue_sn('rok').";     
  ";
  //echo $sql;
  $result=dbQuery($db_jmeno,$sql,$db_spojeni);
  echo "
  <strong>id: ".mysql_result($result,0,1)." - ".mysql_result($result,0,0)."</strong> - ".mysql_result($result,0,5).".".mysql_result($result,0,6)." - ".dekoduj_rasu(mysql_result($result,0,3))." ".dekoduj_povolani(mysql_result($result,0,4))."<br />";  
}


function dekoduj_rasu ($rasa){
  switch ($rasa){
    case 0: $vysledek="bezrasý ";
    break;
    case 1: $vysledek="člověk";
    break;
    case 2: $vysledek="barbar";
    break;
    case 3: $vysledek="elf";
    break;
    case 4: $vysledek="trpaslík";
    break;
    case 5: $vysledek="kudůk";
    break;
    case 6: $vysledek="hobit";
    break;
    case 7: $vysledek="kroll";
    break;
  }
  return $vysledek;
}

function dekoduj_povolani ($povolani){
  switch ($povolani){
    case 0: $vysledek="neumětel";
    break;
    case 1: $vysledek="bojovník";
    break;
    case 2: $vysledek="šermíř";
    break;
    case 3: $vysledek="čaroděj";
    break;
    case 4: $vysledek="mág";
    break;
    case 5: $vysledek="pyrofor";
    break;
    case 6: $vysledek="theurg";
    break;
    case 7: $vysledek="chodec";
    break;
    case 8: $vysledek="druid";
    break;
    case 9: $vysledek="lupič";
    break;
    case 10: $vysledek="sicco";
    break;
  }
  return $vysledek;
}

?>


<!-- Tady začíná kód obecné úvodní stránky -->
<?php require('uvod-vzdy.hhp'); ?>

<h1>GameCon <?echo var_getvalue_sn('rok')?> - administrace účastníků</h1>

<?
if (empty($_POST["akce"])){
  if (post('vyhledavani') == 1){
    $vyhledavej=true;
    $pridavka='';
    if (is_numeric($_POST["retezec"])){
      $pridavka= "or (id_uzivatele=$_POST[retezec])";
    }
    $sql="
      select distinct
        jmeno_uzivatele,
        prijmeni_uzivatele,
        login_uzivatele,
        id_uzivatele,
        mesto_uzivatele,
        email1_uzivatele,
        datum_narozeni_uzivatele,
        id_uzivatele
      from
        uzivatele_hodnoty
      where
        (prijmeni_uzivatele COLLATE utf8_general_ci like '$_POST[retezec]%') or
        (login_uzivatele COLLATE utf8_general_ci like  '$_POST[retezec]%') or
        (email1_uzivatele COLLATE utf8_general_ci like '$_POST[retezec]%') $pridavka
    ";
    if ($vyhledavej){
      $result=dbQuery($db_jmeno,$sql,$db_spojeni);
      if (mysql_num_rows($result) > 0){
        echo "<table class=\"zvyraznovana\">";
        echo "<tr style=\"font-weight: bold;\">";
        echo "<th>GC ID</th><th>jméno a příjmení</th><th>login</th><th>email</th><th>město</th><th>vybrat pro práci</th>";
        echo "</tr>";
        while($zaznam=mysql_fetch_row($result)){
        echo "<tr>";
        echo "<td>";
        echo $zaznam[3];
        echo "</td>";
        echo "<td>";
        echo $zaznam[0]." ".$zaznam[1];
        echo "</td>";
        echo "<td>";
        echo $zaznam[2];
        echo "</td>";
        echo "<td>";
        echo $zaznam[5];
        echo "</td>";
        echo "<td>";
        echo $zaznam[4];
        echo "</td>";
        echo '<td>
                <form method="post">
                  <input type="hidden" name="uzivatele_vybrat" value="1" />
                  <input type="hidden" name="id_uzivatele" value="'.$zaznam[7].'" />
                  <input type="submit" style="height: 18px; margin: 0" value="Vybrat" />
                </form>
              </td>';
        echo "</tr>";  
        }
        echo "</table>";
      }
      else {
        echo "<strong>Podle zadaných parametrů nebyli nalezeni žádní uživatelé.</strong>";
      }
    }
    else {
      echo "<strong>Nebyla zadána číselná hodnota do položky GC ID.</strong>";
    }
  }
  
  if (!empty($_SESSION["id_uzivatele"])){
    $sql="
      select
        login_uzivatele,
        jmeno_uzivatele,
        prijmeni_uzivatele,
        id_uzivatele
      from
        uzivatele_hodnoty
      where
        id_uzivatele=$_SESSION[id_uzivatele]
      ";
      //echo $sql;
      $result=dbQuery($db_jmeno,$sql,$db_spojeni);
      echo "
        <div class=\"aktiv\"><strong>Aktivní uživatel</strong> » login (jméno a příjmení): <strong>".mysql_result($result,0,0)." (".mysql_result($result,0,1)." ".mysql_result($result,0,2)." - GC ID: ".mysql_result($result,0,3).")</strong> <a href=\"javascript: document.getElementById('zrusit').submit()\">zrušit práci s uživatelem</a> (alt+z)</div>";
      ?>
      <form  method="post" style="display: inline;" id="zrusit">
        <input type="hidden" name="uzivatele_zrusit" value="1" />
      </form>
      <?
      if (isset($chyba_odhlaseni)&&$chyba_odhlaseni==true){
        ?>
        <div class="adm_box" style="border: 5px solid red; background-color: white;;">
          <strong>Tento uživatel nedostal materiály nebo má záporný zústatek na účtu!</strong><br />
          <a href="javascript: document.getElementById('zrusit2').submit()">Ano, i přesto zrušit práci s uživatelem</a> (alt+z)
          <form  method="post" style="display: inline;" id="zrusit2">
            <input type="hidden" name="uzivatele_zrusit" value="2" />
          </form>
        </div>
        <?
      }
      
      if (ma_pravo($_SESSION["id_uzivatele"],$GLOBALS['ID_PRAVO_ORG_SEKCE'])){
        ?>
        <div class="adm_box" style="background-color: skyblue;">
          <strong>Tento uživatel je organizátor!</strong>
        </div>
        <?
      }
      else if(ma_pravo($_SESSION["id_uzivatele"],P_ORG_AKCI))
      {
        ?>
        <div class="adm_box" style="background-color: palegreen;">
          <strong>Tento uživatel je vypravěč!</strong>
        </div>
        <?php
      }
  }
  else {
    
    //test mistrovství, jestli je sprváně přemigrovaný rok
    $a=dbOneLine('SELECT rok FROM akce_seznam WHERE id_akce='.ID_AKTIVITA_DRD);
      if($a['rok']!=ROK)
        echo '<div class="error">Pro aktuální rok není vytvořena aktivita mistrovství v DrD. Je potřeba vytvořit strom mistrovství DrD v aktivitách a zanést daná ID do souboru /sdilene/constants.hhp.</div>';
        //@todo nabídnout údržbový skript
    
    ?>
    
    <!-- nový výběr -->
    <?php require('uvod-omni.hhp'); ?>
    
    <!-- starý výběr -->
    <!--
    <div class="adm_box">
      <strong>Žádný uživatel není vybrán pro práci. Uživatele můžeš vyhledat podle příjmení, přezdívky, emailu nebo jeho id.</strong><br />
      <br /><form  method="post">
        <input type="hidden" name="vyhledavani" value="1"/>
        <strong>Vyhledat:</strong> <input type="text" name="retezec" style="width: 250px;" />
        <input type="submit" value="Vyhledávat" />
      </form>
    </div>
    -->
    
    <div class="adm_box">
      <h3>Prodej - věci k prodeji</h3>
      <table class="invisible_table">
      <tr>
          <th style="padding: 3px;">název artefaktu</th>
          <th style="padding: 3px;">cena</th>
          <th style="padding: 3px;">zbývající počet</th>
      </tr>                                  
      <?
      $sql="
      select
        artefakt,
        cena,
        pocet
      from
        prodej_artefakty
      where
        rok=".var_getvalue_sn('rok').";        
      ";
      $result=dbQuery($sql);
      while($zaznam=mysql_fetch_row($result)){
        $artefakt=$zaznam[0];
        $cena=$zaznam[1];
        $pocet=$zaznam[2];
        if ($pocet == 0){
          $pridavek="style=\"color: grey;\"";
        }
        else {
          $pridavek="";
        }
        if ($pocet == -1){
          $pocet="neomezeně";
        }
        echo"
        <tr $pridavek>
          <td>$artefakt</td>
          <td>$cena</td>
          <td>$pocet</td>
        </tr>
        ";
      }      
      ?>
      </table>
      <?php
        if(mysql_num_rows($result)==0)
          echo '<div class="error">Pro aktuální rok nejsou vytvořeny žádné věci k prodeji.</div>';
      ?>
      <h3>Prodat</h3>
      <strong>Artefakt:</strong><br />
      <form  method="post" name="registrace">
        <input type="hidden" name="akce" value="prodej">
        <select name="id_artefaktu" style="width: 150px;">
          <option value="0" selected="selected">nic</option>
        <?
        $sql="
        select
          id_artefaktu,
          artefakt
        from
          prodej_artefakty
        where
          rok=".var_getvalue_sn('rok')."
          and pocet <> 0;        
        ";
        $result=dbQuery($db_jmeno,$sql,$db_spojeni);
        while($zaznam=mysql_fetch_row($result)){
          echo "<option value=\"$zaznam[0]\">$zaznam[1]</option>";
        }
        ?>
        </select><br />
        <strong>Poznámka:</strong><br />
        <textarea name="poznamka" style="width: 300px; height: 50px;"></textarea><br />
        <input type="submit" value="Prodat artefakt" />              
      </form>
    </div>
     
    <div class="adm_box">
      <h3>Registrace nového uživatele</h3>
      <form method="post">
        <input type="hidden" name="akce" value="registrace">
        <table class="invisible_table">
          <tr>
            <td><strong>Login:</strong></td><td><input type="text" name="login"></td>
          </tr>
          <tr>
            <td><strong>Jméno:</strong></td><td><input type="text" name="jmeno"></td>
          </tr>
          <tr>
            <td><strong>Příjmení:</strong></td><td><input type="text" name="prijmeni"></td>
          </tr>
          <tr>
            <td><strong>Heslo:</strong></td><td><input type="text" name="heslo"></td>
          </tr>
          <tr>
            <td><strong>Email:</strong></td><td><input type="text" name="email"></td>
          </tr>
          <tr>
            <td colspan="2" style="text-align: center;">
              <input type="submit" value="Zaregistrovat uživatele" />
            </td>
          </tr>
        </table>
      </form>
    </div>
    <?
  }
  
  
  
  /////////////////////////////////////////////
  // Stránka s vybraným uživatelem pro práci //
  ///////////////////////////////////////////// 
  
  if (!empty($_SESSION["id_uzivatele"]))
  {
  ?>
    <table class="cista" style="width:938px;margin-top:20px;border-collapse:separate; border-spacing:10px; margin: -10px;">
    <tr>
    <td class="adm_box">
      <h3>Gamecon</h3>
      <?
      if (ma_pravo($_SESSION["id_uzivatele"],$GLOBALS['ID_PRAVO_PRIHLASEN'])){
        echo "Uživatel <strong>je registrován</strong> na GC".var_getvalue_sn('rok')." <br />";
      }
      else {
        echo "Uživatel <strong>není</strong> registrován na GC".var_getvalue_sn('rok');
        ?>
        <a href="javascript: document.getElementById('registracegc').submit()">(zaregistrovat na GC)</a>
        <form  method="post" id="registracegc" style="display: inline;">
          <input type="hidden" name="akce" value="registracegc" />
        </form>
        <?
      }        
      $sql="
      select
        ostatni.placka,
        ostatni.tricko,
        ostatni.ubytovani,
        (
        select
          count(*)
        from
          prihlaska_ubytovani ubyt
        where
          ubyt.id_uzivatele=$_SESSION[id_uzivatele]
          and rok=".var_getvalue_sn('rok')." 
        ),
        ostatni.kostka
      from
        prihlaska_ostatni ostatni
      where
        ostatni.id_uzivatele=$_SESSION[id_uzivatele]
        and rok=".var_getvalue_sn('rok').";
      ";
      $result=dbQuery($db_jmeno,$sql,$db_spojeni);
      if (mysql_num_rows($result) > 0){
        $placka=mysql_result($result,0,0);
        $tricko=mysql_result($result,0,1);
        $ubytovani=mysql_result($result,0,2);
        $dnu=mysql_result($result,0,3);
        $kostka=mysql_result($result,0,4);
      
        $tricko_slovy='';
        if ($tricko != "0"){
          switch ($tricko){
            case "dS": $typ="dámské, velikost S";
            break;
            case "dM": $typ="dámské, velikost M";
            break;
            case "dL": $typ="dámské, velikost L";
            break;
            case "S": $typ="pánské, velikost S";
            break;
            case "M": $typ="pánské, velikost M";
            break;
            case "L": $typ="pánské, velikost L";
            break;
            case "XL": $typ="pánské, velikost XL";
            break;
          }
          if($uPracovni->maPravo(P_TRIKO_ZDARMA))
            $typ.=', <span style="color: red">červené</span>';
          elseif($uPracovni->maPravo(P_TRIKO_ZAPUL))
            $typ.=', <span style="color: steelblue">modré</span>';
          else
            $typ.=', černé';
          $tricko_slovy="<li>tričko $typ</li>";
        }
        
        $placka_slovy=$placka==1?"<li>placku</li>":'';
        $kostka_slovy=$kostka==1?"<li>kostku</li>":'';
      
        ?>
        <strong>Má objednáno:</strong>
        <ul>
        <?php echo $placka_slovy.$kostka_slovy.$tricko_slovy ?>
        </ul>
        <?
        if (ma_pravo($_SESSION["id_uzivatele"],$GLOBALS['ID_PRAVO_PRITOMEN'])){
          echo "<strong>Uživatel je na GC".var_getvalue_sn('rok')." a dostal materiály</strong>";
        }
        else {
          ?>
            <a href="javascript: document.getElementById('materialy').submit()">Dát uživateli materiály</a> (alt+m)
            <form  method="post" id="materialy">
              <input type="hidden" name="akce" value="materialy" />
            </form>
          <?
        }
      }
      
      ?>
    </td>
    <td class="adm_box drd">
      <h3>Mistrovství v DrD</h3>
      <?
        $a=dbOneLine('SELECT rok FROM akce_seznam WHERE id_akce='.ID_AKTIVITA_DRD);
        if($a['rok']!=ROK)
          echo '<div class="error">Pro aktuální rok není vytvořena aktivita mistrovství v DrD. Je potřeba vytvořit strom mistrovství DrD v aktivitách a zanést daná ID do souboru /sdilene/constants.hhp.</div>';
          //@todo nabídnout údržbový skript
      else
      {
        if(ma_pravo($_SESSION["id_uzivatele"],$ID_PRAVO_DRD))
        {
          ?>
          <strong>Hraje Mistrovství v DrD</strong> <a href="javascript: document.getElementById('zrus_drd').submit()">zrušit registraci na DrD</a>
          <form  method="post" style="display: inline;" id="zrus_drd">
            <input type="hidden" name="akce" value="zrus_drd" />
          </form>
          <?
          $sql="
          select
            uzdru.id_druziny,
            druz.nazev
          from
            drd_uzivatele_druziny uzdru,
            drd_druziny druz
          where
            uzdru.id_uzivatele=$_SESSION[id_uzivatele] and
            uzdru.id_druziny=druz.id_druziny
            and uzdru.rok=".var_getvalue_sn('rok')."
            and druz.rok=".var_getvalue_sn('rok')."
          ";
          $result=dbQuery($db_jmeno,$sql,$db_spojeni);
          if (mysql_num_rows($result) > 0){
            echo "<br />Je členem <strong>družiny č.".(mysql_result($result,0,0)-DRD_POSUN)." (".mysql_result($result,0,1).")</strong>";
            ?><br />
            <a href="javascript: document.getElementById('odhlas_drd').submit()"> (odhlásit z družiny)</a>
            <form  method="post" style="display: inline;" id="odhlas_drd">
              <input type="hidden" name="akce" value="odhlas_drd" />
            </form>
            <?
          }
          else {
            echo "<br /><strong>Není v žádné družině</strong>";
            ?>
            <a href="javascript: document.getElementById('prihlas_drd').submit()">(přihlásit do družiny)</a>
            <form method="post" style="display: inline;" id="prihlas_drd">
              <input type="hidden" name="akce" value="prihlas_drd" />
            </form>
            / <a href="javascript: document.getElementById('zaloz_drd').submit()">(založit družinu)</a>
            <form  method="post" style="display: inline;" id="zaloz_drd">
              <input type="hidden" name="akce" value="zaloz_drd" />
            </form>
            <?
          }
          echo '<br /><a href="'.URL_WEBU.'/drd-osobni-denik?uid='.$_SESSION['id_uzivatele'].'">(osobní deník)</a>';
        }
        else
        {
          echo "Není registrován na Mistrovství v DrD ";
          if($uPracovni->gcPrihlasen())
          {
            ?>
            <br />
            <a href="javascript: document.getElementById('prihlas_na_drd').submit()">(zaregistrovat na DrD)</a>
            <form  method="post" style="display: inline;" id="prihlas_na_drd">
              <input type="hidden" name="akce" value="prihlas_na_drd" />
            </form>
            <?
          }
          else
            echo 'ani přihlášen na GC.';
        }
      }
      ?>
    </td>
    <td class="adm_box">
      <h3>Aktivity</h3>
      <?php
        $a=dbQuery('SELECT t.typ_1p, count(a.typ) as pocet
          FROM akce_prihlaseni p
          LEFT JOIN akce_seznam a USING(id_akce)
          LEFT JOIN akce_typy t ON(t.id_typu=a.typ)
          WHERE p.id_uzivatele='.$_SESSION["id_uzivatele"].'
          AND a.rok='.$GLOBALS['ROK_AKTUALNI'].'
          AND a.typ!=0 AND a.typ!=99 -- dracak
          GROUP BY a.typ');
        if(!$r=mysql_fetch_assoc($a))
          echo('<b>Žádné registrované aktivity</b>');
        else
        {
          do
            echo('<b>'.ucfirst($r['typ_1p']).'</b>: '.$r['pocet'].'<br />');
          while($r=mysql_fetch_assoc($a));
        }
      ?>
      <br />
      <a href="./program-uzivatele" onclick="window.open(this.href); return false">kompletní program</a><br />
      <a href="./program-osobni" onclick="window.open(this.href); return false">osobní program uživatele</a>
    </td>
    </tr>
    </table>    
    
    <div class="adm_box">
      <h3>Finance</h3>
      <strong>Na GC účtu: <?echo gamecoruny_vypis();?></strong><br />
      <form  method="post" name="penize_pripsat">
        <input type="hidden" name="akce" value="penize_pripsat">
        <strong>Vložit částku:</strong> <input type="text" name="castka"><br />
        <?
          if(ma_pravo($u->id(),108))
            echo "se slevou:<input type=\"checkbox\" name=\"sleva\" value=\"ano\" /><br />";
          else 
            echo "Pro připsání peněz se slevou (slováci a lidi s poznámkou) kontaktuj admina (Elden, Gandalf)<br />";
        ?>
        <strong>Poznámka:</strong><br />
        <textarea name="poznamka"></textarea><br />
        <input type="submit" value="Připsat částku" />
      </form>
      <a href="javascript: document.getElementById('vyplatit').submit()">(vyplatit peníze)</a>
      <form  method="post" id="vyplatit">
        <input type="hidden" name="akce" value="vyplatit" />
      </form>
      <a href="javascript: document.getElementById('financni_prehled').submit()">(zobrazit finanční přehled <?php echo ROK ?>)</a>
      <form  method="post" id="financni_prehled">
        <input type="hidden" name="akce" value="financni prehled" />
      </form>
      <a href="javascript: document.getElementById('financni_historie').submit()">(zobrazit finanční historii <?php echo ROK ?>)</a>
      <form  method="post" id="financni_historie">
        <input type="hidden" name="akce" value="financni historie" />
      </form>
    </div>
    
    <div class="adm_box">
      <h3>Informace</h3>
      <?php
        $r=dbOneLine('SELECT * FROM uzivatele_hodnoty WHERE id_uzivatele='.$uPracovni->id());
      ?> 
      <strong>E-mail:</strong> <?=$r['email1_uzivatele']?><br />
      <strong>Telefon:</strong> <?=$r['telefon_uzivatele']?><br />
      <strong>Vzkaz z registrace:</strong>
      <?php
        $result=dbQuery("select vzkaz from prihlaska_ostatni 
          where id_uzivatele=$_SESSION[id_uzivatele] and rok=".ROK);
        if (mysql_num_rows($result) > 0){
          echo mysql_result($result,0,0);
        }
        else {
          echo "Žádná poznámka";
        }
      ?>
    </div>
    
    <div class="adm_box" style="border: 1px solid red;">
      <h3>Zrušit práci</h3>    
      <form  method="post">
        <input type="hidden" name="uzivatele_zrusit" value="1" />
        <input type="submit" value="Zrušit práci s tímto uživatelem" />
      </form>
    </div>  
  <?
  
  }
  
  ?>
  <div class="adm_box">
    <h3>Obrazovky pro infopult</h3>
    <a href="/program-obecny" onclick="return!window.open(this.href)">Program</a> -
    <a href="/last-minute-tabule" onclick="return!window.open(this.href)">Volná místa</a>
  </div>
  <script>$('.omnibox').first().focus();</script>
  
  <?php
  
  }
  else {
    if ($_POST["akce"] == "materialy"){
      echo "Uživateli byly dány materiály.<br />";
      echo "<a href=\"/uvod\">zpět</a>";
      posad_na_zidli($GLOBALS['ID_ZIDLE_PRITOMEN']);
      if($uPracovni->id()==$u->id()) //hack - pokud admin edituje sám sebe, musí se otočit (správně by měl řešit uzivatel.hhp)
        $u->otoc();
      $uPracovni->otoc();
      back();
    }
    if ($_POST["akce"] == "prodej"){
      $sql="
      select
        pocet
      from
        prodej_artefakty
      where
        id_artefaktu=$_POST[id_artefaktu]        
      ";
      $result=dbQuery($db_jmeno,$sql,$db_spojeni);
      $pocet=mysql_result($result,0,0);
      if ($pocet <> 0){
        $sql="
        insert into
          prodej_zaznam
          (id_artefaktu,id_admin,poznamka,datum,rok)
        values
          ($_POST[id_artefaktu],$_SESSION[id_admin],'$_POST[poznamka]',NOW(),".var_getvalue_sn('rok').")  
        ";
        dbQuery($db_jmeno,$sql,$db_spojeni);
        
        if ($pocet <> -1){
          $sql="
          update
            prodej_artefakty
          set
            pocet=pocet-1
          where
            id_artefaktu=$_POST[id_artefaktu]
          ";
          dbQuery($db_jmeno,$sql,$db_spojeni);          
        }
        ?>
        <div class="adm_box" style="border: 1px solid red;">
          <strong>Artefakt úspěšně prodán<strong>
        </div>
        <?
      }
      else {
        ?>
        <div class="adm_box" style="border: 1px solid red;">
          <strong>POZOR! Prodej neúspěšný, artefakt již není na skladě! Nevydávat!</strong>
        </div>
        <?
      }
    
      echo "<a href=\"/uvod\">zpět</a>";
    }    
    if ($_POST["akce"] == "registracegc"){
      echo "Uživatel byl zaregistrován na GameCon.<br />";
      echo "<a href=\"/uvod\">zpět</a>";
      
      $sql="
      insert into 
        prihlaska_ostatni
        (id_uzivatele,placka,kostka,tricko,student,ubytovani,rok)
        values
        ($_SESSION[id_uzivatele],0,0,'0',0,0,".var_getvalue_sn('rok').")
      ";
      dbQuery($db_jmeno,$sql,$db_spojeni);
      
      $sql="
      select
        jmeno_uzivatele,
        prijmeni_uzivatele,
        email1_uzivatele
      from
        uzivatele_hodnoty
      where
        id_uzivatele=$_SESSION[id_uzivatele] 
      ";
      $result=dbQuery($db_jmeno,$sql,$db_spojeni);
      $jmeno=mysql_result($result,0,0);
      $prijmeni=mysql_result($result,0,1);
      $email=mysql_result($result,0,2);
      
      $sql="
      insert into 
        prihlaska_uzivatele
        (id_uzivatele,jmeno,prijmeni,email,rok)
        values
        ($_SESSION[id_uzivatele],'$jmeno','$prijmeni','$email',".var_getvalue_sn('rok').")
      ";
      dbQuery($db_jmeno,$sql,$db_spojeni);
      
      posad_na_zidli($GLOBALS['ID_ZIDLE_PRIHLASEN']);
      if($uPracovni->id()==$u->id()) //hack - pokud admin edituje sám sebe, musí se otočit (správně by měl řešit uzivatel.hhp)
        $u->otoc();
      $uPracovni->otoc();
    }

    if($_POST["akce"] == "registrace")
    {
      $sql="
        select
          login_uzivatele
        from
          uzivatele_hodnoty
        where
          login_uzivatele like '$_POST[login]';
      ";
      $result=dbQuery($db_jmeno,$sql,$db_spojeni);
      if(mysql_num_rows($result)>0 || empty($_POST["login"]) || empty($_POST["email"]) || empty($_POST["heslo"]))
      {
        $chyba=true;
        if (mysql_num_rows($result)>0){
          $chyba_vypis .= "Uživatel s tímto uživatelským jménem je již zaregistrován.<br />"; 
        } 
        if (empty($_POST["login"])){
          $chyba_vypis .= "Není vyplněn login.<br />"; 
        }
        if (empty($_POST["heslo"])){
          $chyba_vypis .= "Není zadáno heslo.<br />"; 
        }
        if (empty($_POST["email"])){
          $chyba_vypis .= "Není zadán email.<br />"; 
        }
        echo "<div class=\"adm_box\" style=\"border: 1px solid red;\"><strong>CHYBA! $chyba_vypis</strong></div>";
        ?>
        <div class="adm_box">
        <h3>Registrace nového uživatele</h3>
        <form  method="post">
              <input type="hidden" name="akce" value="registrace">
              <table class="invisible_table">
                <tr>
                  <td><strong>Login:</strong></td><td><input type="text" name="login" value="<?echo $_POST["login"]?>" /></td>
                </tr>
                <tr>
                  <td><strong>Jméno:</strong></td><td><input type="text" name="jmeno" value="<?echo $_POST["jmeno"]?>" /></td>
                </tr>
                <tr>
                  <td><strong>Příjmení:</strong></td><td><input type="text" name="prijmeni" value="<?echo $_POST["prijmeni"]?>" /></td>
                </tr>
                <tr>
                  <td><strong>Heslo:</strong></td><td><input type="text" name="heslo" value="<?echo $_POST["heslo"]?>" /></td>
                </tr>
                <tr>
                  <td><strong>Email:</strong></td><td><input type="text" name="email" value="<?echo $_POST["email"]?>" /></td>
                </tr>
                <tr>
                  <td colspan="2" style="text-align: center;">
                    <input type="submit" value="Zaregistrovat uživatele" />
                  </td>
                </tr>
              </table>
            </form>
        </div>
        <?php
        echo "<br /><br /><br /><a href=\"/uvod\">zpět na ÚVOD</a>";  
      }
      else
      {
        $heslo=md5($_POST["heslo"]);
        dbInsert('uzivatele_hodnoty',array(
          'login_uzivatele'=>post('login'),
          'jmeno_uzivatele'=>post('jmeno'),
          'prijmeni_uzivatele'=>post('prijmeni'),
          'email1_uzivatele'=>post('email'),
          'heslo_md5'=>$heslo,
          'funkce_uzivatele'=>1));
        $last_id=mysql_insert_id();
        echo "<div class=\"adm_box\" style=\"border: 1px solid red;\"><strong>Uživatel vytvořen<br />";
        ?>
        <br />
        <form  method="post">
          <input type="hidden" name="uzivatele_vybrat" value="1" />
          <input type="hidden" name="id_uzivatele" value="<?echo $last_id?>" />
          <input type="submit" value="Vybrat uživatele pro práci" />
        </form>
        <?
        echo "</strong></div><br /><br /><br /><a href=\"/uvod\">zpět na ÚVOD</a>";        
      }
    }

    
    if ($_POST["akce"] == "penize_pripsat"){
      
        $sql="
          select
            id_uzivatele,
            jmeno_uzivatele,
            prijmeni_uzivatele
          from
            uzivatele_hodnoty
          where
            id_uzivatele=$_SESSION[id_uzivatele]
        ";
        $result=dbQuery($db_jmeno,$sql,$db_spojeni);
        if (mysql_num_rows($result)>0){
          $id_uzivatele=mysql_result($result,0,0);
          $jmeno_uzivatele=mysql_result($result,0,1);
          $prijmeni_uzivatele=mysql_result($result,0,2);
          $datum=time();
          if (post("sleva") == "ano") {
            $sleva=1;
          }
          else {
            $sleva=0;
          } 
          $sql="
            insert into
              finance_platby
              (id_uzivatele,castka,sleva,rok)
            values
              ($id_uzivatele,$_POST[castka],$sleva,".var_getvalue_sn('rok').")
          ";
          //echo $sleva." XXX ".$sql."<br />";
          if (dbQuery($db_jmeno,$sql,$db_spojeni)){
          $sql="
            select
              max(id_platby)
            from
              finance_platby
          ";
          $result=dbQuery($db_jmeno,$sql,$db_spojeni);
          $max=mysql_result($result,0,0);
           
          $sql="
            insert into
              log_uzivatele
              (id_uzivatele,typ,admin,datum,poznamka,id_platby)
            values
              ($id_uzivatele,1,$_SESSION[id_admin],$datum,'$_POST[poznamka]',$max)
          ";
          dbQuery($db_jmeno,$sql,$db_spojeni);
          
          $sql="
            select
              login_uzivatele,
              jmeno_uzivatele,
              prijmeni_uzivatele
            from
              uzivatele_hodnoty
            where
              id_uzivatele=$id_uzivatele
          ";
          $result=dbQuery($db_jmeno,$sql,$db_spojeni);
          $login=mysql_result($result,0,0);
          $jmeno=mysql_result($result,0,1);
          $prijmeni=mysql_result($result,0,2);
          
          $sql="
                select
                  log.datum,
                  log.poznamka,
                  uziv.login_uzivatele,
                  fin.castka,
                  fin.sleva,
                  log.typ
                from
                  log_uzivatele log,
                  uzivatele_hodnoty uziv,
                  finance_platby fin
                where
                  log.admin=uziv.id_uzivatele and
                  log.id_uzivatele=$id_uzivatele and
                  log.typ in (1,2) and
                  log.id_platby=fin.id_platby
                  and fin.rok=".var_getvalue_sn('rok').";
              ";
              $result=dbQuery($db_jmeno,$sql,$db_spojeni);
              if (mysql_num_rows($result) > 0){
                $poc=1;
                $vysledek='';
                while($zaznam=mysql_fetch_row($result)){
                  if ($zaznam[4] == 1){
                    $sleva="(se slevou)";
                  }
                  else {
                    $sleva="(beze slevy)";
                  }
                  if (!empty($zaznam[1])){
                    $pozn="($zaznam[1])";
                  }
                  else {
                    $pozn="";
                  }
                  if ($zaznam[5] == 1){
                    $udelal="připsal na účet";
                  }
                  else {
                    $udelal="vyplatil z účtu";
                  }
                  $vysledek .= "<strong>$poc</strong>. ".date("j.n.y",$zaznam[0]).": $zaznam[2] $udelal $sleva <strong>$zaznam[3]</strong> GameCorun $pozn<br />";
                  $poc++;
                }
              }
          
          echo "
            Uživateli $login ($jmeno $prijmeni) bylo připsáno $_POST[castka] GameCorun.<br /><br />
            <strong>Finanční historie uživatele $login</strong> ($jmeno $prijmeni)<br />";
          echo financeHistorie($id_uzivatele);
          
          }
        }
      echo "<br /><a href=\"/uvod\">zpět</a>";
  }
  
  if ($_POST["akce"] == "financni prehled")
  {
    $fin=new Finance($uPracovni);
    echo $fin->prehledHtml();  
    ?> <a href="/uvod">zpět</a> <?php  
  }
  
  if ($_POST["akce"]=="financni historie")
  {
    echo "<strong>Finanční historie uživatele</strong><br />";
    echo financeHistorie($_SESSION['id_uzivatele']);
    ?> <a href="/uvod">zpět</a> <?php  
  }

  if ($_POST["akce"] == "financni-prehled-old"){
  $standard=gamecoruny_standard2_old();
  $bonus=gamecoruny_bonus2_old();
      $sql="
        select
          placka,
          ubytovani,
          tricko,
          kostka
        from
          prihlaska_ostatni
        where
          id_uzivatele=$_SESSION[id_uzivatele]
          and rok=2009;
      ";
      $result=dbQuery($db_jmeno,$sql,$db_spojeni);
      $placka=mysql_result($result,0,0);
      $ubytovani=mysql_result($result,0,1);
      $tricko=mysql_result($result,0,2);
      $kostka=mysql_result($result,0,3);
      
      if ($ubytovani > 0){
        $sql="
          select
            count(id_ubytovani)
          from
            prihlaska_ubytovani
          where
            id_uzivatele=$_SESSION[id_uzivatele]
            and rok=2009;
      ";
      $result=dbQuery($db_jmeno,$sql,$db_spojeni);
      $pocet_dni=mysql_result($result,0,0);
      }
      $soucet=0;
      
      if ($placka == "1"){
        $placka_vypis="objednána (15 Kč)";
        $soucet += 15;
      }
      else {
        $placka_vypis="neobjednána";
      }
      
      if ($kostka == "1"){
        $kostka_vypis="objednána (15 Kč)";
        $soucet += 15;
      }
      else {
        $kostka_vypis="neobjednána";
      }
      
      if ($tricko != "0"){
        switch ($tricko){
          case "dS": $typ="dámské, velikost S ";
          break;
          case "dM": $typ="dámské, velikost M ";
          break;
          case "M": $typ="pánské, velikost M ";
          break;
          case "L": $typ="pánské, velikost L ";
          break;
          case "XL": $typ="pánské, velikost XL ";
          break;
        }
        $tricko_vypis="objednáno $typ(".$GLOBALS['CENA_TRIKO']." Kč)";
        $soucet += $GLOBALS['CENA_TRIKO'];
      }
      else {
        $tricko_vypis="neobjednáno";
      }
      if ($ubytovani > 0){
        if ($pocet_dni > 0){
          if ($ubytovani == 1){
            $ubytovani_stoji=$pocet_dni*200;
            $ubytovani_vypis="objednáno (typ: pokoj, dnů: $pocet_dni, cena celkem: $ubytovani_stoji)";
            $soucet += $ubytovani_stoji;
          }
          if (($ubytovani == 2) && ($pocet_dni == 1)){
            $ubytovani_stoji=$pocet_dni*120;
            $ubytovani_vypis="objednáno - typ chatka, dnů: $pocet_dni, cena celkem: $ubytovani_stoji)";
            $soucet += 150;
          }
          elseif (($ubytovani == 2) && ($pocet_dni > 1)){
            $ubytovani_stoji=$pocet_dni*120;
            $ubytovani_vypis="objednáno - typ chatka, dnů: $pocet_dni, cena celkem: $ubytovani_stoji)";
            $soucet += $ubytovani_stoji;
          }
        }
      }
      else {
        $ubytovani_vypis="neobjednáno";
      }
      
      echo "
      <h3>Tvé platby</h3>
      <p>
        Na svůj GameCon účet sis poslal <strong>$standard Kč</strong> a tím jsi získal <strong>$standard</strong> GameCorun a dále Bonus ve výši <strong>$bonus</strong>.
      </p>
      
      <h3>Ubytování, doplňky</h3>
      <p>
      Položky, které jsi vyplnil při registraci (na tyto položky nelze využít Bonus)
      </p>
      <p>
      <strong>Ubytování:</strong> $ubytovani_vypis<br />
      <strong>Tričko:</strong> $tricko_vypis<br />
      <strong>Placka:</strong> $placka_vypis<br />
      <strong>Kostka:</strong> $kostka_vypis<br />
      </p>
      
      <h3>Přihlášené aktivity</h3>
      <p>
      Na tyto položky se automaticky používá Bonus za včasnou platbu (pokud nějaký máš).
      </p>
      <p>";
  
      $sql="
        select
          student
        from
          prihlaska_ostatni
        where
          id_uzivatele=$_SESSION[id_uzivatele]
          and rok=2009;
      ";
      $result=dbQuery($db_jmeno,$sql,$db_spojeni);
      $student=mysql_result($result,0,0);
      
      $sql="
        select
          seznam.id_akce,
          seznam.nazev_akce,
          seznam.cena,
          seznam.sleva
        from
          akce_seznam seznam
        where seznam.id_akce in(
          select
            prihlaseni.id_akce
          from
            akce_prihlaseni prihlaseni
          where
            prihlaseni.id_uzivatele=$_SESSION[id_uzivatele]
          order by
            prihlaseni.id_akce asc
        ) and
        seznam.id_akce not in (
          select
            id_akce
          from
            akce_volne
          where
            id_uzivatele=$_SESSION[id_uzivatele]
        )
        and seznam.rok=2009;
      ";
      $result=dbQuery($db_jmeno,$sql,$db_spojeni);

      if (mysql_num_rows($result)>0){
        echo "<p>";
        while($zaznam=mysql_fetch_row($result)){
          $nazev_akce=$zaznam["1"];
          $cena=$zaznam["2"];
          $sleva=$zaznam["3"];
          if ($sleva == 1){
            $sleva_slovy="můžu";
            if ($student == 1){ 
              $odecet_zaklad=$odecet_bonus=($cena * 0.5); 
            }
            else {
              $odecet_bonus=$cena * 0.25;
              $odecet_zaklad=$cena * 0.75;        
            }
          }
          else {
            $sleva_slovy="nemůžu";
            $odecet_bonus=1;
            $odecet_zaklad=$cena;  
          }
          if (($bonus - $odecet_bonus) < 0){
            echo "<strong>$nazev_akce</strong>, cena $cena GC, odečteno ".($odecet_zaklad+($odecet_bonus-$bonus))."/$bonus<br />";
          }
          else {
            echo "<strong>$nazev_akce</strong>, cena $cena GC, odečteno $odecet_zaklad/$odecet_bonus<br />";
          }
          $standard -= $odecet_zaklad;
          if (($bonus - $odecet_bonus) > 0){
            $bonus -= $odecet_bonus;
          }
          else {
            $standard -= ($odecet_bonus-$bonus);
            $bonus=0;
          }
        }
        echo "</p>";
      }
      else {
        echo "<p>Nemáš objednánu žádnou aktivitu.</p>";
      }
      
      $sql="
        select
          akce.nazev_akce
        from
          akce_volne volne,
          akce_seznam akce
        where
          volne.id_uzivatele=$_SESSION[id_uzivatele] and
          akce.id_akce=volne.id_akce
          and rok=".var_getvalue_sn('rok').";
      ";
      $result=dbQuery($db_jmeno,$sql,$db_spojeni);

      if (mysql_num_rows($result)>0){
        echo "<br />";
        while($zaznam=mysql_fetch_row($result)){
          echo "<strong>$zaznam[0]</strong>, odečteno jako <strong>volná akce</strong>, cena 0/0<br />";
        }
      }
      
      $standard -= $soucet;
      echo "</p>
      <h3>Konečný stav</h3>
      <p>
        Stav tvého GameCon účtu po odečtení všech registrovaných položek je <strong>$standard / $bonus</strong> GameCorun. 
      </p>
      ";
      
      $sql="
      select
        sum(castka)
      from
        finance_platby
      where
        id_uzivatele=$_SESSION[id_uzivatele] and
        castka < 0
        and rok=2009;
    ";
    $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    $vyplaceno=mysql_result($result,0,0);
    $vyplaceno=abs($vyplaceno);
    
    $sql="
      select
        zustatek
      from
        uzivatele_hodnoty
      where
        id_uzivatele=$_SESSION[id_uzivatele]
    ";
    $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    $zustatek=mysql_result($result,0,0);
    
    if ($vyplaceno != ""){
      echo "Po Conu bylo uživateli vyplaceno $vyplaceno. Do roku 2010 mu zástalo na účtě $zustatek<br /><br />";
    }
      
    echo "<a href=\"/uvod\">zpět</a>";
  }
  
  if ($_POST["akce"] == "vyplatit"){
      ?>
      <div class="adm_box">
        <h3>Vyplatit peníze</h3>
        <?
        if(post("krok") != "2"){
        ?>
        Na GC účtu (max. k vyplacení): <strong><?echo gamecoruny_vypis();?></strong>
        <form  method="post" name="vyplatit">
          <input type="hidden" name="akce" value="vyplatit">
          <input type="hidden" name="krok" value="2">
          <strong>Vyplatit částku:</strong> <input type="text" name="castka"><br />
          <strong>Poznámka:</strong><br />
          <textarea name="poznamka"></textarea><br />
          <input type="submit" value="Vyplatit částku" />
        </form>
        <?
        }
        else {
          if(($_POST["castka"]>0) && (is_numeric($_POST["castka"]))){
            $sql="
              select
                id_uzivatele,
                jmeno_uzivatele,
                prijmeni_uzivatele
              from
                uzivatele_hodnoty
              where
                id_uzivatele=$_SESSION[id_uzivatele]
            ";
            $result=dbQuery($db_jmeno,$sql,$db_spojeni);
            if (mysql_num_rows($result)>0){
              $id_uzivatele=mysql_result($result,0,0);
              $jmeno_uzivatele=mysql_result($result,0,1);
              $prijmeni_uzivatele=mysql_result($result,0,2);
              $datum=time();
              $penize="-$_POST[castka]";
              $sql="
                insert into
                  finance_platby
                  (id_uzivatele,castka,sleva,rok)
                values
                  ($id_uzivatele,$penize,0,".var_getvalue_sn('rok').")
              ";
              //echo $sleva." XXX ".$sql."<br />";
              if (dbQuery($db_jmeno,$sql,$db_spojeni)){
              $sql="
                select
                  max(id_platby)
                from
                  finance_platby
              ";
              $result=dbQuery($db_jmeno,$sql,$db_spojeni);
              $max=mysql_result($result,0,0);
               
              $sql="
                insert into
                  log_uzivatele
                  (id_uzivatele,typ,admin,datum,poznamka,id_platby)
                values
                  ($id_uzivatele,2,$_SESSION[id_admin],$datum,'$_POST[poznamka]',$max)
              ";
              dbQuery($db_jmeno,$sql,$db_spojeni);
              
              $sql="
                select
                  login_uzivatele,
                  jmeno_uzivatele,
                  prijmeni_uzivatele
                from
                  uzivatele_hodnoty
                where
                  id_uzivatele=$id_uzivatele
              ";
              $result=dbQuery($db_jmeno,$sql,$db_spojeni);
              $login=mysql_result($result,0,0);
              $jmeno=mysql_result($result,0,1);
              $prijmeni=mysql_result($result,0,2);
              
              echo "
                <strong>Info</strong><br />
                Uživateli $login ($jmeno $prijmeni) bylo <strong>vyplaceno</strong> $_POST[castka] GameCorun.<br /><br />
                <strong>Finanční historie uživatele</strong><br />";
              echo financeHistorie($id_uzivatele);
              }
            }
      
          }
          else {
            echo "Chyba v castce";
          }
        }
        ?>
        <br /><a href="/uvod">zpět</a>
      </div>
      <?
    }
    
  if ($_POST["akce"] == "zrus_drd"){
    
    //odhlasit z druziny
    if (v_druzine()){
      vyhod_z_druziny(moje_druzina_cislo());
    }
    $id_uzivatele=isset($id_uzivatele)?$id_uzivatele:'';
    odhlas_akci(ID_AKTIVITA_DRD,$id_uzivatele);
    odhlas_akci(ID_AKTIVITA_DRD_BLOK1,$id_uzivatele);
    odhlas_akci(ID_AKTIVITA_DRD_BLOK2,$id_uzivatele);
    odhlas_akci(ID_AKTIVITA_DRD_BLOK3,$id_uzivatele);
    
    back();
    
    ?>
    <div class="adm_box">
      <strong>Uživatel byl odhlášen z Mistrovství v DrD.<br />
    </div>
    <br /><br /><a href="/uvod">zpět</a>
    <?
  }  
  
  if ($_POST["akce"] == "odhlas_drd"){
    //odhlasit z druziny
    if (v_druzine()){
      vyhod_z_druziny(moje_druzina_cislo());
    }
    back();
  }
  
  if ($_POST["akce"] == "prihlas_drd"){
    //odhlasit z druziny
    echo "<h2>Výpis družin</h2>";
        $sql="
          select
            id_druziny,
            blok
          from
            drd_druziny
          where
            rok=".var_getvalue_sn('rok')."
          order by
            id_druziny asc
          ";
        //echo $sql;
        $result=dbQuery($db_jmeno,$sql,$db_spojeni);
        if (mysql_num_rows($result) > 0){
          while($zaznam=mysql_fetch_row($result)){
            echo "
              <div style=\"margin-top: 20px;\">
                <h3 style=\"float: left; margin-top: 0px; padding-top: 0px;\">č. ".($zaznam[0]-DRD_POSUN)." - ".druzina_jmeno($zaznam[0])."</h3> <div style=\"float: right\"><a href=\"javascript: document.getElementById('prihlasit$zaznam[0]').submit()\">přihlásit</a> / <a href=\"členové\" onclick=\"ukaz($zaznam[0]); return false;\">členové</a></div>
                <div style=\"clear: both\">
                  <strong>Správce:</strong> ".druzina_spravce($zaznam[0])."<br />
                  <strong>Herní blok: </strong>".dekoduj_blok($zaznam[1])."<br />
                  <strong>Pán Jeskyně: </strong>".druzina_pj($zaznam[0])."<br />
                  <strong>Poznámka: </strong>".nl2br(druzina_poznamka($zaznam[0]))."<br />";
                  echo '<span id="objekt'.$zaznam[0].'" style="display: none;">';
                  druzina_clenove($zaznam[0]);
              echo "</span>";
            ?>
            <form  method="post" style="display: inline;" id="prihlasit<?echo $zaznam[0]?>">
              <input type="hidden" name="akce" value="prihlas_drd2" />
              <input type="hidden" name="id_druziny" value="<?echo $zaznam[0]?>" />
            </form>
            <?
            echo "</div></div>";
          }
        }
        else {
          echo "<ul><li>Ještě není založena žádná družina</li></ul>";
        }
    
    
    ?>
    <div class="adm_box">
      <strong>výpis.</strong><br />
    </div>
    <br /><br /><a href="/uvod">zpět</a>
    <?
  }
  
  if ($_POST["akce"] == "prihlas_drd2"){
    $sql="
    insert into
      drd_uzivatele_druziny
      (id_uzivatele,id_druziny,rok)
    values
      ($_SESSION[id_uzivatele],$_POST[id_druziny],".var_getvalue_sn('rok').")  
    ";
    dbQuery($db_jmeno,$sql,$db_spojeni);
    back();
  }
  
  if ($_POST["akce"] == "zaloz_drd")
  {
    ?>
    <div class="adm_box">
      <h3>Založit družinu DrD a přihlásit do ní uživatele</h3>
      <form  method="post">
        <input type="hidden" name="akce" value="zaloz_drd2"/>
        <table class="invisible_table">
          <tr>
            <td><strong>Jméno družiny:</strong></td><td>
              <input type="text" name="jmeno_druziny" />
            </td>
          </tr>
          <tr>
            <td colspan="2"><input type="submit" value="Založit družinu" /></td>
          </tr>
        </table>
      </form>
      </div>
    <br /><br /><a href="/uvod">zpět</a>  
    <?  
  }
  
  if ($_POST["akce"] == "zaloz_drd2"){
    if (!empty($_POST["jmeno_druziny"])){
    $sql ="
    insert into
      drd_druziny
      (nazev,poznamka,spravce,verejna,blok,rok)
    values
      ('$_POST[jmeno_druziny]','',$_SESSION[id_uzivatele],1,0,".var_getvalue_sn('rok').")   
    ";                                                                        //zalozit druzinu
    dbQuery($db_jmeno,$sql,$db_spojeni);
    $last_id=mysql_insert_id();
    $sql="
    insert into
      drd_uzivatele_druziny
      (id_uzivatele,id_druziny,rok)
    values
      ($_SESSION[id_uzivatele],$last_id,".var_getvalue_sn('rok').")  
    ";
    dbQuery($db_jmeno,$sql,$db_spojeni);
    ?>
    <div class="adm_box">
      <strong>Družina založena, aktivní uživatel se stal jejím členem!</strong>
      <br /><br /><a href="/uvod">zpět</a>
    </div>
    <?
    }
    else {
    ?>
    <div class="adm_box">
      <strong>Není zadán název družiny!</strong>
      <br /><br /><a href="/uvod">zpět</a>
    </div>
    <?
    }  
  }
  
  if ($_POST["akce"] == "prihlas_na_drd"){
    prihlas_akci(ID_AKTIVITA_DRD);
    back(); 
  }    
  
  //**************************************************trojboj
  if ($_POST["akce"] == "trb_zrus_drd"){
    //zesadit ze zidle DrD
    zesad_ze_zidle($ID_ZIDLE_TROJBOJ);
    
    //odhlasit z druziny
    if (trb_v_druzine()){
      trb_vyhod_z_druziny(trb_moje_druzina_cislo());
    }
    odhlas_akci($ID_AKTIVITA_TROJBOJ_REG);
    odhlas_akci($ID_AKTIVITA_TROJBOJ_PATEK);
    odhlas_akci($ID_AKTIVITA_TROJBOJ_SOBOTA);
    
    back();
  }  
  
  if ($_POST["akce"] == "trb_odhlas_drd"){
    //odhlasit z druziny
    if (trb_v_druzine()){
      trb_vyhod_z_druziny(trb_moje_druzina_cislo());
    }
    back();
  }
  //*************************************************************************************dodělat
  if ($_POST["akce"] == "trb_prihlas_drd"){
    //výběr teamů
    echo "<h2>Výpis teamů na Trojboj</h2>";
        $sql="
          select
            id_druziny,
            spacepat
          from
            trb_druziny
          where
            rok=".var_getvalue_sn('rok')."
          order by
            id_druziny asc
          ";
        //echo $sql;
        $result=dbQuery($db_jmeno,$sql,$db_spojeni);
        if (mysql_num_rows($result) > 0){
          while($zaznam=mysql_fetch_row($result)){
            echo "
              <div style=\"margin-top: 20px;\">
                <h3 style=\"float: left; margin-top: 0px; padding-top: 0px;\">č. ".($zaznam[0]-8)." - ".trb_druzina_jmeno($zaznam[0])."</h3> <div style=\"float: right\"><a href=\"javascript: document.getElementById('trb_prihlasit$zaznam[0]').submit()\">přihlásit</a> / <a href=\"členové\" onclick=\"ukaz($zaznam[0]); return false;\">členové</a></div>
                <div style=\"clear: both\">
                  <strong>Správce:</strong> ".trb_druzina_spravce($zaznam[0])."<br />
                  <strong>Blok Trojboje: </strong>".trb_dekoduj_spacepat($zaznam[1])."<br />
                  <strong>Poznámka: </strong>".nl2br(trb_druzina_poznamka($zaznam[0]))."<br />";
                  echo '<span id="objekt'.$zaznam[0].'" style="display: none;">';
                  trb_druzina_clenove($zaznam[0]);
              echo "</span>";
            ?>
            <form  method="post" style="display: inline;" id="trb_prihlasit<?echo $zaznam[0]?>">
              <input type="hidden" name="akce" value="trb_prihlas_drd2" />
              <input type="hidden" name="id_druziny" value="<?echo $zaznam[0]?>" />
            </form>
            <?
            echo "</div></div>";
          }
        }
        else {
          echo "<ul><li>Ještě není založena žádná družina</li></ul>";
        }
    
    
    ?>
    <br /><br /><a href="/uvod">zpět</a>
    <?
  }
  
  if ($_POST["akce"] == "trb_prihlas_drd2"){
    $sql="
    insert into
      trb_uzivatele_druziny
      (id_uzivatele,id_druziny,rok)
    values
      ($_SESSION[id_uzivatele],$_POST[id_druziny],".var_getvalue_sn('rok').")  
    ";
    dbQuery($db_jmeno,$sql,$db_spojeni);
    back();  
  }
  
  if ($_POST["akce"] == "trb_zaloz_drd"){
  ?>
  <div class="adm_box">
    <h3>Založit tým na trojboj a přihlásit do něj uživatele</h3>
    <form  method="post">
      <input type="hidden" name="akce" value="trb_zaloz_drd2"/>
      <table class="invisible_table">
        <tr>
          <td><strong>Jméno týmu:</strong></td><td>
            <input type="text" name="jmeno_druziny" />
          </td>
        </tr>
        <tr>
          <td colspan="2"><input type="submit" value="Založit tým" /></td>
        </tr>
      </table>
    </form>
    </div>
  <br /><br /><a href="/uvod">zpět</a>  
  <?  
  }
  
  if ($_POST["akce"] == "trb_zaloz_drd2"){
    if (!empty($_POST["jmeno_druziny"])){
    $sql ="
    insert into
      trb_druziny
      (nazev,poznamka,spravce,verejna,rok)
    values
      ('$_POST[jmeno_druziny]','',$_SESSION[id_uzivatele],1,".var_getvalue_sn('rok').")   
    ";                                                                        //zalozit druzinu
    dbQuery($db_jmeno,$sql,$db_spojeni);
    $last_id=mysql_insert_id();
    $sql="
    insert into
      trb_uzivatele_druziny
      (id_uzivatele,id_druziny,rok)
    values
      ($_SESSION[id_uzivatele],$last_id,".var_getvalue_sn('rok').")  
    ";
    dbQuery($db_jmeno,$sql,$db_spojeni);
    ?>
    <div class="adm_box">
      <strong>Tým založen, aktivní uživatel se stal jeho členem!</strong>
      <br /><br /><a href="/uvod">zpět</a>
    </div>
    <?
    }
    else {
    ?>
    <div class="adm_box">
      <strong>Není zadán název týmu!</strong>
      <br /><br /><a href="/uvod">zpět</a>
    </div>
    <?
    }  
  }
  
  if ($_POST["akce"] == "trb_prihlas_na_drd"){
    posad_na_zidli($GLOBALS['ID_ZIDLE_TROJBOJ']);
    prihlas_akci($ID_AKTIVITA_TROJBOJ_REG);
    back();  
  }
}

?>
