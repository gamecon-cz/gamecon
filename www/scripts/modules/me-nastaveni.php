<?

if(!$u){ echo hlaska('jenPrihlaseni'); return; } //jen přihlášení

if (isset($_POST['menim_udaje'])){

  $chyba_zobraz='';
  if (!empty($_POST["narozeni_mesic"]) && !empty($_POST["narozeni_mesic"]) && !empty($_POST["narozeni_mesic"])){ 
    if (!checkdate($_POST["narozeni_mesic"],$_POST["narozeni_den"],$_POST["narozeni_rok"])){
      $chyba_zobraz.="Chyba: Zadané datum narození není platné.<br />";
    }
  }
  else {
    $datum_narozeni="";
  }
  if ( empty($_POST["email1"])){
    $chyba_zobraz.="Chyba: Údaje nebyly změněny. Všechna pole označená hvězdičkou jsou povinná.<br />";
    //echo $_POST["login"]." ".$_POST["jmeno"]." ".$_POST["email1"]." ".$_POST["prijmeni"]." ".$_POST["ulice_a_cp"]." ".$_POST["mesto"]." ".$_POST["psc"]." ".$_POST["telefon"]." ".$_POST["datum_narozeni"]; 
  }
  else {
    if ($_POST["heslo1"] != $_POST["heslo2"]){
      $chyba_zobraz.="Chyba: Údaje nebyly změněny. Zadaná hesla nejsou shodná.<br />";
    }    
        
    $sql="select id_uzivatele from uzivatele_hodnoty where email1_uzivatele like '$_POST[email1]' and id_uzivatele <> $_SESSION[id_uzivatele]";
    $result=dbQuery($db_jmeno , $sql, $db_spojeni);
    if (mysql_num_rows($result) > 0){
      $chyba_zobraz.="Chyba: Údaje nebyly změněny. Uživatel se zadaným e-mailem je již registrován.<br />";
    }
  }
  if($chyba_zobraz) chyba($chyba_zobraz);
  
  
  if (!$chyba_zobraz){
    if ($_POST["souhlas_maily"]==1){
      $souhlas_maily=1;
    }
    else {
      $souhlas_maily=2;
    }
    if (!empty($_POST["narozeni_mesic"]) && !empty($_POST["narozeni_mesic"]) && !empty($_POST["narozeni_mesic"])){ 
      $datum_narozeni=mktime(0,0,0,$_POST["narozeni_mesic"],$_POST["narozeni_den"],$_POST["narozeni_rok"]);
    }
    if(!empty($_POST["heslo1"])){
      $heslo_zasifrovane=md5($_POST["heslo1"]);
      $sql= "UPDATE uzivatele_hodnoty
             SET jmeno_uzivatele='$_POST[jmeno]',
                 prijmeni_uzivatele='$_POST[prijmeni]',
                 ulice_a_cp_uzivatele='$_POST[ulice_a_cp]',
                 mesto_uzivatele='$_POST[mesto]',
                 stat_uzivatele='$_POST[stat]',
                 psc_uzivatele='$_POST[psc]',
                 telefon_uzivatele='$_POST[telefon1]',
                 datum_narozeni_uzivatele='$datum_narozeni',
                 heslo_md5='$heslo_zasifrovane',
                 souhlas_maily='$souhlas_maily',
                 email1_uzivatele='$_POST[email1]',
                 email2_uzivatele='$_POST[email2]',
                 jine_uzivatele='$_POST[dalsi_informace]',
                 forum_razeni='$_POST[forum_razeni]'
             WHERE id_uzivatele=$_SESSION[id_uzivatele]";
    }
    else {
      $sql= "UPDATE uzivatele_hodnoty
             SET jmeno_uzivatele='$_POST[jmeno]',
                 prijmeni_uzivatele='$_POST[prijmeni]',
                 ulice_a_cp_uzivatele='$_POST[ulice_a_cp]',
                 mesto_uzivatele='$_POST[mesto]',
                 stat_uzivatele='$_POST[stat]',
                 psc_uzivatele='$_POST[psc]',
                 telefon_uzivatele='$_POST[telefon1]',
                 datum_narozeni_uzivatele='$datum_narozeni',
                 souhlas_maily='$souhlas_maily',
                 email1_uzivatele='$_POST[email1]',
                 email2_uzivatele='$_POST[email2]',
                 jine_uzivatele='$_POST[dalsi_informace]',
                 forum_razeni='$_POST[forum_razeni]'
             WHERE id_uzivatele=$_SESSION[id_uzivatele]";
    }
    //echo $sql;
    dbQuery($db_jmeno, $sql, $db_spojeni);
    $_SESSION["razeni_fora"]=$_POST["forum_razeni"];
    $u->otoc();
    oznameni('Údaje byly změněny.');
  }
}


