<?php

/** 
 * Správa uživatelských práv a židlí (starý kód)
 *
 * nazev: Práva
 * pravo: 106
 */
 
$db_jmeno=null;
$db_spojeni=null;
$_POST['akce']=isset($_POST['akce'])?$_POST['akce']:null;
$_POST['detail_akce']=isset($_POST['detail_akce'])?$_POST['detail_akce']:null;

if ($_POST["akce"] == "detail_zidle"){
  if ($_POST["detail_akce"] == "odeber_pravo"){
    $sql="
      delete from
        r_prava_zidle
      WHERE id_zidle=$_POST[cislo_zidle]
      AND id_prava=$_POST[cislo_prava]
    ";
    if (dbQuery($db_jmeno,$sql,$db_spojeni)){
      $chyba_zobraz="<br /><strong>Právo odebráno!</strong><br /><br />";
    }
  }
  if ($_POST["detail_akce"] == "sesadit_uzivatele"){
    $sql="
      delete from
        r_uzivatele_zidle
      where
        id_uzivatele=$_POST[cislo_uzivatele] and
        id_zidle=$_POST[cislo_zidle]
    ";
    if (dbQuery($db_jmeno,$sql,$db_spojeni)){
      $chyba_zobraz="<br /><strong>Uživateli byla podtrhnuta židle!</strong><br /><br />";
    }
  }
  if ($_POST["detail_akce"] == "sesadit_aktivniho"){
    $sql="
      delete from
        r_uzivatele_zidle
      where
        id_uzivatele=$_SESSION[id_uzivatele] and
        id_zidle=$_POST[cislo_zidle]
    ";
    if (dbQuery($db_jmeno,$sql,$db_spojeni)){
      $chyba_zobraz="<br /><strong>Aktivnímu uživateli byla podtrhnuta židle!</strong><br /><br />";
    }
  }
  if ($_POST["detail_akce"] == "posadit_aktivniho"){
    $sql="
      insert into
        r_uzivatele_zidle
        (id_uzivatele,id_zidle)
      values
        ($_SESSION[id_uzivatele],$_POST[cislo_zidle])        
    ";
    if (dbQuery($db_jmeno,$sql,$db_spojeni)){
      $chyba_zobraz="<br /><br /><strong>Aktivní uživatel posazen na židli.</strong><br /><br />";
    }
  }
  if ($_POST["detail_akce"] == "pridej_pravo"){
    $sql="
      insert into
        r_prava_zidle
        (id_prava,id_zidle)
      values
        ($_POST[id_prava],$_POST[cislo_zidle]);
    ";
    if (dbQuery($db_jmeno,$sql,$db_spojeni)){
      $chyba_zobraz="<br /><strong>Právo přidáno!</strong><br /><br />";
    }
  }
  
  if (isset($_SESSION["id_uzivatele"])){
    $sql="
      select
        id_uzivatele
      from
        r_uzivatele_zidle
      where
        id_uzivatele=$_SESSION[id_uzivatele] and
        id_zidle=$_POST[cislo_zidle];
    ";
    $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    if (mysql_num_rows($result)){
      ?>
        <form  method="post">
          <input type="hidden" name="akce" value="detail_zidle" />
          <input type="hidden" name="detail_akce" value="sesadit_aktivniho" />
          <input type="hidden" name="cislo_zidle" value="<?php echo $_POST["cislo_zidle"]?>" />
          <input type="submit" value="Sesadit aktivního uživatele z židle" />
        </form>
      <?php
    }
    else {
      ?>
        <form  method="post">
          <input type="hidden" name="akce" value="detail_zidle" />
          <input type="hidden" name="detail_akce" value="posadit_aktivniho" />
          <input type="hidden" name="cislo_zidle" value="<?php echo $_POST["cislo_zidle"]?>" />
          <input type="submit" value="Posadit aktivního uživatele na tuto židli" />
        </form>
      <?php
    }
  }
  
  if (isset($chyba_zobraz)){
    echo $chyba_zobraz;
  }
  
  $sql="
    select
      zidle.jmeno_zidle,
      zidle.popis_zidle
    from
      r_zidle_soupis zidle
    where
      zidle.id_zidle=$_POST[cislo_zidle]
  ";
  $result=dbQuery($db_jmeno,$sql,$db_spojeni);
  echo "Detail židle <strong>".mysql_result($result,0,0)." (".mysql_result($result,0,1).")</strong><br />";
  $sql="
    select
      sroubky.id_prava,
      prava.jmeno_prava,
      prava.popis_prava
      -- sroubky.id_sroubku -- zrušeno
    from
      r_prava_soupis prava,
      r_prava_zidle sroubky
    where
      sroubky.id_zidle=$_POST[cislo_zidle] and
      sroubky.id_prava=prava.id_prava      
    order by prava.id_prava asc
  ";
  $result=dbQuery($db_jmeno,$sql,$db_spojeni);
  if (mysql_num_rows($result)){
    ?>
    <table>
      <tr>
        <td><strong>Název práva</strong></td>
        <td><strong>Popis práva</strong></td>
        <td></td>
      </tr>
    <?php
    while ($zaznam=mysql_fetch_row($result)){
    ?>
      <form  method="post" id="odeber_pravo<?php echo $zaznam[0]?>">
        <input type="hidden" name="akce" value="detail_zidle" />
        <input type="hidden" name="detail_akce" value="odeber_pravo" />
        <input type="hidden" name="cislo_zidle" value="<?php echo $_POST["cislo_zidle"]?>" />
        <input type="hidden" name="cislo_prava" value="<?php echo $zaznam[0]?>" />
      </form>
      <tr>
        <td><?php echo $zaznam[1]?></td>
        <td><?php echo $zaznam[2]?></td>
        <td><a href="javascript: document.getElementById('odeber_pravo<?php echo $zaznam[0]?>').submit()">vzít židli právo</a></td>
      </tr>
        <?php
    }
    echo "</table>";
  }
  else {
    echo "Tato židle nemá žádné právo";
  }
  $sql="
    select
      prava.id_prava,
      prava.jmeno_prava
    from
      r_prava_soupis prava
    where 
      prava.id_prava > 0
    and
      prava.id_prava not in (
        select
          sroubky.id_prava
        from
          r_prava_zidle sroubky
        where
          sroubky.id_zidle=$_POST[cislo_zidle]
      )
    order by prava.jmeno_prava asc
  ";
  $result=dbQuery($db_jmeno,$sql,$db_spojeni);
  if (mysql_num_rows($result)){
    ?>
    <form  method="post">
      <input type="hidden" name="akce" value="detail_zidle" />
      <input type="hidden" name="detail_akce" value="pridej_pravo">
      <input type="hidden" name="cislo_zidle" value="<?php echo $_POST["cislo_zidle"]?>">
      <select name="id_prava">
        
    <?php
    while ($zaznam=mysql_fetch_row($result)){
      ?>
      <option value="<?php echo $zaznam[0]?>"><?php echo $zaznam[1]?></option>
      <?php
    }
    ?></select>
      <input type="submit" value="Přidat židli právo" />
    </form>
    <?php
  }
  ?>
  <br /><br />
  <strong>Seznam uživatelů na této židli:</strong><br />
  <?php
  $sql="
    select
      uzivatele.id_uzivatele,
      uzivatele.id_uzivatele,
      uzivatele.login_uzivatele,
      uzivatele.jmeno_uzivatele,
      uzivatele.prijmeni_uzivatele
    from
      uzivatele_hodnoty uzivatele,
      r_uzivatele_zidle sezeni
    where
      uzivatele.id_uzivatele=sezeni.id_uzivatele and
      sezeni.id_zidle=$_POST[cislo_zidle]
    order by uzivatele.login_uzivatele;
  ";
  $result=dbQuery($db_jmeno,$sql,$db_spojeni);
  if (mysql_num_rows($result)){
    ?>
    <table class="zvyraznovana">
      <tr>
        <th>id</th>
        <th>login</th>
        <th>jméno a příjmení</th>
        <th></th>
      </tr>
    <?php
    while ($zaznam=mysql_fetch_row($result)){
    ?>
    <tr>
      <form  method="post" id="sesadit_uzivatele<?php echo $zaznam[0]?>">
        <input type="hidden" name="akce" value="detail_zidle" />
        <input type="hidden" name="detail_akce" value="sesadit_uzivatele">
        <input type="hidden" name="cislo_zidle" value="<?php echo $_POST["cislo_zidle"]?>">
        <input type="hidden" name="cislo_uzivatele" value="<?php echo $zaznam[0]?>">
      </form>
      <td><?php echo $zaznam[0]?></td>
      <td><?php echo $zaznam[2]?></strong></td>
      <td><?php echo $zaznam[3]." ".$zaznam[4]?></td>
      <td><a href="javascript: document.getElementById('sesadit_uzivatele<?php echo $zaznam[0]?>').submit()">sesadit uživatele ze židle</a></td>
    </tr>
    <?php
    }
    ?> </table> <?php
  }
  else {
    echo "Na této židli nesedí žádný uživatel.";
  }
}
else{
  if ($_POST["akce"] == "pridej_uzivatele"){
    $sql="
      insert into
        r_uzivatele_zidle
        (id_uzivatele,id_zidle)
      values
        ($_SESSION[id_uzivatele],$_POST[cislo_zidle])        
    ";
    if (dbQuery($db_jmeno,$sql,$db_spojeni)){
      $chyba_zobraz="<br /><br /><strong>Uživatel posazen na židli.</strong><br /><br />";
    }
  }
 if ($_POST["akce"] == "odeber_uzivatele"){
  $sql="
    delete from
      r_uzivatele_zidle
    where
      id_zidle=$_POST[cislo_zidle] and
      id_uzivatele=$_SESSION[id_uzivatele]
  ";
    if (dbQuery($db_jmeno,$sql,$db_spojeni)){
      $chyba_zobraz="<br /><br /><strong>Uživateli byla odebrána zvolená židle.</strong><br /><br />";
    }
  }
  if ($_POST["akce"] == "pridej_zidli"){
    if (!empty($_POST["popis_zidle"])){
      $sql="
        select
          jmeno_zidle
        from
          r_zidle_soupis
        where
          jmeno_zidle like '$_POST[jmeno_zidle]'
      ";
      $result=dbQuery($db_jmeno,$sql,$db_spojeni);
      if (mysql_num_rows($result)){
        $chyba_zobraz="<br /><br />Židle tohoto jména již existuje!<br /><br />";
      }
      else {
        $sql="
          insert into
            r_zidle_soupis
            (jmeno_zidle,popis_zidle)
          values
            ('$_POST[jmeno_zidle]','$_POST[popis_zidle]') 
        ";
        echo $sql;
        if (dbQuery($db_jmeno,$sql,$db_spojeni)){
          $chyba_zobraz="<br /><br />Židle přidána!<br /><br />";
        }
      }
    }
    else {
      $chyba_zobraz="<br /><br />Nebylo zadáno jméno židle!<br /><br />";
    }
  }
  ?>
  Seznam všech existujících židlí (souborů práv)(bez systémových):
  <?php
  $sql="
    SELECT id_zidle, jmeno_zidle, popis_zidle
    FROM r_zidle_soupis
    WHERE id_zidle>0
    ORDER BY jmeno_zidle ASC
  ";
  $result=dbQuery($db_jmeno,$sql,$db_spojeni);
  if (mysql_num_rows($result)){
    ?>
    <table class="zvyraznovana">
      <tr>
        <th>Jméno židle</th>
        <th>Popis židle</th>
        <th style="width: 150px"></th>
        <th style="width: 65px"></th>
      </tr>
    <?php
    while ($zaznam=mysql_fetch_row($result)){
  ?>
      <tr>
        <td><?php echo $zaznam[1]?></td>
        <td><?php echo $zaznam[2]?></td>
        <td>
        <?php
        if (!empty($_SESSION["id_uzivatele"])){
          $sql2="
            select
              ''
            from
              r_uzivatele_zidle
            where
              id_uzivatele=$_SESSION[id_uzivatele] and
              id_zidle=$zaznam[0]
          ";
          //echo $sql2;
          $result2=dbQuery($db_jmeno,$sql2,$db_spojeni);
          if (mysql_num_rows($result2) > 0){
            echo "<a href=\"javascript: document.getElementById('odeber_uzivatele$zaznam[0]').submit()\">odebrat uživateli tuto židli</a>";
          }
          else {
            echo "<a href=\"javascript: document.getElementById('pridej_uzivatele$zaznam[0]').submit()\">posadit uživatele na židli</a>";
          }
        }
        else {
          echo "není vybrán uživatel";
        }
        ?></td>
        <td><a href="javascript: document.getElementById('detail_zidle<?php echo $zaznam[0]?>').submit()">detail židle</a></td>
        <form id="pridej_uzivatele<?php echo $zaznam[0]?>"  method="post" /><input type="hidden" name="akce" value="pridej_uzivatele"><input type="hidden" name="cislo_zidle" value="<?php echo $zaznam[0]?>" /></form>
        <form id="odeber_uzivatele<?php echo $zaznam[0]?>"  method="post" /><input type="hidden" name="akce" value="odeber_uzivatele"><input type="hidden" name="cislo_zidle" value="<?php echo $zaznam[0]?>" /></form>
        <form id="detail_zidle<?php echo $zaznam[0]?>"  method="post" /><input type="hidden" name="akce" value="detail_zidle"><input type="hidden" name="cislo_zidle" value="<?php echo $zaznam[0]?>" /></form>
      </tr>
  <?php
    }
    echo "</table>";
    if (isset($chyba_zobraz)){
      echo $chyba_zobraz;
    }
  }
  
  ?>
  <br /><br />
  <strong>Přidat novou židli</strong><br /><br />
  <form  method="post">
    <input type="hidden" name="akce" value="pridej_zidli" />
    Jméno židle: <input type="text" name="jmeno_zidle" value="" /><br />
    Popis židle: <input type="text" name="popis_zidle" value="" />
    <input type="submit" value="Přidat židli" />
  </form>
  <?php
  
}
?>
