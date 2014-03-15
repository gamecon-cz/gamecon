<?php

// ignorování notice kvůli starému kódu pracujícímu s neinicializovanými proměnnými
error_reporting(E_ALL ^ E_NOTICE ^ E_DEPRECATED ^ E_STRICT);

//port starých proměnných
$url->cast(1)?$_GET["url_druha"]=$url->cast(1):0;
$url->cast(2)?$_GET["url_treti"]=$url->cast(2):0;
$url->cast(3)?$_GET["url_ctvrta"]=$url->cast(3):0;
$_SESSION["prihlasen"]=$u?1:0;
$_SESSION["razeni_fora"]='s';
if($u)
{
  $razeni=dbOneLine('SELECT forum_razeni FROM uzivatele_hodnoty WHERE id_uzivatele='.$u->id());
  $_SESSION["razeni_fora"]=$razeni['forum_razeni'];
}

require_once('forum-funkce.hhp');

//starý javascript
echo('
<script>
  function ukaz(i) {
    var pom=new Object();
    pom=document.getElementById("objekt"+i);
    if (pom.style.display == "block"){
      pom.style.display="none";
    }
    else {
      pom.style.display="block";
    }
  }
  
  function SetState(obj_checkbox, obj_textarea)
  {  if(obj_checkbox.checked)
     { obj_textarea.disabled=false;
     }
     else
     { obj_textarea.disabled=true;
     }
  }
</script>
');

// Nastaveni fora
$prispevku_ns=10; //prispevku na strance
$vlaken_ns=5; //vlaken na strance

//zobrazeni h1 pro druhou uroven fora
if (!empty($_GET["url_druha"]) && empty($_GET["url_treti"]) && empty($_GET["url_ctvrta"])){
  $sql="select jmeno_sekce from forum_sekce where jmeno_sekce_mini like '$_GET[url_druha]'";
  $result=dbQuery($db_jmeno,$sql,$db_spojeni);
  echo "<h1>".mysql_result($result,0,0)."</h1>";
}

if ($_POST["vkladam_vlakno"] == 2){
  vloz_vlakno($_POST["id_sekce"]);
}

//zobrazeni tlacitka "oznacit vsechny jako prectene"
if (!empty($_GET["url_druha"]) && empty($_GET["url_treti"]) && empty($_GET["url_ctvrta"]) && ($_SESSION["prihlasen"] > 0)){
  vkladani_vlakna($id_sekce);
}

if (!empty($_SESSION["prihlasen"]) && empty($_GET["url_treti"])){
  ?>
  <form action="forum<?php echo $_GET["url_druha"]?'/'.$_GET["url_druha"]:''; ?>" method="post" style="display: inline;">
    <input type="hidden" name="prectene" value="1" />
    <?php
    if (!empty($_GET["url_druha"])){
    $sql="select id_sekce from forum_sekce where jmeno_sekce_mini like '$_GET[url_druha]'";
    $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    $id_vlakna=mysql_result($result,0,0);
    ?>
    <input type="hidden" name="id_vlakna" value="<?php echo $id_vlakna?>" />
    <?php
    }
    ?>
    <input type="image" src="files/styly/styl-aktualni/tlacitka/oznacit-vsechny-jako-prectene.gif" value="Submit" alt="Označit všechny jako přečtené" style="width: 158px; height: 26px;" />
  </form>
  <?php
}

//odchytavani vkladani do DB (a jinych vylomenin)
//prispevky
if ($_POST["vkladam_prispevek"] == 1){
  vloz_prispevek($_POST["id_podsekce"]);
}
//prispevky anonymni
if ($_POST["vkladam_prispevek"] == 2){
  vloz_prispevek_anonym($_POST["id_podsekce"]);
}
//vlakna
if ($_POST["vkladam_vlakno"] == 1){
  zobraz_vkladani_vlakna($_POST["id_sekce"]);
}
//oznaceni vsech zprav jako prectenych
if (($_POST["prectene"] == 1) && (!empty($_POST["id_vlakna"]))){
  oznacit_jako_prectene_vlakno($_POST["id_vlakna"]);
}
elseif (($_POST["prectene"] == 1) && (empty($_POST["id_vlakna"]))){
  oznacit_jako_prectene();
}
//mazani prispevku
if ($_POST["smazat_prispevek"] == 1){
  $sql="select id_clanku,obsah from forum_clanky where id_clanku=$_POST[id_prispevku]";
  $result=dbQuery($db_jmeno,$sql,$db_spojeni);
  zobraz_potvrzeni_smazani(mysql_result($result,0,0),mysql_result($result,0,1));
}

if ($_POST["smazat_prispevek"] == 2){
  $sql="delete from forum_clanky where id_clanku=$_POST[id_prispevku]";
  dbQuery($db_jmeno,$sql,$db_spojeni);
  $chyba_zobraz="Příspěvek smazán.";
}

//editovani prispevku (zobrazeni editacniho pole)
if ($_POST["editovat_prispevek"] == 1){
  zobraz_editovani($_POST["id_prispevku"]);
}
if ($_POST["editovat_prispevek"] == 2){
  $sql="update forum_clanky set obsah='$_POST[obsah_prispevku]' where id_clanku=$_POST[id_prispevku]";
  dbQuery($db_jmeno,$sql,$db_spojeni);
  $chyba_zobraz="Příspěvek byl zeditován.";
}


if (isset($chyba_zobraz)){
  echo "<div class=\"chyba_ramecek\">$chyba_zobraz</div>";
  unset($chyba_zobraz);
}

// vypis vsech hlavnich sekci fora - nemenne, zadavane adminem
if (empty($_GET["url_druha"]) && empty($_GET["url_treti"]) && empty($_GET["url_ctvrta"])){
  $sql="select jmeno_sekce,jmeno_sekce_mini,id_sekce,popis_sekce from forum_sekce where viditelnost=0 order by poradi asc";
  $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    if (mysql_num_rows($result) > 0){
    ?>
    <div class="forum_vlakna">
      <div class="hlavicka">
        <div style="width: 275px;">téma</div>
        <div style="width: 55px;">vláken</div>
        <div style="width: 150px;">poslední příspěvěk</div>
      </div>
    <?php
    $pocitadlo_radku=0;
    while($zaznam=mysql_fetch_row($result)){
      $pocitadlo_radku++;
      //kontrola, jestli je v sekci novy prispevek
      if (!empty($_SESSION["prihlasen"])){
        $sql2="select id_podsekce,posledni_zmena from forum_podsekce where patri=$zaznam[2]";
        $result2=dbQuery($db_jmeno,$sql2,$db_spojeni);
        $novy=0;
        while($zaznam2=mysql_fetch_row($result2)){
          $sql3="select id_cteni from forum_cteno where id_podsekce=$zaznam2[0] and id_uzivatele=$_SESSION[id_uzivatele] and precteno<$zaznam2[1]";
          $result3=dbQuery($db_jmeno,$sql3,$db_spojeni);
          $sql4="select id_cteni from forum_cteno where id_podsekce=$zaznam2[0] and id_uzivatele=$_SESSION[id_uzivatele]";
          $result4=dbQuery($db_jmeno,$sql4,$db_spojeni);
          
          if ((mysql_num_rows($result3) > 0) || (mysql_num_rows($result4) == 0)){
            $novy=1;
          }
        }
      //*******************************************
      
        if ($novy == 1){
          $obrazek=" class=\"novy\"";          
          $novy=0;
        }
        else {
          $obrazek=" class=\"stary\"";
        }
      }
      else {
        $obrazek=" class=\"stary\"";
      }
      //***********************
      ?>
      <div class="radek<?php echo $pocitadlo_radku%2;?>">
        <div class="ohraniceni">
          <div style="width: 50px;" <?php echo $obrazek?>>&nbsp;</div>
        <?php
        //autor, datum a čas posledního založení
        $sql_prvni_clanek="select uzivatel,datum from forum_clanky where patri=$zaznam[2] order by id_clanku asc limit 0,1";
        $result_prvni_clanek=dbQuery($db_jmeno,$sql_prvni_clanek,$db_spojeni);
        if (mysql_num_rows($result_prvni_clanek)>0){
          $id_uzivatele=mysql_result($result_prvni_clanek,0,0);
          $datum_ts=mysql_result($result_prvni_clanek,0,1);
          $sql_login_uzivatele="select login_uzivatele from uzivatele_hodnoty where id_uzivatele=$id_uzivatele";
          $result_login_uzivatele=dbQuery($db_jmeno,$sql_login_uzivatele,$db_spojeni);
          $login_uzivatele=mysql_result($result_login_uzivatele,0,0);
          $datum_vlozeni=date("j.n.y, H:i",$datum_ts);
        }
        else {
          $login_uzivatele="<em>nikdo</em>";
          $datum_vlozeni="nikdy";          
        }
        ?>
        <div style="width: 220px; text-align: left; min-height: 48px; vertical-align: top;">
          <span style="position: relative; top: 2px;"><a href="forum/<?php echo $zaznam[1]?>"><?php echo $zaznam[0]?></a></span><br />
          <span style="position: relative; top: 2px; font-style: italic;"><?php echo $zaznam[3]?></span>
        </div>
        <?php
        //spočítání vláken
        $sql_vlaken="select id_podsekce from forum_podsekce where patri=$zaznam[2]";
        $result_vlaken=dbQuery($db_jmeno,$sql_vlaken,$db_spojeni);
        $vlaken=mysql_num_rows($result_vlaken);
        ?>
        <div style="width: 55px; line-height: 48px; "><?php echo $vlaken?></div>
        <?php
        //autor, datum a čas posledního založení
        //$sqlx="select id_podsekce from forum_podsekce where max(posledni_zmena)";
        //$resultx=dbQuery($db_jmeno,$sqlx,$db_spojeni);
        //$id_podsekce_zobrazeni=mysql_result($resultx,0,0);
        
        $sql_posledni_clanek="select uzivatel,datum,jmeno_neregistrovany from forum_clanky where patri=(select id_podsekce from forum_podsekce where posledni_zmena=(select max(posledni_zmena) from forum_podsekce where patri=$zaznam[2])) order by id_clanku desc limit 0,1";
        //echo $sql_posledni_clanek;
        $result_posledni_clanek=dbQuery($db_jmeno,$sql_posledni_clanek,$db_spojeni);
        if (mysql_num_rows($result_posledni_clanek)>0){
          $id_uzivatele=mysql_result($result_posledni_clanek,0,0);
          if (!empty($id_uzivatele)){
            $datum_ts=mysql_result($result_posledni_clanek,0,1);
            $sql_login_uzivatele="select login_uzivatele from uzivatele_hodnoty where id_uzivatele=$id_uzivatele";
            $result_login_uzivatele=dbQuery($db_jmeno,$sql_login_uzivatele,$db_spojeni);
            $login_uzivatele=mysql_result($result_login_uzivatele,0,0);
          }
          else {
            $login_uzivatele=mysql_result($result_posledni_clanek,0,2);
          }
          $datum_vlozeni=date("j.n.y, H:i",$datum_ts);
        }
        else {
          $login_uzivatele="<em>nikdo</em>";
          $datum_vlozeni="nikdy";      
        }
        ?>
          <div style="width: 144px;">
            <?php echo $login_uzivatele?><br />
            <span class="druhy_radek"> <?php echo $datum_vlozeni?></span>
          </div>
        </div>
      </div>
    <?php
      
      //***********************
    }
    echo "</div>";
  }
}

//vypsani neviditelnych sekci
if (!empty($_SESSION["id_uzivatele"])){
  $sql="
    select
      viditelnost
    from
      forum_sekce
    where
      viditelnost in (
        select
          id_zidle
        from
          r_uzivatele_zidle
        where
          id_uzivatele=$_SESSION[id_uzivatele])  
    ";
    if (mysql_num_rows(dbQuery($db_jmeno,$sql,$db_spojeni))>0){


if (empty($_GET["url_druha"]) && empty($_GET["url_treti"]) && empty($_GET["url_ctvrta"])){
  $sql="select jmeno_sekce,jmeno_sekce_mini,id_sekce,popis_sekce,viditelnost from forum_sekce where viditelnost > 0 order by poradi asc";
  $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    if (mysql_num_rows($result) > 0){
    ?>
    <div class="forum_vlakna">
      <div class="hlavicka">
        <div style="width: 275px;">Pro organizátory</div>
        <div style="width: 55px;">vláken</div>
        <div style="width: 150px;">poslední příspěvěk</div>
      </div>
    <?php
    $pocitadlo_radku=0;
    while($zaznam=mysql_fetch_row($result)){
      $pocitadlo_radku++;
      //kontrola, jestli je v sekci novy prispevek
      if (!empty($_SESSION["prihlasen"])){
        $sql2="select id_podsekce,posledni_zmena from forum_podsekce where patri=$zaznam[2]";
        $result2=dbQuery($db_jmeno,$sql2,$db_spojeni);
        $novy=0;
        while($zaznam2=mysql_fetch_row($result2)){
          $sql3="select id_cteni from forum_cteno where id_podsekce=$zaznam2[0] and id_uzivatele=$_SESSION[id_uzivatele] and precteno<$zaznam2[1]";
          $result3=dbQuery($db_jmeno,$sql3,$db_spojeni);
          $sql4="select id_cteni from forum_cteno where id_podsekce=$zaznam2[0] and id_uzivatele=$_SESSION[id_uzivatele]";
          $result4=dbQuery($db_jmeno,$sql4,$db_spojeni);
          
          if ((mysql_num_rows($result3) > 0) || (mysql_num_rows($result4) == 0)){
            $novy=1;
          }
        }
      //*******************************************
      
        if ($novy == 1){
          $obrazek=" class=\"novy\"";          
          $novy=0;
        }
        else {
          $obrazek=" class=\"stary\"";
        }
      }
      else {
        $obrazek=" class=\"stary\"";
      }
      //***********************
      if (ma_pravo($_SESSION["id_uzivatele"],$zaznam[4])){
        ?>
        <div class="radek<?php echo $pocitadlo_radku%2;?>">
          <div class="ohraniceni">
            <div style="width: 50px;" <?php echo $obrazek?>>&nbsp;</div>
          <?php
          //autor, datum a čas posledního založení
          $sql_prvni_clanek="select uzivatel,datum from forum_clanky where patri=$zaznam[2] order by id_clanku asc limit 0,1";
          $result_prvni_clanek=dbQuery($db_jmeno,$sql_prvni_clanek,$db_spojeni);
          if (mysql_num_rows($result_prvni_clanek)>0){
            $id_uzivatele=mysql_result($result_prvni_clanek,0,0);
            $datum_ts=mysql_result($result_prvni_clanek,0,1);
            $sql_login_uzivatele="select login_uzivatele from uzivatele_hodnoty where id_uzivatele=$id_uzivatele";
            $result_login_uzivatele=dbQuery($db_jmeno,$sql_login_uzivatele,$db_spojeni);
            $login_uzivatele=mysql_result($result_login_uzivatele,0,0);
            $datum_vlozeni=date("j.n.y, H:i",$datum_ts);
          }
          else {
            $login_uzivatele="<em>nikdo</em>";
            $datum_vlozeni="nikdy";          
          }
          ?>
          <div style="width: 220px; text-align: left; min-height: 48px; vertical-align: top;">
            <span style="position: relative; top: 2px;"><a href="forum/<?php echo $zaznam[1]?>"><?php echo $zaznam[0]?></a></span><br />
            <span style="position: relative; top: 2px; font-style: italic;"><?php echo $zaznam[3]?></span>
          </div>
          <?php 
          //spočítání vláken
          $sql_vlaken="select id_podsekce from forum_podsekce where patri=$zaznam[2]";
          $result_vlaken=dbQuery($db_jmeno,$sql_vlaken,$db_spojeni);
          $vlaken=mysql_num_rows($result_vlaken);
          ?>
          <div style="width: 55px; line-height: 48px; "><?php echo $vlaken?></div>
          <?php 
          //autor, datum a čas posledního založení
          //$sqlx="select id_podsekce from forum_podsekce where max(posledni_zmena)";
          //$resultx=dbQuery($db_jmeno,$sqlx,$db_spojeni);
          //$id_podsekce_zobrazeni=mysql_result($resultx,0,0);
          
          $sql_posledni_clanek="select uzivatel,datum,jmeno_neregistrovany from forum_clanky where patri=(select id_podsekce from forum_podsekce where posledni_zmena=(select max(posledni_zmena) from forum_podsekce where patri=$zaznam[2])) order by id_clanku desc limit 0,1";
          //echo $sql_posledni_clanek;
          $result_posledni_clanek=dbQuery($db_jmeno,$sql_posledni_clanek,$db_spojeni);
          if (mysql_num_rows($result_posledni_clanek)>0){
            $id_uzivatele=mysql_result($result_posledni_clanek,0,0);
            if (!empty($id_uzivatele)){
              $datum_ts=mysql_result($result_posledni_clanek,0,1);
              $sql_login_uzivatele="select login_uzivatele from uzivatele_hodnoty where id_uzivatele=$id_uzivatele";
              $result_login_uzivatele=dbQuery($db_jmeno,$sql_login_uzivatele,$db_spojeni);
              $login_uzivatele=mysql_result($result_login_uzivatele,0,0);
            }
            else {
              $login_uzivatele=mysql_result($result_posledni_clanek,0,2);
            }
            $datum_vlozeni=date("j.n.y, H:i",$datum_ts);
          }
          else {
            $login_uzivatele="<em>nikdo</em>";
            $datum_vlozeni="nikdy";      
          }
          ?>
            <div style="width: 144px;">
              <?php echo $login_uzivatele?><br />
              <span class="druhy_radek"> <?php echo $datum_vlozeni?></span>
            </div>
          </div>
        </div>
      <?php 
    }  
      //***********************
    }
    echo "</div>";
  }
}
}}

// vypis vsech vlaken v zadane hlavni sekci a pro registrovane moznost vlozeni noveho vlakna
if (!empty($_GET["url_druha"]) && empty($_GET["url_treti"]) && empty($_GET["url_ctvrta"])){
  $sql="select id_sekce from forum_sekce where jmeno_sekce_mini like '$_GET[url_druha]'";
  $result=dbQuery($db_jmeno,$sql,$db_spojeni);
  if (mysql_num_rows($result) == 1){
    $id_sekce=mysql_result($result,0,0);
    $sql="select jmeno_podsekce,jmeno_podsekce_mini,id_podsekce,posledni_zmena from forum_podsekce where patri=$id_sekce order by posledni_zmena desc";
    $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    ?>
    <div class="forum_vlakna">
      <div class="hlavicka">
        <div style="width: 223px;">téma</div>
        <div style="width: 53px; padding-left: 2px;">odpovědí</div>
        <div style="width: 53px; padding-left: 2px;">zobrazení</div>
        <div style="width: 147px;">poslední příspěvěk</div>
      </div>
    <?php 
    $pocitadlo_radku=0;
    while($zaznam=mysql_fetch_row($result)){
      $pocitadlo_radku++;
      //kontrola, jestli je v sekci novy prispevek
      if (!empty($_SESSION["prihlasen"])){
        $sql3="select id_cteni from forum_cteno where id_podsekce=$zaznam[2] and id_uzivatele=$_SESSION[id_uzivatele] and precteno<$zaznam[3]";
        $result3=dbQuery($db_jmeno,$sql3,$db_spojeni);
        $sql4="select id_cteni from forum_cteno where id_podsekce=$zaznam[2] and id_uzivatele=$_SESSION[id_uzivatele]";
        $result4=dbQuery($db_jmeno,$sql4,$db_spojeni); 
        if ((mysql_num_rows($result3) > 0) || (mysql_num_rows($result4) == 0)){
          $novy=1;
        }
        if ($novy == 1){
          $obrazek=" class=\"novy\"";          
          $novy=0;
        }
        else {
          $obrazek=" class=\"stary\"";
        }
      }
      else {
        $obrazek=" class=\"stary\"";
      }
      ?>
      <div class="radek<?php echo $pocitadlo_radku%2;?>v_2">
        <div class="ohraniceni">
          <div style="width: 50px;" <?php echo $obrazek?>>&nbsp;</div>
        <?php 
        //autor, datum a čas posledního založení
        $sql_prvni_clanek="select uzivatel,datum from forum_clanky where patri=$zaznam[2] order by id_clanku asc limit 0,1";
        $result_prvni_clanek=dbQuery($db_jmeno,$sql_prvni_clanek,$db_spojeni);
        if (mysql_num_rows($result_prvni_clanek)>0){
          $id_uzivatele=mysql_result($result_prvni_clanek,0,0);
          $datum_ts=mysql_result($result_prvni_clanek,0,1);
          $sql_login_uzivatele="select login_uzivatele from uzivatele_hodnoty where id_uzivatele=$id_uzivatele";
          $result_login_uzivatele=dbQuery($db_jmeno,$sql_login_uzivatele,$db_spojeni);
          if (mysql_num_rows($result_login_uzivatele) == 0){
            $login_uzivatele="(smazán)";
          }
          else {
            $login_uzivatele=mysql_result($result_login_uzivatele,0,0);
          }
          $datum_vlozeni=date("j.n.y",$datum_ts);
        }
        else {
          $login_uzivatele="<em>nikdo</em>";
          $datum_vlozeni="nikdy";          
        }
        ?>
        <div style="width: 170px; text-align: left;">
          <a href="forum/<?php echo $_GET["url_druha"]?>/<?php echo $zaznam[1]?>">
          <?php 
          if (strlen($zaznam[0])>70){
            echo substr($zaznam[0],0,69)."&hellip;";
          }
          else {
            echo $zaznam[0];
          }
          ?>
          </a><br />
          <span class="druhy_radek"><?php echo "<strong>".$login_uzivatele."</strong>, ".$datum_vlozeni?><?php //if (ma_pravo($_SESSION["id_uzivatele"],1)){echo ' <a href="">smazat vlákno</a>';}?></span>
        </div>
        <?php 
        //spočítání odpovědí
        $sql_odpovedi="select id_clanku from forum_clanky where patri=$zaznam[2]";
        $result_odpovedi=dbQuery($db_jmeno,$sql_odpovedi,$db_spojeni);
        $odpovedi=mysql_num_rows($result_odpovedi);
        ?>
        <div style="width: 55px; line-height: 48px;"><?php echo $odpovedi?></div>
        <?php 
        //spočítání poctu zobrazeni
        $sql_zobrazeni="select precteno from forum_podsekce where id_podsekce=$zaznam[2]";
        $result_zobrazeni=dbQuery($db_jmeno,$sql_zobrazeni,$db_spojeni);
        $zobrazeni=mysql_result($result_zobrazeni,0,0);
        ?>
        <div style="width: 55px; line-height: 48px;"><?php echo $zobrazeni?></div>
        <?php 
        //autor, datum a čas posledního založení
        $sql_posledni_clanek="select uzivatel,datum,jmeno_neregistrovany from forum_clanky where patri=$zaznam[2] order by id_clanku desc limit 0,1";
        $result_posledni_clanek=dbQuery($db_jmeno,$sql_posledni_clanek,$db_spojeni);
        if (mysql_num_rows($result_posledni_clanek)>0){
          $id_uzivatele=mysql_result($result_posledni_clanek,0,0);
          if (!empty($id_uzivatele)){
            $datum_ts=mysql_result($result_posledni_clanek,0,1);
            $sql_login_uzivatele="select login_uzivatele from uzivatele_hodnoty where id_uzivatele=$id_uzivatele";
            $result_login_uzivatele=dbQuery($db_jmeno,$sql_login_uzivatele,$db_spojeni);
            if (mysql_num_rows($result_login_uzivatele)>0){
              $login_uzivatele=mysql_result($result_login_uzivatele,0,0);
            }
            else {
              $login_uzivatele="(uživatel smazán)";
            }
          }
          else {
            $login_uzivatele=mysql_result($result_posledni_clanek,0,2);
          }
          $datum_vlozeni=date("j.n.y, H:i",$datum_ts);
        }
        else {
          $login_uzivatele="<em>nikdo</em>";
          $datum_vlozeni="nikdy";      
        }
        ?>
          <div style="width: 144px;">
            <?php echo $login_uzivatele?><br />
            <span class="druhy_radek"><?php echo $datum_vlozeni?></span>
          </div>
        </div>
      </div>
    <?php 
    }
    echo "</div>";
  }
  else {
    $chyba_zobraz .= "chyba! tato podsekce neexistuje! (nebo ma stejne jmeno jako nejaka uz existujici, kazdopadne error)";
  }
  if ($_SESSION["prihlasen"] == 0){
    echo "<strong>Pro založení nového vlákna se nejdříve přihlašte nebo zaregistrujte.</strong>"; 
  }
}

//chyba, nesmi nastat
if (!empty($_GET["url_druha"]) && !empty($_GET["url_treti"]) && is_numeric($_GET["url_treti"])){
  echo "sorry";
}

//zobrazeni prispevku ve vlakne, jestli je uzivatel nalogovan, moznost zapisu + oznaceni vlakna jako precteneho
if (!empty($_GET["url_druha"]) && !empty($_GET["url_treti"]) && !is_numeric($_GET["url_treti"]) && !isset($_GET["url_ctvrta"])){
  $sql="select id_sekce from forum_sekce where jmeno_sekce_mini like '$_GET[url_druha]'";
  $result=dbQuery($db_jmeno,$sql,$db_spojeni);
  $id_sekce=mysql_result($result,0,0);
  $sql="select id_podsekce from forum_podsekce where jmeno_podsekce_mini like '$_GET[url_treti]' and patri=$id_sekce";
  $result=dbQuery($db_jmeno,$sql,$db_spojeni);
  $id_podsekce=mysql_result($result,0,0);
  
  //pocitani zobrazeni & oznaceni precteni
  vlakno_precteno_obecne($id_podsekce);
  if ($_SESSION["prihlasen"]>0){
    vlakno_precteno($id_podsekce);
  }
    
  $sql="select id_clanku from forum_clanky where patri=$id_podsekce";
  $result=dbQuery($db_jmeno,$sql,$db_spojeni);
  $prispevku_ve_vlakne=mysql_num_rows($result);
  if ($_SESSION["razeni_fora"] == "s"){
      $pocatecni=0;
      $strana=1;
  }
  else {
    $strana=ceil($prispevku_ve_vlakne / $prispevku_ns);
    if ($strana > 1){
      $pocatecni=(($strana-1)*$prispevku_ns);
    }
    else{
      $pocatecni=0;
    }
  }
  if (($_SESSION["razeni_fora"] == "v") && ($_SESSION["prihlasen"] > 0)){
    $sql="select id_clanku from forum_clanky where patri=$id_podsekce order by id_clanku asc limit $pocatecni,$prispevku_ns";
    //echo $sql."<br />";
    $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    ?>
    <div class="forum_vlakna">
      <div class="hlavicka">
        <div style="width: 460px; padding: 0px 10px 0px 10px; text-align: left;">
          <?php 
            $sql_nazev="select jmeno_podsekce from forum_podsekce where jmeno_podsekce_mini like '$_GET[url_treti]'";
            $result_nazev=dbQuery($db_jmeno,$sql_nazev,$db_spojeni);
            echo mysql_result($result_nazev,0,0);
          ?>
        </div>
      </div>
    <?php 
    $pocitadlo_radku++;
    while($zaznam=mysql_fetch_row($result)){
      zobraz_prispevek($zaznam[0]);
    }
    ?>
    </div>
    <?php 
    strankovani_vlakna($id_podsekce,$strana,$prispevku_ns);
    
    if ($_SESSION["prihlasen"] > 0){
      zobraz_vkladani($id_podsekce);
    }
    else{
      echo "<strong>Pro vložení nového příspěvku se nejdříve přihlašte nebo zaregistrujte.</strong>";
    }
  }
  elseif (($_SESSION["razeni_fora"] == "s") && ($_SESSION["prihlasen"] > 0)) {    
    zobraz_vkladani($id_podsekce);
    $sql="select id_clanku from forum_clanky where patri=$id_podsekce order by id_clanku desc limit $pocatecni,$prispevku_ns";
    //echo $sql."<br />";
    $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    ?>
    <div class="forum_vlakna">
      <div class="hlavicka">
        <div style="width: 460px; padding: 0px 10px 0px 10px; text-align: left;">
          <?php 
            $sql_nazev="select jmeno_podsekce from forum_podsekce where jmeno_podsekce_mini like '$_GET[url_treti]'";
            $result_nazev=dbQuery($db_jmeno,$sql_nazev,$db_spojeni);
            echo mysql_result($result_nazev,0,0);
          ?>
        </div>
      </div>
    <?php 
    while($zaznam=mysql_fetch_row($result)){
      zobraz_prispevek($zaznam[0]);
    }
    ?>
    </div>
    <?php 
    strankovani_vlakna($id_podsekce,$strana,$prispevku_ns);
  }
  else {
    $sql="select id_clanku from forum_clanky where patri=$id_podsekce order by id_clanku asc limit $pocatecni,$prispevku_ns";
    //echo $sql."<br />";
    $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    ?>
    <div class="forum_vlakna">
      <div class="hlavicka">
        <div style="width: 460px; padding: 0px 10px 0px 10px; text-align: left;">
          <?php 
            $sql_nazev="select jmeno_podsekce from forum_podsekce where jmeno_podsekce_mini like '$_GET[url_treti]'";
            $result_nazev=dbQuery($db_jmeno,$sql_nazev,$db_spojeni);
            echo mysql_result($result_nazev,0,0);
          ?>
        </div>
      </div>
    <?php 
    $pocitadlo_radku++;
    while($zaznam=mysql_fetch_row($result)){
      zobraz_prispevek($zaznam[0]);
    }
    ?>
    </div>
    <?php 
    strankovani_vlakna($id_podsekce,$strana,$prispevku_ns);
    
    //****************anonymni prispivani
    zobraz_vkladani_anonym($id_podsekce);
    
  }
}

if (!empty($_GET["url_druha"]) && !empty($_GET["url_treti"]) && is_numeric($_GET["url_ctvrta"])){
  $sql="select id_sekce from forum_sekce where jmeno_sekce_mini like '$_GET[url_druha]'";
  $result=dbQuery($db_jmeno,$sql,$db_spojeni);
  $id_sekce=mysql_result($result,0,0);
  $sql="select id_podsekce from forum_podsekce where jmeno_podsekce_mini like '$_GET[url_treti]' and patri=$id_sekce";
  $result=dbQuery($db_jmeno,$sql,$db_spojeni);
  $id_podsekce=mysql_result($result,0,0);
  
  if ($_SESSION["razeni_fora"] == "s"){
    zobraz_vkladani($id_podsekce);
    $pocatecni_prispevek=($_GET["url_ctvrta"]*$prispevku_ns)-$prispevku_ns;
    $sql="select id_clanku from forum_clanky where patri=$id_podsekce order by id_clanku desc limit $pocatecni_prispevek,$prispevku_ns";
  }
  else {
    $pocatecni_prispevek=($_GET["url_ctvrta"]*$prispevku_ns)-$prispevku_ns;
    $sql="select id_clanku from forum_clanky where patri=$id_podsekce order by id_clanku asc limit $pocatecni_prispevek,$prispevku_ns";
  }
  $result=dbQuery($db_jmeno,$sql,$db_spojeni);
  ?>
    <div class="forum_vlakna">
      <div class="hlavicka">
        <div style="width: 460px; padding: 0px 10px 0px 10px; text-align: left;">
          <?php 
            $sql_nazev="select jmeno_podsekce from forum_podsekce where jmeno_podsekce_mini like '$_GET[url_treti]'";
            $result_nazev=dbQuery($db_jmeno,$sql_nazev,$db_spojeni);
            echo mysql_result($result_nazev,0,0);
          ?>
        </div>
      </div>
    <?php 
    $pocitadlo_radku++;
    while($zaznam=mysql_fetch_row($result)){
      zobraz_prispevek($zaznam[0]);
    }
    ?>
    </div>
    <?php 
  strankovani_vlakna($id_podsekce,$_GET["url_ctvrta"],$prispevku_ns);
  
  if (($_SESSION["prihlasen"] > 0) && $_SESSION["razeni_fora"] == "v"){
    zobraz_vkladani($id_podsekce);
  }
  elseif ($_SESSION["prihlasen"] == 0) {
    //echo "<strong>Pro vložení nového příspěvku se nejdříve přihlašte nebo zaregistrujte.</strong>"; 
  }
  if ($_SESSION["prihlasen"]>0){
    vlakno_precteno($id_podsekce);
  }
}
/*
echo "<br /><br /><br /><br />";
$chyba_zobraz .= "url prvni ".$_GET["url_prvni"]."<br />";
$chyba_zobraz .= "url druha ".$_GET["url_druha"]."<br />";
$chyba_zobraz .= "url treti ".$_GET["url_treti"]."<br />";
$chyba_zobraz .= "url ctvrta ".$_GET["url_ctvrta"]."<br />";
echo "<br /><br /><br /><br />";
*/

//print(time());

?>