/////////////////AVATARY////////////////
if( !empty($_POST["avatar_vymazat"]) )
{
  chdir($ROOT_DIR);
  if($u->avatarSmaz())
    oznameni(hlaska('avatarSmazan'));
  else
    chyba(hlaska('avatarChybaMazani'));
  chdir($MODULE_DIR);
}
//nahrani
if( !empty($_POST["ukladam_avatar"]) )
{
  chdir($ROOT_DIR);
  $nazev = $_FILES["avatar"]["name"];
  $delka = strlen($nazev);
  $potecce = 0;
  $zacatek = "";
  $konec = "";
  for ( $i=0; $i<$delka ; $i++ )
    {
    if ( ($potecce == 0) && ($nazev[$i] != ".") ):
      $zacatek .= $nazev[$i];
    elseif ( $potecce == 1 ):
      $konec .= $nazev[$i];
    else:
      $potecce = 1;
    endif;
    }
  $konec = strtolower($konec);
  if($konec=="jpg"){
    
    /*
    // zjisteni maximalniho cisla z pocitadla
    $sql = "select hodnota_pocitadla from pocitadla where id_pocitadla = 2";
    $result = mysql_db_query($db_jmeno, $sql, $db_spojeni); 
    $pocitadlo = mysql_result($result,0,0);
    //inkrementace pocitadla
    $pocitadlo1 = $pocitadlo + 1;
    $sql = "update pocitadla set hodnota_pocitadla = $pocitadlo1 where id_pocitadla = 2";
    $result = mysql_db_query($db_jmeno, $sql, $db_spojeni);
    
    //nahrani informace do DB
    $sql = "update uzivatele_hodnoty set avatar = $pocitadlo1 where id_uzivatele = $_SESSION[id_uzivatele]";
    mysql_db_query($db_jmeno, $sql, $db_spojeni);
    */
    
    //nahrani obrazku do tmp souboru
    $filename  = $_FILES['avatar']['tmp_name'];
    
    $max_width = 60;
    $max_height = 60;
    $yposun = $xposun = 0;
    
    // Get new sizes
    list($width, $height) = getimagesize($filename);
    if ($width > $height){
      $newwidth = $max_width;
      $newheight = $height/($width/$max_width);
      $yposun = round(($max_height-$newheight)/2);
    }
    else {
      $newwidth = $width/($height/$max_height);
      $newheight = $max_height;
      $xposun = round(($max_width-$newwidth)/2);
    }
    
    // Load
    $thumb = imagecreatetruecolor($max_width, $max_height);
    $gdColor = imagecolorallocate($thumb, 0, 0, 0); // cerna barva pozadi
    imagefilledrectangle($thumb, 0, 0, $max_width-1, $max_height-1, $gdColor);
    
    if (($konec == "jpg") || ($konec == "jpeg")){
      $source = imagecreatefromjpeg($filename);
    }
    elseif ($konec == "gif"){
      $source = imagecreatefromgif($filename);
    }
    elseif ($konec == "png"){
      $source = imagecreatefrompng($filename);
    }
    // Resize
    imagecopyresampled($thumb, $source, $xposun, $yposun, 0, 0, $newwidth, $newheight, $width, $height);
    //header('Content-type: image/jpeg');
    if (ImageJpeg($thumb,"./files/systemove/avatary/".$u->id().".jpg",100)){
      ImageDestroy($source); //Odstranění původního obrázku z paměti
      ImageDestroy($thumb);  //Odstranění obrázku náhledu z paměti
      oznameni(hlaska('avatarNahran'));
    }
  }  
  else 
  {
    chyba(hlaska('avatarSpatnyFormat'));
  }
  chdir($MODULE_DIR);
}


