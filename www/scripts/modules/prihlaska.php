<?php

if(!REGISTRACE_AKTIVNI) { echo hlaska('prihlaseniVypnuto'); return; }
if(GAMECON_BEZI)
{
  echo '<h1 style="color: red;">GameCon začal, veškeré registrace přes internet jsou ukončeny</h1>
  <h2 style="color: red;">Jakékoliv další změny jsou možné u infopultu v deskoherně</h2>';
}
//test na přihlášenost viz níž

//test plnosti ubytování
//funguje jen zde, jde spíš o hack
$NABIDKA_POKOJE=true;
$NABIDKA_POKOJE_2=true; //2L pokoj
$NABIDKA_TRIDY=true;
$a=dbQuery('
  SELECT ubytovani as typ, count(1) as pocet
  FROM prihlaska_ostatni
  WHERE rok='.ROK.'
  GROUP BY ubytovani
  ORDER BY ubytovani');
while($r=mysql_fetch_assoc($a))
  if($r['typ'] && $GLOBALS['KAPACITA_U'.$r['typ']][ROK] <= $r['pocet'])
    switch($r['typ'])
    {
      case 1: $NABIDKA_POKOJE=false; break;
      case 2: $NABIDKA_TRIDY=false; break;
      case 3: $NABIDKA_POKOJE_2=false; break;
    }
    

$errorReporting=error_reporting(E_ALL ^ E_NOTICE);

?>

<h1>Přihláška na GameCon <?php echo ROK?></h1>
<p>Na této stránce se můžete přihlásit na GameCon <?php echo ROK?>. V rámci přihlášky si vyberete ubytování a můžete si objednat GameCon tričko a placku. Pokud zaškrtnete, že jste student, ovlivní to výši vaší slevy a při prezenci na GameConu nám předložíte nějaký studentský průkaz nebo doklad o studiu.</p>
<p>Přihlášku budete moci měnit až téměř do začátku GameConu. Na zastavení této možnosti vás včas upozorníme. V případě, že budete chtít získat slevu za včasné placení, tak je nutné zaplatit ubytování, tričko, placku, kostku a přihlášené aktivity nejpozději do konce června. Za platby došlé 1. července a později už bonus nezískáte.</p>
<p>Pokud si chcete objednat více kusů trička, placky nebo kostek, tak to prosím uveďte do poznámky, stejně jako informaci, že chcete být ubýtováni s konkrétní osobou (ideálně uveďte jméno, příjmení a ID uživatele).</p>
<p>Před samotným přihlášením vám doporučujeme pročíst si články k <a href="/prihlasovani-na-gameconu">přihlašování</a>, <a href="http://gamecon.cz/ceny-slevy-placeni-gc-2012">placení</a> a článek <a href="/gamecon-v-nekolika-snadnych-krocich">GameCon v několika krocích</a>. Tyto články důkladně popisují fungování při&shy;hla&shy;šo&shy;va&shy;cí&shy;ho systému - může se jevit složitější, ale jakmile si články pročtete, zjistíte, že přihlašovat se na aktivity na GameConu je hračka a můžete si jednoduše vytvářet a měnit vlastní program.</p>

<?
if (empty($_SESSION["id_uzivatele"])){
?>
<p><strong>Pro přihlášení se na GameCon musíte být zaregistrováni a přihlášeni na tomto webu!</strong></p>
<?
}
?>
<?
$mail_orgum=false;

if($_POST["prihlas_gc"] == 1)
{
  $prazdno=false;
  $sql="
  select
    jmeno_uzivatele,
    prijmeni_uzivatele,
    ulice_a_cp_uzivatele,
    mesto_uzivatele,
    stat_uzivatele,
    psc_uzivatele,
    datum_narozeni_uzivatele,
    email1_uzivatele
  from
    uzivatele_hodnoty
  where
    id_uzivatele=$_SESSION[id_uzivatele];
  ";
  $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    if (mysql_num_rows($result) > 0){
      $jmeno=mysql_result($result,0,0);
      if ($jmeno == ""){
        $prazdno=true;
      }
      $prijmeni=mysql_result($result,0,1);
      if ($prijmeni == ""){
        $prazdno=true;
      }
      $ulice=mysql_result($result,0,2);
      if ($ulice == ""){
        $prazdno=true;
      }
      $mesto=mysql_result($result,0,3);
      if ($mesto == ""){
        $prazdno=true;
      }
      $stat=mysql_result($result,0,4);
      if ($stat == ""){
        $prazdno=true;
      }
      $psc=mysql_result($result,0,5);
      if ($psc == ""){
        $prazdno=true;
      }
      $datum_narozeni=mysql_result($result,0,6);
      if ($datum_narozeni == ""){
        $prazdno=true;
      }
      $email=mysql_result($result,0,7);
      if ($email == ""){
        $prazdno=true;
      }
    }
  if ($prazdno == true){
    echo "Nemáte vyplněny všechny údaje, které jsou potřeba pro přihlášení se na GameCon ".var_getvalue_sn('rok').". Učiňte tak, prosím, v sekci \"<a href=\"/me_nastaveni\">Mé nastavení</a>\"";
  }
  else {
   if ($_POST["druhy_krok"] == 1){
        //smazat jeho ubytovani
        $sql="
          delete from
            prihlaska_ubytovani
          where
            id_uzivatele=$_SESSION[id_uzivatele]
            and rok=".var_getvalue_sn('rok')."
        ";
        $result=dbQuery($db_jmeno,$sql,$db_spojeni);
        //smazat jeho "ostatni"
        $sql="
          delete from
            prihlaska_ostatni
          where
            id_uzivatele=$_SESSION[id_uzivatele]
            and rok=".var_getvalue_sn('rok')."
        ";
        $result=dbQuery($db_jmeno,$sql,$db_spojeni);
        $mail_orgum=false;
   }
   //if(statment || !statement) se cesky rika if(1), vime?
   if (
    (!ma_pravo($_SESSION["id_uzivatele"],$ID_PRAVO_PRIHLASEN)) || 
    (ma_pravo($_SESSION["id_uzivatele"],$ID_PRAVO_PRIHLASEN))
   ){
    if ($_POST["druhy_krok"] != 1){
      $mail_orgum=false;
      $sql="
        insert into prihlaska_uzivatele 
          (
          id_uzivatele,
          jmeno,
          prijmeni,
          ulice,
          mesto,
          stat,
          psc,
          datum_narozeni,
          email,
          rok
          )
        values
          (
          $_SESSION[id_uzivatele],
          '$jmeno',
          '$prijmeni',
          '$ulice',
          '$mesto',
          '$stat',
          '$psc',
          $datum_narozeni,
          '$email',
          ".var_getvalue_sn('rok')."
          )
      ";
      $result=dbQuery($db_jmeno,$sql,$db_spojeni);
      $sql="
        insert into r_uzivatele_zidle
          (
          id_uzivatele,
          id_zidle
          )
        values
          (
          $_SESSION[id_uzivatele],
          $ID_ZIDLE_PRIHLASEN
          )
      ";
      $result=dbQuery($db_jmeno,$sql,$db_spojeni);//posazení uživatele na židli "přihlášen na GC"
    }
    if ($_POST["placka"] == "on"){
      $placka=1;
    }
    else {
      $placka=0;
    }
    if ($_POST["kostka"] == "on"){
      $kostka=1;
    }
    else {
      $kostka=0;
    }
    if ($_POST["student"] == "on"){
      $student=1;
    }
    else {
      $student=0;
    }
    if ($_POST["druhy_krok"]==1){ //odeslani mailu se vzkazem orgum - pokud se zmenil nebo je vyplnen
      /*$sql="
        select
          count(id_uzivatele)
        from
          prihlaska_ostatni
        where
          id_uzivatele=$_SESSION[id_uzivatele]
        LIMIT 1000
      ";
      $result=dbQuery($db_jmeno,$sql,$db_spojeni);
      echo "<br /><br />error".mysql_error()."<br />";   
      while($zaznam=mysql_fetch_row($result)){
        $pocet=$zaznam[0];
      }
      echo "POCET: $pocet<br />";
      if ($pocet > 0){
        $sql="
          select
            vzkaz
          from
            prihlaska_ostatni
          where
            id_uzivatele=$_SESSION[id_uzivatele]
          limit 1000;
        ";
        $result=dbQuery($db_jmeno,$sql,$db_spojeni);
        $vzkaz_stary=mysql_result($result,0,0);
      }*/
        
      //DO NOT WANT
      /*
      if(ma_pravo($_SESSION["id_uzivatele"],999)){
        //else {echo "<br />$sql<br />$db_jmeno<br />$db_spojeni";}
        echo "<br />novy: $_POST[vzkaz]<br />stary: $vzkaz_stary<br />";
        foreach ($_POST as $key => $value) {
          echo "Key: $key; Value: $value<br />\n";
        }
      }
      */
      
      if ("$_POST[vzkaz]" != "$vzkaz_stary"){
        $mail_orgum=true;
      }
    }
    else {
      if (!empty($_POST["vzkaz"])){
        $mail_orgum=true;
      }
    }
    //echo "<br /><br />XXX ".$_POST["tricko"]." XXX<br /><br />";
    if (empty($_POST["tricko"])){
      $trickoinsert="0";
    }
    else {
      $trickoinsert=$_POST["tricko"];
    }
    
    $sql="
      insert into prihlaska_ostatni
        (
        id_uzivatele,
        placka,
        kostka,
        tricko,
        student,
        ubytovani,
        na_pokoji,
        vzkaz,
        rok
        )
      values
        (
        $_SESSION[id_uzivatele],
        $placka,
        $kostka,
        '$trickoinsert',
        $student,
        $_POST[ubytovani],
        '$_POST[na_pokoji]',
        '$_POST[vzkaz]',
        ".var_getvalue_sn('rok')."
        )
    ";
    $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    //echo $sql;
    
    $ubytovani_sql="insert into prihlaska_ubytovani
          (
          id_uzivatele,
          den,
          rok
          )
        values ";
    if ($_POST["ct"] == "on"){
      $ubytovani_sql.="
          (
          $_SESSION[id_uzivatele],
          1,
          ".var_getvalue_sn('rok')."
          )
      "; 
      $ubytuj=true;   
    }
    if ($_POST["pa"] == "on"){
      if ($ubytuj == true){
        $ubytovani_sql.=",";
      }
      $ubytovani_sql.="
          (
          $_SESSION[id_uzivatele],
          2,
          ".var_getvalue_sn('rok')."
          )
      ";    
      $ubytuj=true;
    }
    if ($_POST["so"] == "on"){
      if ($ubytuj == true){
        $ubytovani_sql.=",";
      }
      $ubytovani_sql.="
          (
          $_SESSION[id_uzivatele],
          3,
          ".var_getvalue_sn('rok')."
          )
      ";    
      $ubytuj=true;
    }
    if ($_POST["ne"] == "on"){
      if ($ubytuj == true){
        $ubytovani_sql.=",";
      }
      $ubytovani_sql.="
          (
          $_SESSION[id_uzivatele],
          4,
          ".var_getvalue_sn('rok')."
          )
      ";    
      $ubytuj=true;
    }
    if ($ubytuj == true){
      $result=dbQuery($db_jmeno,$ubytovani_sql,$db_spojeni);
    }
    $u->otoc();
    if($_POST["druhy_krok"]==1) //aktualizace údajů
    {
      if(post('zpetNaFinance'))
      {
        oznameni(hlaska('aktualizacePrihlasky'),false); //nepřesměrovávát na referer, ale na…
        back('/finance'); // …přehled financí, který nás sem poslal a nastavil post zpetNaFinance
      }
      else
        oznameni(hlaska('aktualizacePrihlasky'));
    }
    else //nová přihláška
      oznameni(hlaska('prihlaseniNaGc',$u));
   }
  } //<<--- Takto se delaji indenty. Diky, diky moc za to…

  //poslání případného vzkazu organizátorům
  if ($mail_orgum == true){
  $sql="
    select
      login_uzivatele,
      email1_uzivatele
    from
      uzivatele_hodnoty
    where
      id_uzivatele=$_SESSION[id_uzivatele];
  ";
  $result=dbQuery($db_jmeno,$sql,$db_spojeni);
  $login=mysql_result($result,0,0);
  $email=mysql_result($result,0,1);
  
  $mail=new GcMail("Uživatel $login ($email) při své registraci na GC zadal následující vzkaz organizátorům: <br />".$_POST["vzkaz"]);
  $mail->adresat('info@gamecon.cz');
  $mail->predmet("Vzkaz z registrace na GC - uživatel $login");
  $mail->odeslat();
  
  }
  
  if(!$chyba_zobraz) oznameni('Změny uloženy');
}

if (isset($chyba_zobraz)){
  echo "<div class=\"chyba_ramecek\">$chyba_zobraz</div>";
  unset($chyba_zobraz);
}
?>

<?
if (!empty($_SESSION["id_uzivatele"])){
if(!ma_pravo($_SESSION["id_uzivatele"],$ID_PRAVO_PRIHLASEN)){
  $prazdno=false;
  $sql="
  select
    jmeno_uzivatele,
    prijmeni_uzivatele,
    ulice_a_cp_uzivatele,
    mesto_uzivatele,
    stat_uzivatele,
    psc_uzivatele,
    datum_narozeni_uzivatele,
    email1_uzivatele
  from
    uzivatele_hodnoty
  where
    id_uzivatele=$_SESSION[id_uzivatele];
  ";
  $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    if (mysql_num_rows($result) > 0){
      $jmeno=mysql_result($result,0,0);
      if ($jmeno == ""){
        $prazdno=true;
      }
      $prijmeni=mysql_result($result,0,1);
      if ($prijmeni == ""){
        $prazdno=true;
      }
      $ulice=mysql_result($result,0,2);
      if ($ulice == ""){
        $prazdno=true;
      }
      $mesto=mysql_result($result,0,3);
      if ($mesto == ""){
        $prazdno=true;
      }
      $stat=mysql_result($result,0,4);
      if ($stat == ""){
        $prazdno=true;
      }
      $psc=mysql_result($result,0,5);
      if ($psc == ""){
        $prazdno=true;
      }
      $datum_narozeni=mysql_result($result,0,6);
      if ($datum_narozeni == ""){
        $prazdno=true;
      }
      $email=mysql_result($result,0,7);
      if ($email == ""){
        $prazdno=true;
      }
    }
  if ($prazdno == true){
    $chyba_zobraz="Nemáte vyplněny všechny údaje, které jsou potřeba pro přihlášení se na GameCon ".var_getvalue_sn('rok').". Učiňte tak, prosím, v sekci \"<a href=\"/me_nastaveni\">Mé nastavení</a>\"";
    if (isset($chyba_zobraz)){
      echo "<div class=\"chyba_ramecek\">$chyba_zobraz</div>";
    unset($chyba_zobraz);
    }
  }
  else {
    if(!ma_pravo($_SESSION["id_uzivatele"],$ID_PRAVO_PRIHLASEN)){
      if (!empty($_SESSION["id_uzivatele"])){
    ?>
      <form method="post">
        <input type="hidden" name="prihlas_gc" value="1" />
        <div class="registrace_radek">
          <h2>Ubytování</h2>
        </div>
        <div class="registrace_radek">
          <div class="registrace_popis">Typ ubytování</div>
          <div class="registrace_input" style="padding-top: 2px;">
            <select class="registrace_select2" name="ubytovani">
              <?php if($NABIDKA_POKOJE){ ?>  
                <option value="1">Trojlůžkové pokoje (<?php echo CENA_POKOJ ?>,-Kč/noc)</option>  
              <?php } ?>
              <?php if($NABIDKA_POKOJE_2){ ?>  
                <option value="3">Dvoulůžkové pokoje (<?php echo CENA_POKOJ_2 ?>,-Kč/noc)</option>  
              <?php } ?>
              <?php if($NABIDKA_TRIDY){ ?>
              <option value="2" selected="selected">Spacák (<?php echo $GLOBALS['CENA_SPACAK'] ?>,-Kč/noc)</option>
              <?php } ?>
              <option value="0">žádné / vlastní</option>              
            </select>
          </div>
        </div>
        <div class="registrace_radek">
          <div class="registrace_popis">19.7. - čtvrteční noc</div><div class="registrace_input" style="padding-top: 5px;"><input type="checkbox" name="ct" style="width: 20px;" /> </div>
        </div>
        <div class="registrace_radek">
          <div class="registrace_popis">20.7. - páteční noc</div><div class="registrace_input" style="padding-top: 5px;"><input type="checkbox" name="pa" style="width: 20px;" /></div>
        </div>
        <div class="registrace_radek">
          <div class="registrace_popis">21.7. - sobotní noc</div><div class="registrace_input" style="padding-top: 5px;"><input type="checkbox" name="so" style="width: 20px;" /> </div>
        </div>
        <div class="registrace_radek">
          <div class="registrace_popis">22.7. - nedělní noc</div><div class="registrace_input" style="padding-top: 5px;"><input type="checkbox" name="ne" style="width: 20px;" /> </div>
        </div>
        <div class="registrace_radek">
        <strong>Poznámka:</strong> pokoje na internátu jsou dvoulůžkové a třílůžkové, v případě dosažení určité dohodnuté kapacity bude možné ubytování v tělocvičně ve vlastním spacáku za 70,-Kč/noc. Pokud si přejete být, v případě že to bude možné, přednostně ubytování v tělocvičně, zvolte možnost "Pokoje/třídy". O převedení na ubytování ve třídě ve spacáku budete informováni. V případě, že bude naplněna ubytovací kapacita na pokojích, budeme se navýšit kapacitu lůžek v jiných zařízeních.
        </div>
        <div class="registrace_radek">
          <h2>Ostatní</h2>
        </div>
        <div class="registrace_radek">
          <?php if(!OBJEDNAVKA_UZAVRENA_TRIKO){ ?>
          <div class="registrace_popis">chci <a href="/files/obsah/materialy/<?echo var_getvalue_sn('rok')?>/tricko.jpg" rel="lightbox[galerie]" title="Tričko GameCon <?echo var_getvalue_sn('rok')?> - obrázky jsou ilustrativní">tričko</a> 
            <?php
              if($u->maPravo(P_TRIKO_ZDARMA))
                echo '(or&shy;ga&shy;ni&shy;zá&shy;tor&shy;ské, zdarma)';
              else if($u->maPravo(P_TRIKO_ZAPUL))
                echo '(vypraveč&shy;ské za polovic – '.(CENA_TRIKO/2).'&thinsp;Kč)';
              else 
                echo '('.CENA_TRIKO.'&thinsp;Kč)';
            ?>
          </div>
          <?php } else { ?>
          <div class="registrace_popis">objednávání triček po&shy;zas&shy;ta&shy;ve&shy;no</div>
          <?php } ?>            
          <div class="registrace_input">
            <select name="tricko" class="registrace_select2">
              <option value="0" checked="checked">žádné</option>
              <?php if(!OBJEDNAVKA_UZAVRENA_TRIKO){ ?>
              <option value="dS">Dámské velikost S</option>
              <option value="dM">Dámské velikost M</option>
              <option value="dL">Dámské velikost L</option>
              <option value="S">Pánské velikost S</option>
              <option value="M">Pánské velikost M</option>
              <option value="L">Pánské velikost L</option>
              <option value="XL">Pánské velikost XL</option>
              <?php } ?>
            </select>
          </div>
        </div>
        <div class="registrace_radek">
          <div class="registrace_popis">chci <a href="/files/obsah/materialy/<?echo var_getvalue_sn('rok')?>/placka.jpg" rel="lightbox[galerie]" title="Placka GameCon <?echo var_getvalue_sn('rok')?> - obrázky jsou ilustrativní">placku</a> (<?php echo($u->maPravo(P_PLACKA_ZDARMA)?'organizátor zdarma':CENA_PLACKA.'&thinsp;Kč') ?>)</div><div class="registrace_input" style="padding-top: 5px;">
            <?php if(OBJEDNAVKA_PLACKA) { ?>
            <input type="checkbox" name="placka" style="width: 20px;"/>
            <?php } else { ?>
            Objednávání uzavřeno.
            <?php } ?>
          </div>
        </div>
        <div class="registrace_radek">
          <div class="registrace_popis">chci <a href="/files/obsah/materialy/<?echo var_getvalue_sn('rok')?>/kostka.jpg" rel="lightbox[galerie]" title="Kostka GameCon <?echo var_getvalue_sn('rok')?> - obrázky jsou ilustrativní">kostku</a> (<?php echo $u->maPravo(P_KOSTKA_ZDARMA)?'organizátor zdarma':CENA_KOSTKA.'&thinsp;Kč'; ?>, do vzkazu napište, kterou verzi chcete)</div><div class="registrace_input" style="padding-top: 5px;">
            <?php if(OBJEDNAVKA_KOSTKA) { ?>
            <input type="checkbox" name="kostka" style="width: 20px;"/>
            <?php } else { ?>
            Objednávání uzavřeno.
            <?php } ?>
          </div>
        </div>
        <div class="registrace_radek">
          <div class="registrace_popis">jsem studentem</div><div class="registrace_input" style="padding-top: 5px;"><input type="checkbox" name="student" style="width: 20px;" /></div>
        </div>
        <div class="registrace_radek">
          <br />na pokoji chci být s:<br /> 
          <div class="registrace_textarea"><textarea name="na_pokoji"></textarea></div>
        </div>
        <div class="registrace_radek">
          vzkaz pro organizátory:<br />
          <div class="registrace_textarea"><textarea name="vzkaz"></textarea></div>
        </div>
        <div class="registrace_radek">
          Na závěr bychom Tě rádi požádali o odpověď na několik krátkých otázek. Po jejich vyplnění ale nezapomeň odeslat i tuto stránku s přihláškou. <a href="https://docs.google.com/spreadsheet/viewform?formkey=dFdQMjVLWkU0Z1hQSGlhcjY1LU55OWc6MQ#gid=0" onclick="return!window.open(this.href)">Dotazník</a><br /><br />
        </div>
        <div class="registrace_radek"><strong>Odesláním tohoto formuláře se přihlašuji na GameCon <?echo var_getvalue_sn('rok')?>.</strong></div>
        <div class="buttonky" style="padding-left: 200px;"><input type="image" src="/files/styly/styl-aktualni/tlacitka/odeslat.gif" value="Submit" alt="odeslat" style="width: 88px; height: 26px; margin-top: 10px;"></div>
      </form>
      <?//echo "<strong>GameCon již začal, registrace přes internet jsou stopnuty, přihlásit se bez omezení můžeš přímo na GameConu.</strong>";?>
      <?
        }
        else {
          echo "<p>Pro přihlášení na GameCon musíte být zaregistrováni a přihlášeni na stránkách.</p>";
        }
      }
    }
  }
  else {
    if ($_POST["akce"] == "upravit_prihlasku"){
      $prazdno=false;
      $sql="
      select
        jmeno_uzivatele,
        prijmeni_uzivatele,
        ulice_a_cp_uzivatele,
        mesto_uzivatele,
        stat_uzivatele,
        psc_uzivatele,
        datum_narozeni_uzivatele,
        email1_uzivatele
      from
        uzivatele_hodnoty
      where
        id_uzivatele=$_SESSION[id_uzivatele];
      ";
      $result=dbQuery($db_jmeno,$sql,$db_spojeni);
        if (mysql_num_rows($result) > 0){
          $jmeno=mysql_result($result,0,0);
          if ($jmeno == ""){
            $prazdno=true;
          }
          $prijmeni=mysql_result($result,0,1);
          if ($prijmeni == ""){
            $prazdno=true;
          }
          $ulice=mysql_result($result,0,2);
          if ($ulice == ""){
            $prazdno=true;
          }
          $mesto=mysql_result($result,0,3);
          if ($mesto == ""){
            $prazdno=true;
          }
          $stat=mysql_result($result,0,4);
          if ($stat == ""){
            $prazdno=true;
          }
          $psc=mysql_result($result,0,5);
          if ($psc == ""){
            $prazdno=true;
          }
          $datum_narozeni=mysql_result($result,0,6);
          if ($datum_narozeni == ""){
            $prazdno=true;
          }
          $email=mysql_result($result,0,7);
          if ($email == ""){
            $prazdno=true;
          }
        }
      if ($prazdno == true){
        echo "Nemáte vyplněny všechny údaje, které jsou potřeba změnu vaší přihlášky. Učiňte tak, prosím, v sekci \"<a href=\"/me_nastaveni\">Mé nastavení</a>\"";
      }
      else {
        $sql="
        select
          placka,
          ubytovani,
          tricko,
          student,
          na_pokoji,
          vzkaz,
          kostka
        from
          prihlaska_ostatni
        where
          id_uzivatele=$_SESSION[id_uzivatele]
          and rok=".var_getvalue_sn('rok').";
        ";
        $result=dbQuery($db_jmeno,$sql,$db_spojeni);
        $placka=mysql_result($result,0,0);
        $ubytovani=mysql_result($result,0,1);
        $tricko=mysql_result($result,0,2);
        $student=mysql_result($result,0,3);
        $na_pokoji=mysql_result($result,0,4);
        $vzkaz=mysql_result($result,0,5);
        $kostka=mysql_result($result,0,6);
        
        if ($ubytovani > 0){
          $sql="
            select
              den
            from
              prihlaska_ubytovani
            where
              id_uzivatele=$_SESSION[id_uzivatele]
              and rok=".var_getvalue_sn('rok')."
            order by
              den
            asc;
          ";
          $result=dbQuery($db_jmeno,$sql,$db_spojeni);
          if (mysql_num_rows($result) > 0){
            while($zaznam=mysql_fetch_row($result)){
              switch ($zaznam[0]){
                case 1: $ctvrtek=1;
                break;
                case 2: $patek=1;
                break;
                case 3: $sobota=1;
                break;
                case 4: $nedele=1;
                break;
              }
            }
          }
        }
      
        ?>
        <form method="post">
          <input type="hidden" name="druhy_krok" value="1" />
          <input type="hidden" name="prihlas_gc" value="1" />
          <?php /* Následující řádek: Jde o hack pro provázání s přehledem
            financí - pokud je voláno odtamtud, nastaví se tato post hodnota a 
            vrací se po editu zpět na přehled financí) */ ?>
          <input type="hidden" name="zpetNaFinance" value="<?php echo (int)post('zpetNaFinance') ?>" />
        <div class="registrace_radek">
          <h2>Ubytování</h2>
        </div>
        <div class="registrace_radek">
          <div class="registrace_popis">Typ ubytování</div>
          <div class="registrace_input" style="padding-top: 2px;">
            <select class="registrace_select2" name="ubytovani">
              <option value="0">Vyberte ze seznamu</option>
              <?php if($ubytovani==1 || $NABIDKA_POKOJE){ ?>
                <option value="1"<?if ($ubytovani == 1){echo " selected=\"selected\"";}?>>Trojlůžkové pokoje (<?php echo CENA_POKOJ ?>,-Kč/noc)</option>
              <?php } ?>
              <?php if($ubytovani==3 || $NABIDKA_POKOJE_2){ ?>
                <option value="3"<?if ($ubytovani == 3){echo " selected=\"selected\"";}?>>Dvoulůžkové pokoje (<?php echo CENA_POKOJ_2 ?>,-Kč/noc)</option>
              <?php } ?>
              <?php if ($ubytovani==2 || $NABIDKA_TRIDY){ ?>
              <option value="2"<?if ($ubytovani == 2){echo " selected=\"selected\"";}?>>Spacák (<?php echo $GLOBALS['CENA_SPACAK'] ?>,-Kč/noc)</option>
              <?php } ?>
              <option value="0"<?if ($ubytovani == 0){echo " selected=\"selected\"";}?>>žádné / vlastní</option>
            </select>
          </div>
        </div>
        <div class="registrace_radek">
          <div class="registrace_popis">19.7. - čtvrteční noc</div><div class="registrace_input" style="padding-top: 5px;"><input type="checkbox" name="ct" style="width: 20px;"<?if ($ctvrtek == 1){echo " checked=\"checked\"";}?> /> </div>
        </div>
        <div class="registrace_radek">
          <div class="registrace_popis">20.7. - páteční noc</div><div class="registrace_input" style="padding-top: 5px;"><input type="checkbox" name="pa" style="width: 20px;"<?if ($patek == 1){echo " checked=\"checked\"";}?> /></div>
        </div>
        <div class="registrace_radek">
          <div class="registrace_popis">21.7. - sobotní noc</div><div class="registrace_input" style="padding-top: 5px;"><input type="checkbox" name="so" style="width: 20px;"<?if ($sobota == 1){echo " checked=\"checked\"";}?> /> </div>
        </div>
        <div class="registrace_radek">
          <div class="registrace_popis">22.7. - nedělní noc</div><div class="registrace_input" style="padding-top: 5px;"><input type="checkbox" name="ne" style="width: 20px;"<?if ($nedele == 1){echo " checked=\"checked\"";}?> /> </div>
        </div>
        <div class="registrace_radek">
        <strong>Poznámka:</strong> pokoje na internátu jsou dvoulůžkové a třílůžkové, v případě dosažení určité dohodnuté kapacity bude možné ubytování v tělocvičně ve vlastním spacáku za 70,-Kč/noc. Pokud si přejete být, v případě že to bude možné, přednostně ubytování v tělocvičně, zvolte možnost "Pokoje/třídy". O převedení na ubytování ve třídě ve spacáku budete informováni. V případě, že bude naplněna ubytovací kapacita na pokojích, budeme se navýšit kapacitu lůžek v jiných zařízeních.
        </div>
        <div class="registrace_radek">
          <h2>Ostatní</h2>
        </div>
        <div class="registrace_radek">
          <div class="registrace_popis">chci  <a href="/files/obsah/materialy/<?echo var_getvalue_sn('rok')?>/tricko.jpg" rel="lightbox[galerie]" title="Tričko GameCon <?echo var_getvalue_sn('rok')?> - obrázky jsou ilustrativní">tričko</a> (<? echo $GLOBALS['CENA_TRIKO'] ?> Kč)</div>  
          <div class="registrace_input">
            <?php if(!OBJEDNAVKA_UZAVRENA_TRIKO) { ?>
              <select name="tricko" class="registrace_select2">
                <option value="0"<?if ($tricko == 0){echo " selected=\"selected\"";}?>>žádné</option>
                <option value="dS"<?if ($tricko == "dS"){echo " selected=\"selected\"";}?>>Dámské velikost S</option>
                <option value="dM"<?if ($tricko == "dM"){echo " selected=\"selected\"";}?>>Dámské velikost M</option>
                <option value="dL"<?if ($tricko == "dL"){echo " selected=\"selected\"";}?>>Dámské velikost L</option>
                <option value="S"<?if ($tricko == "S"){echo " selected=\"selected\"";}?>>Pánské velikost S</option>
                <option value="M"<?if ($tricko == "M"){echo " selected=\"selected\"";}?>>Pánské velikost M</option>
                <option value="L"<?if ($tricko == "L"){echo " selected=\"selected\"";}?>>Pánské velikost L</option>
                <option value="XL"<?if ($tricko == "XL"){echo " selected=\"selected\"";}?>>Pánské velikost XL</option>
              </select>
            <?php } else {
              if ($tricko != "0")
              {
                echo "Máš objednáno tričko velikosti <strong>$tricko</strong>".
                '<input name="tricko" type="hidden" value="'.$tricko.'">';
              }
              else 
              {
                echo "Nemáš objednané tričko.";
              }   
            }  
            ?>
          </div>
        </div>
        <div class="registrace_radek">
          <div class="registrace_popis">chci  <a href="/files/obsah/materialy/<?echo var_getvalue_sn('rok')?>/placka.jpg" rel="lightbox[galerie]" title="Placka GameCon <?echo var_getvalue_sn('rok')?> - obrázky jsou ilustrativní">placku</a> (<?php echo $GLOBALS['CENA_PLACKA'] ?> Kč)</div><div class="registrace_input" style="padding-top: 5px;">
            <?php if(OBJEDNAVKA_PLACKA) { ?>
            <input type="checkbox" name="placka" style="width: 20px;"<?if ($placka == 1){echo " checked=\"checked\"";}?> />
            <?php } else { ?>
            <input type="hidden" name="placka" value="<?php echo $placka?'on':'' ?>" />
            Objednávání uzavřeno. <?php echo $placka?'Objednáno.':'' ?> 
            <?php } ?>
          </div>
        </div>
        <div class="registrace_radek">
          <div class="registrace_popis">chci  <a href="/files/obsah/materialy/<?echo var_getvalue_sn('rok')?>/kostka.jpg" rel="lightbox[galerie]" title="Kostka GameCon <?echo var_getvalue_sn('rok')?> - obrázky jsou ilustrativní">kostku</a> (<?php echo $GLOBALS['CENA_KOSTKA'] ?> Kč)</div><div class="registrace_input" style="padding-top: 5px;">
            <?php if(OBJEDNAVKA_KOSTKA) { ?>
            <input type="checkbox" name="kostka" style="width: 20px;"<?if ($kostka == 1){echo " checked=\"checked\"";}?> />
            <?php } else { ?>
            <input type="hidden" name="kostka" value="<?php echo $kostka?'on':'' ?>" />
            Objednávání uzavřeno. <?php echo $kostka?'Objednáno.':'' ?>
            <?php } ?>
          </div>
        </div>
        <div class="registrace_radek">
          <div class="registrace_popis">jsem studentem</div><div class="registrace_input" style="padding-top: 5px;"><input type="checkbox" name="student" style="width: 20px;"<?if ($student == 1){echo " checked=\"checked\"";}?> /></div>
        </div>
        <div class="registrace_radek">
          <br />na pokoji chci být s:<br /> 
          <div class="registrace_textarea"><textarea name="na_pokoji"><?echo $na_pokoji?></textarea></div>
        </div>
        <div class="registrace_radek">
          vzkaz pro organizátory:<br />
          <div class="registrace_textarea"><textarea name="vzkaz"><?echo $vzkaz?></textarea></div>
        </div>
        <div class="buttonky" style="padding-left: 200px;"><input type="image" src="/files/styly/styl-aktualni/tlacitka/odeslat.gif" value="Submit" alt="odeslat" style="width: 88px; height: 26px; margin-top: 10px;"></div>
      </form>
      <?
      }
    }
    elseif ($_POST["akce"] == "smazat_prihlasku"){
      if ($_POST["potvrzeni_smazani"] == 1){
        //sesadit uživatele ze židle
        $sql="
          delete from
            r_uzivatele_zidle
          where
            id_uzivatele=$_SESSION[id_uzivatele] and
            id_zidle=$ID_ZIDLE_PRIHLASEN
        ";
        $result=dbQuery($db_jmeno,$sql,$db_spojeni);
        //smazat jeho udaje v "uzivatele"
        $sql="
          delete from
            prihlaska_uzivatele
          where
            id_uzivatele=$_SESSION[id_uzivatele]
            and rok=".var_getvalue_sn('rok')."
        ";
        $result=dbQuery($db_jmeno,$sql,$db_spojeni);
        //smazat jeho ubytovani
        $sql="
          delete from
            prihlaska_ubytovani
          where
            id_uzivatele=$_SESSION[id_uzivatele] and
            rok=".var_getvalue_sn('rok')."
        ";
        $result=dbQuery($db_jmeno,$sql,$db_spojeni);
        //smazat jeho "ostatni"
        $sql="
          delete from
            prihlaska_ostatni
          where
            id_uzivatele=$_SESSION[id_uzivatele]
            and rok=".var_getvalue_sn('rok')."
        ";
        $result=dbQuery($db_jmeno,$sql,$db_spojeni);
        //smazat jeho prihlasene akce
        $sql="
          delete from
            ap
          USING akce_prihlaseni ap JOIN akce_seznam a ON(a.id_akce=ap.id_akce)
          WHERE
            ap.id_uzivatele=$_SESSION[id_uzivatele]
            and a.rok=".var_getvalue_sn('rok')."
        ";
        $result=dbQuery($db_jmeno,$sql,$db_spojeni);
        $u->otoc();
        oznameni(hlaska('odhlaseniZGc',$u));
      }
      else {
        ?>
        <p><strong>Opravdu chcete smazat svou přihlášku na GameCon <?echo var_getvalue_sn('rok')?>?</p>
        <form id="potvrzeni_smazani" method="post">
          <input type="hidden" name="akce" value="smazat_prihlasku" />
          <input type="hidden" name="potvrzeni_smazani" value="1" />
        </form>
        
        <form id="nepotvrzeni_smazani" method="post">
        </form>
          
          <p>
            <a href="javascript: document.getElementById('potvrzeni_smazani').submit()">Ano, chci se odhlásit</a> - <a href="javascript: document.getElementById('nepotvrzeni_smazani').submit()">Ne, neodhlašovat</a>  
          </p>
        <?
      }
    }
    else {
        ?>
        <p><strong>Jsi přihlášen na GameCon <?echo var_getvalue_sn('rok')?></strong></p>
        <div class="registrace_radek">
          <h2>Tvé údaje</h2>
        </div>
        <?        
        $sql="
          select
            jmeno,
            prijmeni,
            ulice,
            mesto,
            stat,
            psc,
            datum_narozeni,
            email
          from
            prihlaska_uzivatele
          where
            id_uzivatele=$_SESSION[id_uzivatele]
          and
            rok=$ROK_AKTUALNI
          ";
        $result=dbQuery($db_jmeno,$sql,$db_spojeni);
        if (mysql_num_rows($result) > 0){
          $jmeno=mysql_result($result,0,0);
          $prijmeni=mysql_result($result,0,1);
          $ulice=mysql_result($result,0,2);
          $mesto=mysql_result($result,0,3);
          $stat=mysql_result($result,0,4);
          $psc=mysql_result($result,0,5);
          $datum_narozeni=mysql_result($result,0,6);
          $email=mysql_result($result,0,7);
        }
        if ($stat == 1){
          $stat="Česká republika";
        }
        else {
          $stat="Slovenská republika";
        }
        $datum_narozeni=date("j.n.Y",$datum_narozeni);
        $sql="
        select
          placka,
          ubytovani,
          tricko,
          student,
          na_pokoji,
          vzkaz,
          kostka
        from
          prihlaska_ostatni
        where
          id_uzivatele=$_SESSION[id_uzivatele]
          and rok=".var_getvalue_sn('rok').";
      ";
      $result=dbQuery($db_jmeno,$sql,$db_spojeni);
      $placka=mysql_result($result,0,0);
      $ubytovani=mysql_result($result,0,1);
      $tricko=mysql_result($result,0,2);
      $student=mysql_result($result,0,3);
      $na_pokoji=mysql_result($result,0,4);
      $vzkaz=mysql_result($result,0,5);
      $kostka=mysql_result($result,0,6);
      
      if ($placka == "1"){
        $placka="objednána";
      }
      else {
        $placka="neobjednána";
      }
      
      if ($kostka == "1"){
        $kostka="objednána";
      }
      else {
        $kostka="neobjednána";
      }
      
      if ($student == "1"){
        $student="ano";
      }
      else {
        $student="ne";
      }
      
      if (empty($na_pokoji)){
        $na_pokoji="nevyplněno";
      }
      
      if (empty($vzkaz)){
        $vzkaz="nevyplněn";
      }
      
      if ($ubytovani > 0){
        $sql="
          select
            den
          from
            prihlaska_ubytovani
          where
            id_uzivatele=$_SESSION[id_uzivatele]
            and rok=".var_getvalue_sn('rok')."
          order by
            den
          asc;
        ";
        $result=dbQuery($db_jmeno,$sql,$db_spojeni);
        if (mysql_num_rows($result) > 0){
          $pocet_dni=mysql_num_rows($result);
          while($zaznam=mysql_fetch_row($result)){
            switch ($zaznam[0]){
              case 1: $dni.="čtvrtek, ";
              break;
              case 2: $dni.="pátek, ";
              break;
              case 3: $dni.="sobota, ";
              break;
              case 4: $dni.="neděle, ";
              break;
            }
          }
          $dni=substr($dni, 0, -2);
        }
        if ($pocet_dni > 0){
          if ($ubytovani == 1){
            $ubytovani="objednán internát";
          }
          if ($ubytovani == 2){
            $ubytovani="objednána internát/třída";
          }
        }
        else {
          $ubytovani="neobjednáno";
        }
      }
      else {
        $ubytovani="neobjednáno";
      }
      if (!empty($dni)){
        $ubytovani="$ubytovani ($dni)";
      }
       
      if ($tricko != "0"){
        switch ($tricko){
          case "dS": $typ="dámské, velikost S ";
          break;
          case "dM": $typ="dámské, velikost M ";
          break;
          case "dL": $typ="dámské, velikost L ";
          break;
          case "S": $typ="pánské, velikost S ";
          break;
          case "M": $typ="pánské, velikost M ";
          break;
          case "L": $typ="pánské, velikost L ";
          break;
          case "XL": $typ="pánské, velikost XL ";
          break;
        }
        $tricko="objednáno $typ";
      }
      else {
        $tricko="neobjednáno";
      }
      
        ?>
        <div class="registrace_radek">
          <div class="registrace_popis">jméno a příjmení:</div><div class="registrace_input" style="font-weight: bold; padding-top: 5px;"><?echo $jmeno." ".$prijmeni?></div>
        </div>
        <div class="registrace_radek">
          <div class="registrace_popis">ulice:</div><div class="registrace_input" style="font-weight: bold; padding-top: 5px;"><?echo $ulice?></div>
        </div>
        <div class="registrace_radek">
          <div class="registrace_popis">město a psč:</div><div class="registrace_input" style="font-weight: bold; padding-top: 5px;"><?echo $mesto." ".$psc?></div>
        </div>
        <div class="registrace_radek">
          <div class="registrace_popis">stát:</div><div class="registrace_input" style="font-weight: bold; padding-top: 5px;"><?echo $stat?></div>
        </div>
        <div class="registrace_radek">
          <div class="registrace_popis">datum narození:</div><div class="registrace_input" style="font-weight: bold; padding-top: 5px;"><?echo $datum_narozeni?></div>
        </div>
        <div class="registrace_radek">
          <div class="registrace_popis">email:</div><div class="registrace_input" style="font-weight: bold; padding-top: 5px;"><?echo $email?></div>
        </div>
        <div class="registrace_radek">
          <div class="registrace_popis">ubytování:</div><div class="registrace_input" style="font-weight: bold; padding-top: 5px;"><?echo $ubytovani;?></div>
        </div>
        <div class="registrace_radek">
          <div class="registrace_popis">tričko:</div><div class="registrace_input" style="font-weight: bold; padding-top: 5px;"><?echo $tricko;?></div>
        </div>
        <div class="registrace_radek">
          <div class="registrace_popis">placka:</div><div class="registrace_input" style="font-weight: bold; padding-top: 5px;"><?echo $placka;?></div>
        </div>
        <div class="registrace_radek">
          <div class="registrace_popis">kostka:</div><div class="registrace_input" style="font-weight: bold; padding-top: 5px;"><?echo $kostka;?></div>
        </div>
        <div class="registrace_radek">
          <div class="registrace_popis">student:</div><div class="registrace_input" style="font-weight: bold; padding-top: 5px;"><?echo $student;?></div>
        </div>
        <div class="registrace_radek">
          na pokoji s:<strong> <?echo $na_pokoji;?></strong>
        </div>
        <div class="registrace_radek">
          vzkaz pro organizátory:<strong> <?echo $vzkaz;?></strong>
        </div>
        
        <form id="upravit_prihlasku" method="post">
          <input type="hidden" name="akce" value="upravit_prihlasku">
        </form>
        <form id="smazat_prihlasku" method="post">
          <input type="hidden" name="akce" value="smazat_prihlasku">
        </form>
        
        <p style="float: left; padding-top: 30px; width: 450px;">
          <?//<strong>GameCon již začal, přihláška nejde změnit.</strong><br />?>
          <a href="javascript: document.getElementById('upravit_prihlasku').submit()">změnit přihlášku</a><br />
          <?
          if ((!ma_pravo($_SESSION["id_uzivatele"],$ID_PRAVO_DRD)) && (!ma_pravo($_SESSION["id_uzivatele"],$ID_PRAVO_TROJBOJ))){?>
            <a href="javascript: document.getElementById('smazat_prihlasku').submit()">smazat přihlášku</a> <strong>pozor!</strong> - budou zrušeny všechny tvé registrace na aktivity<br /><br />
          <?
          }
          else {
            echo "<strong>Pro odhlášení se z GameConu se nejprve odhlaš z Mistrovství v DrD a GameCon Trojboje.</strong><br />";
          }
          ?>
        </p> 
        
        <?
        }
      }
}




error_reporting($errorReporting);

?>
