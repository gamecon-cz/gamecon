<?php

/** 
 * Stránka k editaci ubytovacích informací a artefaktů (převážně starý kód)
 *
 * nazev: Ubytování
 * pravo: 101
 */

$den1=$den2=$den3=$den4=false; //inicializace implicitních proměnných v starém kódu

if (post("objednano_upravit") == 1){
  if (post('placka') == "on"){
    $placka=1;
  }
  else {
    $placka=0;
  }
  if (post('kostka') == "on"){
    $kostka=1;
  }
  else {
    $kostka=0;
  }
  if (post('student') == "on"){
    $student=1;
  }
  else {
    $student=0;
  }
  
  $sql="
    update
      prihlaska_ostatni
    set
      tricko='$_POST[tricko]',
      placka=$placka,
      student=$student,
      kostka=$kostka
    where
      id_uzivatele=$_SESSION[id_uzivatele]
      and rok=".var_getvalue_sn('rok').";
  ";
  dbQuery($sql);
  echo "<div class=\"adm_box\" style=\"border: 1px solid red\"><strong>Objednávky změněny.</strong></div>";  
}





if(post("ubytovani_upravit") == 1){
  $sql="
    delete from
      prihlaska_ubytovani
    where
      id_uzivatele=$_SESSION[id_uzivatele]
      and rok=".var_getvalue_sn('rok').";
  ";
  dbQuery($sql);
  
  $sql="
    update
      prihlaska_ostatni
    set
      ubytovani=$_POST[ubytovani],
      pokoj=$_POST[pokoj]
    where
      id_uzivatele=$_SESSION[id_uzivatele]
      and rok=".var_getvalue_sn('rok').";
  ";
  dbQuery($sql);
  
  if (post("den1") == "on"){
    $sql="
      insert into
        prihlaska_ubytovani
        (id_uzivatele,den,rok)
      values
        ($_SESSION[id_uzivatele],1,".var_getvalue_sn('rok').")
    ";
    dbQuery($sql);
  }
  if (post('den2') == "on"){
    $sql="
      insert into
        prihlaska_ubytovani
        (id_uzivatele,den,rok)
      values
        ($_SESSION[id_uzivatele],2,".var_getvalue_sn('rok').")
    ";
    dbQuery($sql);
  }
  if (post('den3') == "on"){
    $sql="
      insert into
        prihlaska_ubytovani
        (id_uzivatele,den,rok)
      values
        ($_SESSION[id_uzivatele],3,".var_getvalue_sn('rok').")
    ";
    dbQuery($sql);
  }
  if (post('den4') == "on"){
    $sql="
      insert into
        prihlaska_ubytovani
        (id_uzivatele,den,rok)
      values
        ($_SESSION[id_uzivatele],4,".var_getvalue_sn('rok').")
    ";
    dbQuery($sql);
  }
  
  echo "<div class=\"adm_box\" style=\"border: 1px solid red\"><strong>Ubytovací informace změněny.</strong></div>";
  
}

