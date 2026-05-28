<?

if($u){ echo 'Už jsi zaregistrován'.$u->koncA().'.'; return; } //přihlášený se nemůže registrovat

function nahodne_hex_cislo($pocet_znaku){
  $vysledek = "";
  for ($i = 0; $i < $pocet_znaku; $i++){
    $vysledek .= dechex(rand(0,15));
  }
  return $vysledek;
}

function mime_header_encode($text, $encoding = "utf-8") {
    return "=?$encoding?Q?" . imap_8bit($text) . "?=";
}

$zaregistrovano=0;
$_POST["narozeni_den"]=isset($_POST["narozeni_den"])?$_POST["narozeni_den"]:null;
$_POST["narozeni_mesic"]=isset($_POST["narozeni_mesic"])?$_POST["narozeni_mesic"]:null;
$_POST["narozeni_rok"]=isset($_POST["narozeni_rok"])?$_POST["narozeni_rok"]:null;
$_POST["stat"]=isset($_POST["stat"])?$_POST["stat"]:null;
$_POST["pohlavi"]=isset($_POST["pohlavi"])?$_POST["pohlavi"]:null;
$chyba_zobraz='';

if (isset($_POST['registruji'])){
  if ( empty($_POST["login"]) || empty($_POST["heslo1"]) || empty($_POST["heslo2"]) || empty($_POST["email"]) ){
    $chyba_zobraz.="Chyba: Všechna pole označená &sup1; jsou povinná.<br />";
  }
  if ($_POST["souhlas"] != "on"){
    $chyba_zobraz.="Chyba: Pro registraci je nutné souhlasit s podmínkami.<br />";
  }
  if (!empty($_POST["narozeni_mesic"]) && !empty($_POST["narozeni_mesic"]) && !empty($_POST["narozeni_mesic"])){ 
    if (!checkdate($_POST["narozeni_mesic"],$_POST["narozeni_den"],$_POST["narozeni_rok"])){
      $chyba_zobraz.="Chyba: Zadané datum narození není platné.<br />";
    }
  }
  if ($_POST["heslo1"] != $_POST["heslo2"]){
    $chyba_zobraz.="Chyba: Zadaná hesla nejsou shodná, prosím, vyplň znovu.<br />";
  }
  if ($_POST["pohlavi"]!='m' && $_POST["pohlavi"]!='f'){
    $chyba_zobraz.="Chyba: Nevyplněno pohlaví.<br />";
  }
    
    $sql="select id_uzivatele from uzivatele_hodnoty where login_uzivatele like '$_POST[login]'";
    $result=dbQuery($db_jmeno , $sql, $db_spojeni);
    if (mysql_num_rows($result) > 0){
      $chyba_zobraz.="Chyba: Uživatel se zadaným uživatelským jménem je již registrován, vyber si jiný login.<br />";
    }
    
    $sql="select id_uzivatele from uzivatele_hodnoty where email1_uzivatele like '$_POST[email]'";
    $result=dbQuery($db_jmeno , $sql, $db_spojeni);
    if (mysql_num_rows($result) > 0){
      $chyba_zobraz.="Chyba: Uživatel se zadaným e-mailem je již registrován.<br />";
    }
    
    if ((strlen($_POST["login"]) < 3) && (strlen($_POST["login"]) >= 0)){
      $chyba_zobraz.="Chyba: Uživatelské jméno musí mít alespoň 3 znaky.<br />";
    }
    
  if (!$chyba_zobraz){
    $nahodne_cislo=nahodne_hex_cislo(20);
    $heslo_zasifrovane=md5($_POST["heslo1"]);
    if (!empty($_POST["narozeni_mesic"]) && !empty($_POST["narozeni_mesic"]) && !empty($_POST["narozeni_mesic"])){ 
      $datum_narozeni=mktime(0,0,0,$_POST["narozeni_mesic"],$_POST["narozeni_den"],$_POST["narozeni_rok"]);
    }
    
    /*
    $sql="select hodnota_pocitadla from pocitadla where id_pocitadla=1";
    $result=dbQuery($db_jmeno, $sql, $db_spojeni); 
    $pocitadlo=mysql_result($result,0,0);
    
    $pocitadlo1=$pocitadlo + 1;
    $sql="update pocitadla set hodnota_pocitadla=$pocitadlo1 where id_pocitadla=1";
    */
    
    //echo $sql;
    $result=dbQuery($db_jmeno, $sql, $db_spojeni);
    
    $sql="insert into uzivatele_hodnoty  ( `id_uzivatele` , `login_uzivatele` , `jmeno_uzivatele` , `prijmeni_uzivatele` , `ulice_a_cp_uzivatele` , `mesto_uzivatele`, `stat_uzivatele` , `psc_uzivatele` , `telefon_uzivatele` , `datum_narozeni_uzivatele` , `heslo_md5` , `funkce_uzivatele` , `email1_uzivatele` , `email2_uzivatele` ,`souhlas_maily`, `jine_uzivatele` , `random`, `forum_razeni`, `pohlavi` ) values (NULL,'$_POST[login]','$_POST[jmeno]','$_POST[prijmeni]','$_POST[ulice_a_cp]','$_POST[mesto]','$_POST[stat]','$_POST[psc]','$_POST[telefon]','$datum_narozeni','$heslo_zasifrovane','0','$_POST[email]','$_POST[email2]','$_POST[souhlas_maily]','$_POST[dalsi_informace]','$nahodne_cislo','s','$_POST[pohlavi]')";
    dbQuery($db_jmeno, $sql, $db_spojeni);
    
    //poslání mailu
    $to ="$_POST[email]";
    $subject="Registrace na Gamecon.cz";
    $message="Dobrý den,<br /><br />
    \nZaregistroval".($_POST["pohlavi"]=='f'?'a':'')." ses na serveru <a href=\"http://www.gamecon.cz\">Gamecon.cz</a> pod přihlašovacím jménem $_POST[login]. Pro potvrzení své e-mailové adresy prosím klikni na níže uvedený odkaz:<br /><br />
    \n<a href=\"http://gamecon.cz/potvrzeni-registrace/$nahodne_cislo\">http://gamecon.cz/potvrzeni-registrace/$nahodne_cislo</a>";
    $message_2=iconv("utf-8","iso-8859-2","$message");
    $headers="Reply-to: mailman@gamecon.cz\nContent-Type:text/html; charset=iso-8859-2\nFrom:=?iso-8859-2?B?". base64_encode("GameCon MailMan")."?=<info@gamecon.cz>\n";
    mail($to, mime_header_encode(iconv("utf-8","iso-8859-2","$subject"),"iso-8859-2"), $message_2, $headers);
    
    //poslání případného vzkazu organizátorům
    if (!empty($_POST["vzkaz"])){
    $to ="info@gamecon.cz";
    $subject="Vzkaz pto organizátory - uživatel $_POST[login]";
    $message="Uživatel $_POST[login] při své registraci zadal následující vzkaz organizátorům: <br />".nl2br($_POST["vzkaz"]);
    $message_2=iconv("utf-8","iso-8859-2","$message");
    $headers="Reply-to: mailman@gamecon.cz\nContent-Type:text/html; charset=iso-8859-2\nFrom:=?iso-8859-2?B?". base64_encode("GameCon MailMan")."?=<info@gamecon.cz>\n";
    mail($to, mime_header_encode(iconv("utf-8","iso-8859-2","$subject"),"iso-8859-2"), $message_2, $headers);
    }
    
    $zaregistrovano=1;
  }
}