//if ($zaregistrovano != 1){
  $sql="select jmeno_uzivatele,prijmeni_uzivatele,ulice_a_cp_uzivatele,mesto_uzivatele,stat_uzivatele,psc_uzivatele,telefon_uzivatele,datum_narozeni_uzivatele,email1_uzivatele,email2_uzivatele,jine_uzivatele,souhlas_maily,forum_razeni from uzivatele_hodnoty where id_uzivatele=$_SESSION[id_uzivatele]";
  $result=dbQuery($db_jmeno , $sql, $db_spojeni);
  while($zaznam=mysql_fetch_array($result)){
    $narozeni_den=date("d",$zaznam["datum_narozeni_uzivatele"]);
    $narozeni_mesic=date("m",$zaznam["datum_narozeni_uzivatele"]);
    $narozeni_rok=date("Y",$zaznam["datum_narozeni_uzivatele"]);
  ?>
  <h1>Mé nastavení</h1>
  <?
  if (isset($chyba_zobraz)){
    echo "<div class=\"chyba_ramecek\">$chyba_zobraz</div>";
    unset($chyba_zobraz);
  }
  ?>
  <p>Položky označené &sup1; musí být vyplněny, položky označené &sup2; musí být vyplněny pro fyzickou registraci na GameCon <?php echo ROK_AKTUALNI ?> (dostupná ze sekce <a href="prihlaska">přiháška</a>).<br />Pokud neměníte heslo, pak údaje "Heslo" a "Heslo pro kontrolu" mohou zůstat prázdné.</p>
  
  <div class="registrace_radek">
    <h2>Nastavení obrázku</h2>
  </div>
  <form method="post" enctype="multipart/form-data">
  <input type="hidden" name="ukladam_avatar" value="1" />
  <div class="registrace_radek">
      <div class="registrace_popis">Tvůj obrázek:</div>
      <div class="registrace_input"><input type= "file" name= "avatar" /></div>
  </div>
  <div class="registrace_radek">
      <a href="informace k obrázku" onclick="$('#objekt1').slideToggle(); return false;">Informace k nahrání obrázku</a><br />
  </div>
  <div class="registrace_radek">
    <a href="javascript: document.getElementById('avatar_vymazat').submit()">Obnovit standardní nastavení obrázku</a><br />
  </div>
  <div class="registrace_radek_p" id="objekt1" style="display: none;">
      Avatar (tvůj uživatelský avatar) je obrázek (jpg, gif nebo png) velikosti 60x60px.<br />
      Nahrané obrázky budou zmenšeny na tuto velikost a po stranách doplněny černou barvou.<br /><br />
      Jestli si přeješ mít standardní obrázek, použij odkaz "Obnovit standardní nastavení obrázku".
  </div>
  <div class="buttonky" style="padding-left: 200px;"><input type="image" src="files/styly/styl-aktualni/tlacitka/zmenit_udaje.gif" value="Submit" alt="změnit údaje" style="width: 88px; height: 26px; margin-top: 10px;"></div>
  </form>
  
  <div class="registrace_radek">
    <h2>Nastavení fóra</h2>
  </div>
  <form method="post" autocomplete="off">
      <input type="hidden" name="menim_udaje"  value="1" />
  <div class="registrace_radek">
    <div class="registrace_popis">řazení fóra:</div>
    <div class="registrace_input">
      <select class="registrace_select2" name="forum_razeni">
        <option value="s" <? if ($zaznam["forum_razeni"] == "s"){echo "selected=\"selected\"";}?>>sestupně</option>
        <option value="v" <? if ($zaznam["forum_razeni"] == "v"){echo "selected=\"selected\"";}?>>vzestupně</option>
      </select>
    </div>
  </div>
  <div class="registrace_radek">
    <h2>Uživatelské údaje</h2>
  </div>
      <div class="registrace_radek">
        <div class="registrace_popis">jméno&sup2;:</div>
        <div class="registrace_input"><input type="text" name="jmeno" value="<?echo $zaznam["jmeno_uzivatele"];?>" /></div>
      </div>
      <div class="registrace_radek">
        <div class="registrace_popis">příjmení&sup2;:</div>
        <div class="registrace_input"><input type="text" name="prijmeni" value="<?echo $zaznam["prijmeni_uzivatele"];?>" /></div>
      </div>
      <div class="registrace_radek">
        <div class="registrace_popis">datum narození&sup2;:</div>
        <div class="registrace_input">
          <select class="registrace_select2" name="narozeni_den">
          <option value="" <?if ($narozeni_den == "")?>></option>
          <option value="1" <?if ($narozeni_den == 1){echo 'selected="selected"';}?>>1</option>
          <option value="2" <?if ($narozeni_den == 2){echo 'selected="selected"';}?>>2</option>
          <option value="3" <?if ($narozeni_den == 3){echo 'selected="selected"';}?>>3</option>
          <option value="4" <?if ($narozeni_den == 4){echo 'selected="selected"';}?>>4</option>
          <option value="5" <?if ($narozeni_den == 5){echo 'selected="selected"';}?>>5</option>
          <option value="6" <?if ($narozeni_den == 6){echo 'selected="selected"';}?>>6</option>
          <option value="7" <?if ($narozeni_den == 7){echo 'selected="selected"';}?>>7</option>
          <option value="8" <?if ($narozeni_den == 8){echo 'selected="selected"';}?>>8</option>
          <option value="9" <?if ($narozeni_den == 9){echo 'selected="selected"';}?>>9</option>
          <option value="10" <?if ($narozeni_den == 10){echo 'selected="selected"';}?>>10</option>
          <option value="11" <?if ($narozeni_den == 11){echo 'selected="selected"';}?>>11</option>
          <option value="12" <?if ($narozeni_den == 12){echo 'selected="selected"';}?>>12</option>
          <option value="13" <?if ($narozeni_den == 13){echo 'selected="selected"';}?>>13</option>
          <option value="14" <?if ($narozeni_den == 14){echo 'selected="selected"';}?>>14</option>
          <option value="15" <?if ($narozeni_den == 15){echo 'selected="selected"';}?>>15</option>
          <option value="16" <?if ($narozeni_den == 16){echo 'selected="selected"';}?>>16</option>
          <option value="17" <?if ($narozeni_den == 17){echo 'selected="selected"';}?>>17</option>
          <option value="18" <?if ($narozeni_den == 18){echo 'selected="selected"';}?>>18</option>
          <option value="19" <?if ($narozeni_den == 19){echo 'selected="selected"';}?>>19</option>
          <option value="20" <?if ($narozeni_den == 20){echo 'selected="selected"';}?>>20</option>
          <option value="21" <?if ($narozeni_den == 21){echo 'selected="selected"';}?>>21</option>
          <option value="22" <?if ($narozeni_den == 22){echo 'selected="selected"';}?>>22</option>
          <option value="23" <?if ($narozeni_den == 23){echo 'selected="selected"';}?>>23</option>
          <option value="24" <?if ($narozeni_den == 24){echo 'selected="selected"';}?>>24</option>
          <option value="25" <?if ($narozeni_den == 25){echo 'selected="selected"';}?>>25</option>
          <option value="26" <?if ($narozeni_den == 26){echo 'selected="selected"';}?>>26</option>
          <option value="27" <?if ($narozeni_den == 27){echo 'selected="selected"';}?>>27</option>
          <option value="28" <?if ($narozeni_den == 28){echo 'selected="selected"';}?>>28</option>
          <option value="29" <?if ($narozeni_den == 29){echo 'selected="selected"';}?>>29</option>
          <option value="30" <?if ($narozeni_den == 30){echo 'selected="selected"';}?>>30</option>
          <option value="31" <?if ($narozeni_den == 31){echo 'selected="selected"';}?>>31</option>
        </select>
        <select class="registrace_select2" name="narozeni_mesic">
          <option value="" <?if ($narozeni_mesic == "")?>></option>
          <option value="1" <?if ($narozeni_mesic == 1){echo 'selected="selected"';}?>>leden</option>
          <option value="2" <?if ($narozeni_mesic == 2){echo 'selected="selected"';}?>>únor</option>
          <option value="3" <?if ($narozeni_mesic == 3){echo 'selected="selected"';}?>>březen</option>
          <option value="4" <?if ($narozeni_mesic == 4){echo 'selected="selected"';}?>>duben</option>
          <option value="5" <?if ($narozeni_mesic == 5){echo 'selected="selected"';}?>>květen</option>
          <option value="6" <?if ($narozeni_mesic == 6){echo 'selected="selected"';}?>>červen</option>
          <option value="7" <?if ($narozeni_mesic == 7){echo 'selected="selected"';}?>>červenec</option>
          <option value="8" <?if ($narozeni_mesic == 8){echo 'selected="selected"';}?>>srpen</option>
          <option value="9" <?if ($narozeni_mesic == 9){echo 'selected="selected"';}?>>září</option>
          <option value="10" <?if ($narozeni_mesic == 10){echo 'selected="selected"';}?>>říjen</option>
          <option value="11" <?if ($narozeni_mesic == 11){echo 'selected="selected"';}?>>listopad</option>
          <option value="12" <?if ($narozeni_mesic == 12){echo 'selected="selected"';}?>>prosinec</option>
        </select>
        <select class="registrace_select2" name="narozeni_rok">
          <option value="" <?if ($narozeni_rok == "")?>></option>
          <?
          for ($i=2003; $i>1933; $i--){
            echo '<option value="'.$i.'"'; 
            if ($narozeni_rok == $i){
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
        <div class="registrace_input"><input type="text" name="ulice_a_cp" value="<?echo $zaznam["ulice_a_cp_uzivatele"];?>" /></div>
      </div>
      <div class="registrace_radek">
        <div class="registrace_popis">město&sup2;:</div>
        <div class="registrace_input"><input type="text" name="mesto" value="<?echo $zaznam["mesto_uzivatele"];?>" /></div>
      </div>
      <div class="registrace_radek">
        <div class="registrace_popis">stát&sup2;:</div>
        <div class="registrace_input">
          <select class="registrace_select2" name="stat">
            <option value="1" <?if ($zaznam["stat_uzivatele"] == 1){echo 'selected="selected"';}?>>Česká republika</option>
            <option value="2" <?if ($zaznam["stat_uzivatele"] == 2){echo 'selected="selected"';}?>>Slovenská republika</option>
          </select>
        </div>
      </div>
      <div class="registrace_radek">
        <div class="registrace_popis">psč&sup2;:</div>
        <div class="registrace_input"><input type="text" name="psc" value="<?echo $zaznam["psc_uzivatele"];?>" /></div>
      </div>
      <div class="registrace_radek">
        <div class="registrace_popis">telefon:</div>
        <div class="registrace_input"><input type="text" name="telefon1" value="<?echo $zaznam["telefon_uzivatele"];?>" /></div>
      </div>
      <div class="registrace_radek">
        <div class="registrace_popis">email&sup1;&sup2;:</div>
        <div class="registrace_input"><input type="text" name="email1" value="<?echo $zaznam["email1_uzivatele"];?>" /></div>
      </div>
      <div class="registrace_radek">
        <div class="registrace_popis">email 2:</div>
        <div class="registrace_input"><input type="text" name="email2" value="<?echo $zaznam["email2_uzivatele"];?>" /></div>
      </div>
      <div class="registrace_radek">
        <div class="registrace_popis">heslo&sup1;:</div>
        <div class="registrace_input"><input type="password" name="heslo1" /></div>
      </div>
      <div class="registrace_radek">
        <div class="registrace_popis">heslo pro kontrolu&sup1;:</div>
        <div class="registrace_input"><input type="password" name="heslo2" /></div>
      </div>
      <div class="registrace_radek">
        další informace (ICQ, Skype, jabber):<br />
        <div class="registrace_textarea"><textarea name="dalsi_informace"><?echo $zaznam["jine_uzivatele"];?></textarea></div>
      </div>
      <div class="registrace_radek">
        <a href="podmínky registrace" onclick="$('#objekt0').slideToggle(); return false;">Podmínky registrace</a><br />
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
          <option value="1" <? if ($zaznam["souhlas_maily"] == 1){echo "selected=\"selected\"";}?>>souhlasím</option>
          <option value="2" <? if ($zaznam["souhlas_maily"] == 2){echo "selected=\"selected\"";}?>>nesouhlasím</option>
        </select> se zasíláním informací týkajících se akcí pořádaných 
        občanským sdružením GameCon na svůj email uvedený výše.<br /><br />
        Zavazuji se nepřispívat na stránkách <a href="http://www.gamecon.cz/">www.gamecon.cz</a>
        vulgárními, hanlivými, nezákonnými nebo jakkoli nevhodnými příspěvky.
        Beru rovněž na vědomí, že GameCon má právo odstranit, uzamknout
        nebo jakkoli upravit kterýkoli příspěvek.<br /><br />
        Je-li Vám méně než 15 let, musíte probrat svou registraci
        na stránkách <a href="http://www.gamecon.cz/">www.gamecon.cz</a> se svými rodiči.
        Tím, že registraci provedete, prohlašujete, že tuto registraci provádíte s
        jejich souhlasem a nebo pod jejich dohledem.
      </div>
      <div class="buttonky" style="padding-left: 200px;"><input type="image" src="files/styly/styl-aktualni/tlacitka/zmenit_udaje.gif" value="Submit" alt="změnit údaje" style="width: 88px; height: 26px; margin-top: 10px;"></div>
    </form>
    
  <?
  }
?>
<form method="post" id="avatar_vymazat">
  <input type="hidden" name="avatar_vymazat" value="1">
</form>

<div class="registrace_radek">
  <h2>Zrušení registrace</h2>
</div>
<div class="registrace_radek">
  Chcete-li zrušit svou registraci na stránkách <a href="http://www.gamecon.cz">GameCon.cz</a>, napište nám email na <a href="mailto:info@gamecon.cz">info@gamecon.cz</a>.
</div>