if(post("vyhledavani") == 1){
    $vyhledavej=true;
    if (is_numeric(post('retezec'))){
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
    $result=dbQuery($sql);
    if (mysql_num_rows($result) > 0){
      echo "<table>";
      echo "<tr style=\"font-weight: bold;\">";
      echo "<td>GC ID</td><td>jméno a příjmení</td><td>login</td><td>email</td><td>město</td><td>vybrat pro práci</td>";
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
                <input type="submit" value="Vybrat" />
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
      $result=dbQuery($sql);
      echo "
        <div class=\"aktiv\"><strong>Aktivní uživatel</strong> » login (jméno a příjmení): <strong>".mysql_result($result,0,0)." (".mysql_result($result,0,1)." ".mysql_result($result,0,2)." - GC ID: ".mysql_result($result,0,3).")</strong> <a href=\"javascript: document.getElementById('zrusit').submit()\">zrušit práci s uživatelem</a></div>";
      ?>
      <form method="post" style="display: inline;" id="zrusit">
        <input type="hidden" name="uzivatele_zrusit" value="1" />
      </form>
      
      <?
      if(isset($chyba_odhlaseni) && $chyba_odhlaseni == true){
        ?>
        <div class="adm_box" style="border: 5px solid red; background-color: white;;">
          <strong>Tento uživatel nedostal materiály nebo má záporný zústatek na účtu!</strong><br />
          <a href="javascript: document.getElementById('zrusit2').submit()">Ano, i přesto zrušit práci s uživatelem</a>
          <form method="post" style="display: inline;" id="zrusit2">
            <input type="hidden" name="uzivatele_zrusit" value="2" />
          </form>
        </div>
        <?
      }
      
      if (ma_pravo($_SESSION["id_uzivatele"],2)){
        ?>
        <div class="adm_box" style="background-color: skyblue;">
          <strong>Tento uživatel je organizátor!</strong>
        </div>
        <?
      }
    
    //ubytování
    $sql="
      select
        ubytovani,
        pokoj
      from
        prihlaska_ostatni
      where
        id_uzivatele=$_SESSION[id_uzivatele]
        and rok=".var_getvalue_sn('rok').";
    ";
    $result=dbQuery($sql);
    $ubyt=mysql_result($result,0,0);
    $pokoj=mysql_result($result,0,1);
    
    $sql="
      select
        den
      from
        prihlaska_ubytovani
      where
        id_uzivatele=$_SESSION[id_uzivatele]
        and rok=".var_getvalue_sn('rok').";
    ";
    $result=dbQuery($sql);
    if (mysql_num_rows($result) > 0){
      while($zaznam=mysql_fetch_row($result)){
        switch ($zaznam[0]) {
          case 1: $den1=true;
          break;
          case 2: $den2=true;
          break;
          case 3: $den3=true;
          break;
          case 4: $den4=true;
          break;
        }
      }
    }
    ?>
    <div class="adm_box">
    <h3>Ubytování</h3>
    <form method="post">
      <input type="hidden" name="ubytovani_upravit" value="1"/>
      <table class="invisible_table">
        <tr>
          <td><strong>Typ ubytování:</strong></td><td>
            <select name="ubytovani"  style="width: 100px;">
              <option value="0" <?if ($ubyt == 0){echo "selected=\"selected\"";}?>>žádné</option>
              <option value="1" <?if ($ubyt == 1){echo "selected=\"selected\"";}?>>pokoj 3</option>
              <option value="2" <?if ($ubyt == 2){echo "selected=\"selected\"";}?>>třída</option>
              <option value="3" <?if ($ubyt == 3){echo "selected=\"selected\"";}?>>pokoj 2</option>
            </select>
          </td>
        </tr>
        <tr>
          <td><strong>Dny ubytování:</strong></td>
          <td><input type="checkbox" name="den1" <?if ($den1 == true){echo "checked=\"checked\"";}?>/>&nbsp;&nbsp;<?php echo $PROGRAM_DATA[0] ?> (z čtvrtka na pátek)</td>
        </tr>
        <tr>
          <td></td>
          <td><input type="checkbox" name="den2" <?if ($den2 == true){echo "checked=\"checked\"";}?>/>&nbsp;&nbsp;<?php echo $PROGRAM_DATA[1] ?> (z pátku na sobotu)</td>
        </tr>
        <tr>
          <td></td>
          <td><input type="checkbox" name="den3" <?if ($den3 == true){echo "checked=\"checked\"";}?>/>&nbsp;&nbsp;<?php echo $PROGRAM_DATA[2] ?> (ze soboty na neděli)</td>
        </tr>
        <tr>
          <td></td>
          <td><input type="checkbox" name="den4" <?if ($den4 == true){echo "checked=\"checked\"";}?>/>&nbsp;&nbsp;<?php echo $PROGRAM_DATA[3] ?> (z neděle na pondělí)</td>
        </tr>
        <tr>
          <td><strong>Číslo pokoje:</strong></td>
          <td><input type="text" name="pokoj" value="<?echo $pokoj?>"  style="width: 100px;" /></td>
        </tr>
        <tr>
          <td colspan="2"><input type="submit" value="Změnit ubytovací informace" /></td>
        </tr>
      </table>
    </form>
    </div>                        
    
    <?
    $sql="
    select
      tricko,
      placka,
      student,
      kostka
    from
      prihlaska_ostatni
    where
      id_uzivatele=$_SESSION[id_uzivatele]
      and rok=".var_getvalue_sn('rok')."; 
    ";
    $result=dbQuery($sql);
    $tricko=mysql_result($result,0,0);
    $placka=mysql_result($result,0,1);
    $student=mysql_result($result,0,2);
    $kostka=mysql_result($result,0,3);
    
    ?>
    
    <div class="adm_box">
    <h3>Objednáno</h3>
    <form method="post">
      <input type="hidden" name="objednano_upravit" value="1"/>
      <table class="invisible_table">
        <tr>
          <td><strong>Objednáno tričko:</strong></td><td>
            <select name="tricko" style="width: 100px;">
              <option value="0" <?if ($tricko == '0'){echo "selected=\"selected\"";}?>>žádné</option>
              <option value="S" <?if ($tricko == 'S'){echo "selected=\"selected\"";}?>>pánské S</option>
              <option value="M" <?if ($tricko == 'M'){echo "selected=\"selected\"";}?>>pánské M</option>
              <option value="L" <?if ($tricko == 'L'){echo "selected=\"selected\"";}?>>pánské L</option>
              <option value="XL" <?if ($tricko == 'XL'){echo "selected=\"selected\"";}?>>pánské XL</option>
              <option value="XXL" <?if ($tricko == 'XXL'){echo "selected=\"selected\"";}?>>pánské XXL</option>
              <option value="dS" <?if ($tricko == 'dS'){echo "selected=\"selected\"";}?>>dámské S</option>
              <option value="dM" <?if ($tricko == 'dM'){echo "selected=\"selected\"";}?>>dámské M</option>
              <option value="dL" <?if ($tricko == 'dL'){echo "selected=\"selected\"";}?>>dámské L</option>
            </select>
          </td>
        </tr>
        <tr>
          <td><strong>Objednána placka:</strong></td>
          <td><input type="checkbox" name="placka" <?if ($placka == 1){echo "checked=\"checked\"";}?>/></td>
        </tr>
        <tr>
          <td><strong>Objednána kostka:</strong></td>
          <td><input type="checkbox" name="kostka" <?if ($kostka == 1){echo "checked=\"checked\"";}?>/></td>
        </tr>
        <tr>
          <td><strong>Jako student:</strong></td>
          <td><input type="checkbox" name="student" <?if ($student == 1){echo "checked=\"checked\"";}?>/></td>
        </tr>
        <tr>
          <td colspan="2"><input type="submit" value="Změnit objednávky" /></td>
        </tr>
      </table>
    </form>
    </div>
    
    
    
    <div class="adm_box" style="border: 1px solid red;">
      <h3>Zrušit práci</h3>    
      <form method="post">
        <input type="hidden" name="uzivatele_zrusit" value="1" />
        <input type="submit" value="Zrušit práci s tímto uživatelem" />
      </form>
    </div>
    <?
}
else {
  ?>
  <div class="adm_box">
      <strong>Žádný uživatel není vybrán pro práci. Uživatele můžeš vyhledat podle příjmení, přezdívky, emailu nebo jeho id.</strong><br />
      <br /><form method="post">
        <input type="hidden" name="vyhledavani" value="1"/>
        <strong>Vyhledat:</strong> <input type="text" name="retezec" style="width: 250px;" />
        <input type="submit" value="Vyhledávat" />
      </form>
    </div>
  <?
}
?>