if($zaregistrovano!=1){
?>
<h1>Registrace nového uživatele</h1>

<?
if($chyba_zobraz)
{
  echo "<div class=\"chyba_ramecek\">$chyba_zobraz</div>";
  $chyba_zobraz='';
}
?>

<p>Položky označené &sup1; jsou při registraci povinné, položky označené &sup2; jsou povinné pro fyzickou registraci účastníka na GameCon 2011 (lze je vyplnit později v sekci "Mé nastavení")</p>
  <form method="post">
    <input type="hidden" name="registruji"  value="1" />
    <div class="registrace_radek">
      <div class="registrace_popis">login&sup1;&sup2;:</div>
      <div class="registrace_input"><input type="text" name="login" value="<?if (isset($_POST["login"])){echo $_POST["login"];}?>" /></div>
    </div>
    <div class="registrace_radek">
      <div class="registrace_popis">jméno&sup2;:</div>
      <div class="registrace_input"><input type="text" name="jmeno" value="<?if (isset($_POST["jmeno"])){echo $_POST["jmeno"];}?>" /></div>
    </div>
    <div class="registrace_radek">
      <div class="registrace_popis">příjmení&sup2;:</div>
      <div class="registrace_input"><input type="text" name="prijmeni" value="<?if (isset($_POST["prijmeni"])){echo $_POST["prijmeni"];}?>" /></div>
    </div>
    <div class="registrace_radek">
      <div class="registrace_popis">pohlaví&sup2;:</div>
      <div class="registrace_input">
        <select class="registrace_select2" name="pohlavi">
          <option value=""> </option>
          <option value="f" <?if ($_POST["pohlavi"] == 'f'){echo 'selected="selected"';}?>>žena</option>
          <option value="m" <?if ($_POST["pohlavi"] == 'm'){echo 'selected="selected"';}?>>muž</option>
        </select>
      </div>
    </div>
    <div class="registrace_radek">
      <div class="registrace_popis">datum narození&sup2;:</div>
      <div class="registrace_input">
        <select class="registrace_select2" name="narozeni_den">
          <option value="" <?if ($_POST["narozeni_den"] == 0){echo 'selected="selected"';}?>></option>
          <option value="1" <?if ($_POST["narozeni_den"] == 1){echo 'selected="selected"';}?>>1</option>
          <option value="2" <?if ($_POST["narozeni_den"] == 2){echo 'selected="selected"';}?>>2</option>
          <option value="3" <?if ($_POST["narozeni_den"] == 3){echo 'selected="selected"';}?>>3</option>
          <option value="4" <?if ($_POST["narozeni_den"] == 4){echo 'selected="selected"';}?>>4</option>
          <option value="5" <?if ($_POST["narozeni_den"] == 5){echo 'selected="selected"';}?>>5</option>
          <option value="6" <?if ($_POST["narozeni_den"] == 6){echo 'selected="selected"';}?>>6</option>
          <option value="7" <?if ($_POST["narozeni_den"] == 7){echo 'selected="selected"';}?>>7</option>
          <option value="8" <?if ($_POST["narozeni_den"] == 8){echo 'selected="selected"';}?>>8</option>
          <option value="9" <?if ($_POST["narozeni_den"] == 9){echo 'selected="selected"';}?>>9</option>
          <option value="10" <?if ($_POST["narozeni_den"] == 10){echo 'selected="selected"';}?>>10</option>
          <option value="11" <?if ($_POST["narozeni_den"] == 11){echo 'selected="selected"';}?>>11</option>
          <option value="12" <?if ($_POST["narozeni_den"] == 12){echo 'selected="selected"';}?>>12</option>
          <option value="13" <?if ($_POST["narozeni_den"] == 13){echo 'selected="selected"';}?>>13</option>
          <option value="14" <?if ($_POST["narozeni_den"] == 14){echo 'selected="selected"';}?>>14</option>
          <option value="15" <?if ($_POST["narozeni_den"] == 15){echo 'selected="selected"';}?>>15</option>
          <option value="16" <?if ($_POST["narozeni_den"] == 16){echo 'selected="selected"';}?>>16</option>
          <option value="17" <?if ($_POST["narozeni_den"] == 17){echo 'selected="selected"';}?>>17</option>
          <option value="18" <?if ($_POST["narozeni_den"] == 18){echo 'selected="selected"';}?>>18</option>
          <option value="19" <?if ($_POST["narozeni_den"] == 19){echo 'selected="selected"';}?>>19</option>
          <option value="20" <?if ($_POST["narozeni_den"] == 20){echo 'selected="selected"';}?>>20</option>
          <option value="21" <?if ($_POST["narozeni_den"] == 21){echo 'selected="selected"';}?>>21</option>
          <option value="22" <?if ($_POST["narozeni_den"] == 22){echo 'selected="selected"';}?>>22</option>
          <option value="23" <?if ($_POST["narozeni_den"] == 23){echo 'selected="selected"';}?>>23</option>
          <option value="24" <?if ($_POST["narozeni_den"] == 24){echo 'selected="selected"';}?>>24</option>
          <option value="25" <?if ($_POST["narozeni_den"] == 25){echo 'selected="selected"';}?>>25</option>
          <option value="26" <?if ($_POST["narozeni_den"] == 26){echo 'selected="selected"';}?>>26</option>
          <option value="27" <?if ($_POST["narozeni_den"] == 27){echo 'selected="selected"';}?>>27</option>
          <option value="28" <?if ($_POST["narozeni_den"] == 28){echo 'selected="selected"';}?>>28</option>
          <option value="29" <?if ($_POST["narozeni_den"] == 29){echo 'selected="selected"';}?>>29</option>
          <option value="30" <?if ($_POST["narozeni_den"] == 30){echo 'selected="selected"';}?>>30</option>
          <option value="31" <?if ($_POST["narozeni_den"] == 31){echo 'selected="selected"';}?>>31</option>
        </select>
        <select class="registrace_select2" name="narozeni_mesic">
          <option value="0" <?if ($_POST["narozeni_mesic"] == 0){echo 'selected="selected"';}?>></option>
          <option value="1" <?if ($_POST["narozeni_mesic"] == 1){echo 'selected="selected"';}?>>leden</option>
          <option value="2" <?if ($_POST["narozeni_mesic"] == 2){echo 'selected="selected"';}?>>únor</option>
          <option value="3" <?if ($_POST["narozeni_mesic"] == 3){echo 'selected="selected"';}?>>březen</option>
          <option value="4" <?if ($_POST["narozeni_mesic"] == 4){echo 'selected="selected"';}?>>duben</option>
          <option value="5" <?if ($_POST["narozeni_mesic"] == 5){echo 'selected="selected"';}?>>květen</option>
          <option value="6" <?if ($_POST["narozeni_mesic"] == 6){echo 'selected="selected"';}?>>červen</option>
          <option value="7" <?if ($_POST["narozeni_mesic"] == 7){echo 'selected="selected"';}?>>červenec</option>
          <option value="8" <?if ($_POST["narozeni_mesic"] == 8){echo 'selected="selected"';}?>>srpen</option>
          <option value="9" <?if ($_POST["narozeni_mesic"] == 9){echo 'selected="selected"';}?>>září</option>
          <option value="10" <?if ($_POST["narozeni_mesic"] == 10){echo 'selected="selected"';}?>>říjen</option>
          <option value="11" <?if ($_POST["narozeni_mesic"] == 11){echo 'selected="selected"';}?>>listopad</option>
          <option value="12" <?if ($_POST["narozeni_mesic"] == 12){echo 'selected="selected"';}?>>prosinec</option>
        </select>
        <select class="registrace_select2" name="narozeni_rok">
          <option value=""></option>
          <?
          for ($i=2003; $i>1933; $i--){
            echo '<option value="'.$i.'"'; 
            if ($_POST["narozeni_rok"] == $i){
              echo 'selected="selected"';
            }
            echo '>'.$i.'</option>';
          }
          ?>
        </select>
      </div>
    </div>
    <div class="registrace_radek">
      <div class="registrace_popis">ulice a číslo popisné&sup2;:</div>
      <div class="registrace_input"><input type="text" name="ulice_a_cp" value="<?if (isset($_POST["ulice_a_cp"])){echo $_POST["ulice_a_cp"];}?>" /></div>
    </div>
    <div class="registrace_radek">
      <div class="registrace_popis">město&sup2;:</div>
      <div class="registrace_input"><input type="text" name="mesto" value="<?if (isset($_POST["mesto"])){echo $_POST["mesto"];}?>" /></div>
    </div>
    <div class="registrace_radek">
      <div class="registrace_popis">stát&sup2;:</div>
      <div class="registrace_input">
        <select class="registrace_select2" name="stat">
          <option value="1" <?if ($_POST["stat"] == 1){echo 'selected="selected"';}?>>Česká republika</option>
          <option value="2" <?if ($_POST["stat"] == 2){echo 'selected="selected"';}?>>Slovenská republika</option>
        </select>
      </div>
    </div>
    <div class="registrace_radek">
      <div class="registrace_popis">psč&sup2;:</div>
      <div class="registrace_input"><input type="text" name="psc" value="<?if (isset($_POST["psc"])){echo $_POST["psc"];}?>" /></div>
    </div>
    <div class="registrace_radek">
      <div class="registrace_popis">telefon:</div>
      <div class="registrace_input"><input type="text" name="telefon" value="<?if (isset($_POST["telefon"])){echo $_POST["telefon"];}?>" /></div>
    </div>
    <div class="registrace_radek">
      <em>Telefonní číslo nebude nikomu sděleno a bude použito pouze v případě nutného kontaktu v rámci organizace GameConu.</em>
    </div>
    <div class="registrace_radek">
      <div class="registrace_popis">email&sup1;&sup2;:</div>
      <div class="registrace_input"><input type="text" name="email" value="<?if (isset($_POST["email"])){echo $_POST["email"];}?>" /></div>
    </div>
    <div class="registrace_radek">
      <div class="registrace_popis">email 2:</div>
      <div class="registrace_input"><input type="text" name="email2" value="<?if (isset($_POST["email2"])){echo $_POST["email2"];}?>" /></div>
    </div>
    <div class="registrace_radek">
      <div class="registrace_popis">heslo*:</div>
      <div class="registrace_input"><input type="password" name="heslo1" /></div>
    </div>
    <div class="registrace_radek">
      <div class="registrace_popis">heslo pro kontrolu&sup1;:</div>
      <div class="registrace_input"><input type="password" name="heslo2" /></div>
    </div>
    <div class="registrace_radek">
      další informace (ICQ, Skype, jabber):<br />
      <div class="registrace_textarea"><textarea name="dalsi_informace"><?if (isset($_POST["dalsi_informace"])){echo $_POST["dalsi_informace"];}?></textarea></div>
    </div>
    <div class="registrace_radek">
      <div class="registrace_popis">vzkaz pro organizátory:<br /></div>
      <div class="registrace_textarea"><textarea name="vzkaz""><?if (isset($_POST["vzkaz"])){echo $_POST["vzkaz"];}?></textarea></div>
    </div>
      <div class="registrace_radek_p" id="objekt0" style="display: none;">
        Zároveň tímto vyjadřuji výslovný souhlas s tím, aby občanské sdružení
        GameCon shromažďovalo a zpracovávalo osobní údaje, týkající se mé osoby,
        obsažené v této přihlášce, a to pro účely přípravy a organizace jím
        pořádaných akcí a všech jejich nastávajících ročníků, a to po dobu nezbytnou
        k zajištění práv a povinnosti plynoucích z příprav a zajišťování těchto
        akcí. Souhlasím rovněž s poskytnutím mých osobních údajů, v nezbytném
        rozsahu, třetím osobám poskytujícím ubytovací služby. Zavazuji se bez
        zbytečného odkladu nahlásit jakoukoli změnu zpracovávaných osobních údajů.
        Dále tímto výslovně prohlašuji, že jsem byl v souladu s ustanoveními § 11
        zákona č. 101/2000 Sb. v platném znění o ochraně osobních údajů řádně
        informován o zpracování osobních údajů v souvislosti s výše uvedenými akcemi
        a jejich dalšími ročníky.<br /><br />
 
        Zároveň tímto 
        <select class="registrace_select" name="souhlas_maily">
          <option value="1" seleted="selected">souhlasím</option>
          <option value="2">nesouhlasím</option>
        </select> se zasíláním informací týkajících se akcí pořádaných 
        občanským sdružením GameCon na svůj email uvedený výše.<br /><br />
        Zavazuji se nepřispívat na stránkách <a href="http://www.gamecon.cz/">www.gamecon.cz</a>
        vulgárními, hanlivými, nezákonnými nebo jakkoli nevhodnými příspěvky.
        Beru rovněž na vědomí, že GameCon má právo odstranit, uzamknout
        nebo jakkoli upravit kterýkoli příspěvek.<br /><br />
        Je-li Vám méně než 15 let, musíte probrat svou registraci
        na stránkách <a href="http://www.gamecon.cz/">www.gamecon.cz</a> se svými rodiči.
        Tím, že registraci provedete, prohlašujete, že tuto registraci provádíte s
        jejich souhlasem a nebo pod jejich dohledem.<br />
      </div>
      <div class="registrace_radek">
      <input type="checkbox" name="souhlas" checked="checked"> Souhlasím s <a href="podmínky registrace" onclick="$('#objekt0').slideToggle(); return false;">podmínkami registrace</a><br /><br />
    </div>
      <div class="buttonky" style="padding-left: 200px;"><input type="image" src="/files/styly/styl-aktualni/tlacitka/zaregistrovat.gif" value="Submit" alt="zaregistrovat" style="width: 88px; height: 26px;" /></div>
  </form>



<?
}
else {
  $chyba_zobraz="Uživatel zaregistrován. Pokračujte přihlášením v pravé horní části obrazovky.";
}
if($chyba_zobraz){
  echo "<div class=\"chyba_ramecek\">$chyba_zobraz</div>";
  unset($chyba_zobraz);
}

?>
