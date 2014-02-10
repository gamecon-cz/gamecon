<?php

if(!REGISTRACE_AKTIVNI){ echo hlaska('prihlaseniVypnuto'); return; } //jen pokud se smí přihlašovat na GC
if(!REG_DRD){ echo hlaska('drdVypnuto'); return; } //jen pokud se smí přihlašovat na DrD
if(!$u){ echo hlaska('jenPrihlaseni'); return; } //jen přihlášení
if(!$u->gcPrihlasen()){ echo hlaska('jenPrihlaseniGC'); return; } //jen přihlášení na GC

//ošetření starého api
$_POST['akce']=isset($_POST['akce'])?$_POST['akce']:null;
$chyba_zobraz=null;

?>

<script>
function ukaz(id)
{
  $('#objekt'+id).fadeToggle();
}
</script>

<h1>Přihláška na Mistrovství v DrD</h1>

<?php

require('./drd-konstanty.hhp');
require('./drd-funkce.hhp');

if ((!empty($_SESSION["id_uzivatele"])) && (ma_pravo($_SESSION["id_uzivatele"],$GLOBALS['ID_PRAVO_PRIHLASEN'])))
{ //přihlášen na web i con 

  if ($_POST["akce"]=="uprav_postavu"){
    $sql="
    delete from
      postavy_zbrane_f2f
    where
      id_uzivatele=$_SESSION[id_uzivatele]
      and rok=".var_getvalue_sn('rok')."
      ;
    ";
    $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    
    $sql="
    delete from
      postavy_zbrane_str
    where
      id_uzivatele=$_SESSION[id_uzivatele]
      and rok=".var_getvalue_sn('rok').";
    ";
    $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    
    $sql="
    delete from
      postavy_vybaveni
    where
      id_uzivatele=$_SESSION[id_uzivatele]
      and rok=".var_getvalue_sn('rok').";
    ";
    $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    
    $sql="
    delete from
      postavy_schopnosti
    where
      id_uzivatele=$_SESSION[id_uzivatele]
      and rok=".var_getvalue_sn('rok').";
    ";
    $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    
    $sql="
    delete from
      postavy_poznamka
    where
      id_uzivatele=$_SESSION[id_uzivatele]
      and rok=".var_getvalue_sn('rok').";
    ";
    $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    
    $sql="
    insert into
      postavy_zbrane_f2f
      (id_uzivatele,nazev,uc,ut,oz,rok)
    values
      ($_SESSION[id_uzivatele],'$_POST[zbran1_1]','$_POST[zbran1_2]','$_POST[zbran1_3]','$_POST[zbran1_4]',".var_getvalue_sn('rok')."),
      ($_SESSION[id_uzivatele],'$_POST[zbran2_1]','$_POST[zbran2_2]','$_POST[zbran2_3]','$_POST[zbran2_4]',".var_getvalue_sn('rok')."),
      ($_SESSION[id_uzivatele],'$_POST[zbran3_1]','$_POST[zbran3_2]','$_POST[zbran3_3]','$_POST[zbran3_4]',".var_getvalue_sn('rok')."),
      ($_SESSION[id_uzivatele],'$_POST[zbran4_1]','$_POST[zbran4_2]','$_POST[zbran4_3]','$_POST[zbran4_4]',".var_getvalue_sn('rok')."),
      ($_SESSION[id_uzivatele],'$_POST[zbran5_1]','$_POST[zbran5_2]','$_POST[zbran5_3]','$_POST[zbran5_4]',".var_getvalue_sn('rok').");
    ";
    $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    
    $sql="
    insert into
      postavy_zbrane_str
      (id_uzivatele,nazev,uc,ut,d1,d2,d3,rok)
    values
      ($_SESSION[id_uzivatele],'$_POST[szbran1_1]','$_POST[szbran1_2]','$_POST[szbran1_3]','$_POST[szbran1_4]','$_POST[szbran1_5]','$_POST[szbran1_6]',".var_getvalue_sn('rok')."),
      ($_SESSION[id_uzivatele],'$_POST[szbran2_1]','$_POST[szbran2_2]','$_POST[szbran2_3]','$_POST[szbran2_4]','$_POST[szbran2_5]','$_POST[szbran2_6]',".var_getvalue_sn('rok')."),
      ($_SESSION[id_uzivatele],'$_POST[szbran3_1]','$_POST[szbran3_2]','$_POST[szbran3_3]','$_POST[szbran3_4]','$_POST[szbran3_5]','$_POST[szbran3_6]',".var_getvalue_sn('rok')."),
      ($_SESSION[id_uzivatele],'$_POST[szbran4_1]','$_POST[szbran4_2]','$_POST[szbran4_3]','$_POST[szbran4_4]','$_POST[szbran4_5]','$_POST[szbran4_6]',".var_getvalue_sn('rok').")
    ";
    $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    
    $sql="
    insert into
      postavy_vybaveni
      (id_uzivatele,vybaveni,rok)
    values
      ($_SESSION[id_uzivatele],'$_POST[vybaveni]',".var_getvalue_sn('rok').")
    ";
    $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    
    $sql="
    insert into
      postavy_schopnosti
      (id_uzivatele,schopnosti,rok)
    values
      ($_SESSION[id_uzivatele],'$_POST[schopnosti]',".var_getvalue_sn('rok').")
    ";
    $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    
    $sql="
    insert into
      postavy_poznamka
      (id_uzivatele,poznamka,rok)
    values
      ($_SESSION[id_uzivatele],'$_POST[poznamka]',".var_getvalue_sn('rok').")
    ";
    $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    
  }

  if ($_POST["akce"]=="prihlasit_drd"){
    if(prihlas_akci($GLOBALS['ID_AKTIVITA_DRD']))
    {
      $sql="
      insert into
        drd_postava
        (id_uzivatele,rok)
      values
        ($_SESSION[id_uzivatele],".ROK.")";
      $result=dbQuery($db_jmeno,$sql,$db_spojeni);
      oznameni('Přihlášení na Mistrovství v DrD proběhlo úspěšně.');
    }
    else
      chyba('Přihlášení se nezdařilo.');
  }
  
  if ($_POST["akce"]=="odhlasit_drd"){
    if(odhlas_akci($GLOBALS['ID_AKTIVITA_DRD']))
    {
      $sql="
      delete from
        drd_postava
      where 
        id_uzivatele=$_SESSION[id_uzivatele]
      ";
      $result=dbQuery($db_jmeno,$sql,$db_spojeni);
      oznameni('Odhlášení z Mistrovství v DrD proběhlo úspěšně');
    }
    else
      chyba('Chyba odhlášení z DrD');
  }
  
  if ($_POST["akce"]=="zaloz_druzinu"){
    if (!v_druzine()){
      $sql="
        insert into
          drd_druziny
          (nazev,poznamka,spravce,verejna,blok,rok)
        values
          ('$_POST[nazev]','$_POST[poznamka]',$_SESSION[id_uzivatele],$_POST[verejna],0,".var_getvalue_sn('rok').")   
      ";                                                                        //zalozit druzinu
      dbQuery($db_jmeno,$sql,$db_spojeni);
      $sql="
        select
          id_druziny
        from
          drd_druziny
        where
          spravce=$_SESSION[id_uzivatele]
          and rok=".var_getvalue_sn('rok').";
      ";
      $result=dbQuery($db_jmeno,$sql,$db_spojeni);
      $cislo_druziny=mysql_result($result,0,0);
      prihlas_do_druziny($cislo_druziny);                                       //prihlasil sam sebe do sve druzinky

      $sql="                                 
        delete from
          drd_prihlasky
        where
          id_uzivatele=$_SESSION[id_uzivatele]
          and rok=".var_getvalue_sn('rok').";
      ";
      //echo $sql;
      $result=dbQuery($db_jmeno,$sql,$db_spojeni);                     //smazu vsechny sve stare prihlasky
    }
  }
  if ($_POST["akce"]=="vyber_pj"){
    if (jsem_spravce(moje_druzina_cislo())){
      $sql="
        update
          drd_pj
        set
          blok1=0
        where
          blok1=".moje_druzina_cislo()."                                      
      ";
      dbQuery($db_jmeno,$sql,$db_spojeni);                               //uvolneni PJ
      $sql="
        update
          drd_pj
        set
          blok2=0
        where
          blok2=".moje_druzina_cislo()."
      ";
      dbQuery($db_jmeno,$sql,$db_spojeni);                               //uvolneni PJ
      
      $sql="
        update
          drd_pj
        set
          blok3=0
        where
          blok3=".moje_druzina_cislo()."
      ";
      dbQuery($db_jmeno,$sql,$db_spojeni); 
      
      if ($_POST["pj"]>0){
        $promenna_pj=explode("_",$_POST["pj"]);
        $sql="
          select
            $promenna_pj[1]
          from
            drd_pj
          where
            id_pj=$promenna_pj[0]
        ";
        $result=dbQuery($db_jmeno,$sql,$db_spojeni);

        if (mysql_result($result,0,0) == 0){
          $sql="
            update
              drd_pj
            set        
              $promenna_pj[1]=$_POST[cislo_druziny]
            where
              id_pj=$promenna_pj[0];
          ";
          dbQuery($db_jmeno,$sql,$db_spojeni);
          if ($promenna_pj[1] == "blok1"){
            $blok=1;
          }
          elseif ($promenna_pj[1] == "blok2"){
            $blok=2;
          }
          else{
            $blok=3;
          }
          
          $sql="
            update
              drd_druziny
            set        
              blok=$blok
            where
              id_druziny=$_POST[cislo_druziny];
          ";
          dbQuery($db_jmeno,$sql,$db_spojeni);
          //echo $sql;
        }
      }
      else {
        $sql="
          update
            drd_druziny
          set        
            blok=$_POST[pj] --$promenna_pj[1]
          where
            id_druziny=$_POST[cislo_druziny];
        ";
        $blok=$_POST["pj"];
        dbQuery($db_jmeno,$sql,$db_spojeni);
      }
      $sql="
        select
          id_uzivatele
        from
          drd_uzivatele_druziny
        where
          id_druziny=$_POST[cislo_druziny];
      ";
      //echo $sql;
      $result=dbQuery($db_jmeno,$sql,$db_spojeni);
      if (mysql_num_rows($result) > 0){ 
        while($zaznam=mysql_fetch_row($result)){
          if (($blok == 1) || ($blok == -1)){
            prihlas_akci($GLOBALS['ID_AKTIVITA_DRD_BLOK1'],$zaznam[0]);
            odhlas_akci($GLOBALS['ID_AKTIVITA_DRD_BLOK2'],$zaznam[0]);
            odhlas_akci($GLOBALS['ID_AKTIVITA_DRD_BLOK3'],$zaznam[0]);
          }
          elseif (($blok == 2) || ($blok == -2)){
            prihlas_akci($GLOBALS['ID_AKTIVITA_DRD_BLOK2'],$zaznam[0]);
            odhlas_akci($GLOBALS['ID_AKTIVITA_DRD_BLOK1'],$zaznam[0]);
            odhlas_akci($GLOBALS['ID_AKTIVITA_DRD_BLOK3'],$zaznam[0]);
          }
          elseif (($blok == 3) || ($blok == -3)){
            prihlas_akci($GLOBALS['ID_AKTIVITA_DRD_BLOK3'],$zaznam[0]);
            odhlas_akci($GLOBALS['ID_AKTIVITA_DRD_BLOK1'],$zaznam[0]);
            odhlas_akci($GLOBALS['ID_AKTIVITA_DRD_BLOK2'],$zaznam[0]);
          }
          else {
            odhlas_akci($GLOBALS['ID_AKTIVITA_DRD_BLOK1'],$zaznam[0]);
            odhlas_akci($GLOBALS['ID_AKTIVITA_DRD_BLOK2'],$zaznam[0]);
            odhlas_akci($GLOBALS['ID_AKTIVITA_DRD_BLOK3'],$zaznam[0]);
          }
        }
      }
    }
  }
  if ($_POST["akce"] == "prijmout_prihlasku"){
    $sql="
      select
        id_uzivatele,
        id_druziny
      from
        drd_prihlasky
      where
        id_prihlasky=$_POST[id_prihlasky];
    ";
    $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    if (mysql_num_rows($result) > 0){
      $id_druziny=mysql_result($result,0,1);
      $id_uzivatele=mysql_result($result,0,0);
      prihlas_do_druziny($id_druziny,$id_uzivatele);
      $sql="
        delete from
          drd_prihlasky
        where
          id_prihlasky=$_POST[id_prihlasky]
      "; 
      dbQuery($db_jmeno,$sql,$db_spojeni);
      
      $sql="
        select
          blok
        from
          drd_druziny
        where
          id_druziny=".moje_druzina_cislo().";
      ";
      $result=dbQuery($db_jmeno,$sql,$db_spojeni);
      $blok=mysql_result($result,0,0);
      if (($blok == 1) || ($blok == -1)){
        prihlas_akci($GLOBALS['ID_AKTIVITA_DRD_BLOK1'],$id_uzivatele);        
      }
      elseif (($blok == 2) || ($blok == -2)){
        prihlas_akci($GLOBALS['ID_AKTIVITA_DRD_BLOK2'],$id_uzivatele);           
      }
      elseif (($blok == 3) || ($blok == -3)){
        prihlas_akci($GLOBALS['ID_AKTIVITA_DRD_BLOK3'],$id_uzivatele);           
      }
    }
  }
  if ($_POST["akce"] == "odmitnout_prihlasku"){
    $sql="
      delete from
        drd_prihlasky
      where
        id_prihlasky=$_POST[id_prihlasky]
    "; 
    dbQuery($db_jmeno,$sql,$db_spojeni);
  }
  
  if ($_POST["akce"] == "uprav_postavu"){
    $sql="
      select
        id_postavy
      from
        drd_postava
      where
        id_uzivatele=$_SESSION[id_uzivatele]
        and rok=".var_getvalue_sn('rok').";
    "; 
    //echo $sql;
    $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    if (mysql_num_rows($result) > 0){    
      $sql="
        update
          drd_postava
        set
          jmeno='$_POST[jmeno]',
          rasa=$_POST[rasa],
          povolani=$_POST[povolani]
        where
          id_uzivatele=$_SESSION[id_uzivatele]
          and rok=".var_getvalue_sn('rok').";
      "; 
      dbQuery($db_jmeno,$sql,$db_spojeni);
    }
  }
  
  if (($_POST["akce"] == "predej_spravcovstvi") && (v_druzine()) ){
    if (jsem_spravce(moje_druzina_cislo())){
      $sql="
        update
          drd_druziny
        set
          spravce=$_POST[id_noveho_spravce]
        where
          id_druziny=".moje_druzina_cislo()."
      ";
      //echo $sql;
      dbQuery($db_jmeno,$sql,$db_spojeni);
    }
  }
  
  if (($_POST["akce"] == "vyluc_uzivatele") && (v_druzine()) ){
    if (jsem_spravce(moje_druzina_cislo())){
      vyhod_z_druziny(moje_druzina_cislo(),$_POST["id_vylouceneho"]);
    }  
  }
  
  if (($_POST["akce"] == "opustit_druzinu") && ($_POST["opustit_druzinu"] == 1)){
    ?>
    <form action="<?echo $_SERVER["REQUEST_URI"]?>" id="opustit_druzinu2" method="post">
      <input type="hidden" name="akce" value="opustit_druzinu">
      <input type="hidden" name="opustit_druzinu" value="2">
      <input type="hidden" name="id_druziny" value="<?echo moje_druzina_cislo()?>">
    </form>
    <?
    if (jsem_spravce(moje_druzina_cislo())){
      $sql="
        select
          count(id_prihlaseni)
        from
          drd_uzivatele_druziny
        where
          id_druziny=".moje_druzina_cislo()."
      ";
      //echo $sql;
      $result=dbQuery($db_jmeno,$sql,$db_spojeni);
      if (mysql_result($result,0,0) > 1){
        $chyba_zobraz .= "Správce nemůže opustit družinu, nejprve předej správcovství";
      }
      else {  
        $chyba_zobraz .=  "<strong>Opravdu chceš opustit tuto družinu?</strong><br />";
        $chyba_zobraz .=  "<a href=\"javascript: document.getElementById('opustit_druzinu2').submit()\">opustit družinu</a>"; 
      } 
    }
    else {
      $chyba_zobraz .=  "<strong>Opravdu chceš opustit tuto družinu?</strong><br />";
      $chyba_zobraz .=  "<a href=\"javascript: document.getElementById('opustit_druzinu2').submit()\">opustit družinu</a>"; 
    }
  }

  if (($_POST["akce"] == "opustit_druzinu") && ($_POST["opustit_druzinu"] == 2)){
    if (jsem_spravce(moje_druzina_cislo())){
      $sql="
        select
          count(id_prihlaseni)
        from
          drd_uzivatele_druziny
        where
          id_druziny=".moje_druzina_cislo()."
      ";
      $result=dbQuery($db_jmeno,$sql,$db_spojeni);
      if (mysql_num_rows($result) > 1){
        echo "Správce nemůže opustit družinu, nejprve předej správcovství";
      }
      else {  
        $id_druziny=moje_druzina_cislo();
        $sql="
          select
            blok
          from
            drd_druziny
          where
            id_druziny=$id_druziny;
        ";
        $result=dbQuery($db_jmeno,$sql,$db_spojeni);
        $blok=mysql_result($result,0,0);
        
        
        if($blok) //pokud družina zabírala blok, uvolnit
          dbQuery("
            update
              drd_pj
            set
              blok$blok=0
            where
              blok$blok=$id_druziny");
      
        vyhod_z_druziny($id_druziny);
        
        $result=dbQuery($db_jmeno,$sql,$db_spojeni);
          dbQuery($db_jmeno,$sql,$db_spojeni);
          $sql="
            delete from
              drd_druziny
            where
              id_druziny=$_POST[id_druziny];
          ";
          dbQuery($db_jmeno,$sql,$db_spojeni);
        }  
      }
      else {
        vyhod_z_druziny(moje_druzina_cislo());
      }
    }
  
  if ($_POST["akce"] == "podat_prihlasku"){
    $sql="
      select
        id_druziny
      from
        drd_prihlasky
      where
        id_uzivatele=$_SESSION[id_uzivatele] and
        id_druziny=$_POST[id_druziny]
    "; 
    $result=dbQuery($db_jmeno,$sql,$db_spojeni);
    if (mysql_num_rows($result)==0){
    $sql="
      select
        id_druziny
      from
        drd_druziny
      where
        id_druziny=$_POST[id_druziny]
      ";
      $result=dbQuery($db_jmeno,$sql,$db_spojeni);
      if (mysql_num_rows($result)>0){
        $sql="
          insert into
            drd_prihlasky
            (id_uzivatele,id_druziny,vzkaz_spravce,rok)
          values
            ($_SESSION[id_uzivatele],$_POST[id_druziny],'$_POST[vzkaz_spravce]',".var_getvalue_sn('rok').")
        ";
        $result=dbQuery($db_jmeno,$sql,$db_spojeni);
      }
    }
  }
  if ($_POST["akce"] == "zrusit_zadost"){
    $sql="
      delete from
        drd_prihlasky
      where
        id_druziny=$_POST[id_druziny] and
        id_uzivatele=$_SESSION[id_uzivatele];
    "; 
    dbQuery($db_jmeno,$sql,$db_spojeni);
  }
  
  if ($_POST["akce"] == "zmen_druzinu"){
    //echo "su tu!";
    if (jsem_spravce($_POST["id_druziny"])){
      $sql="
        update
          drd_druziny
        set
          nazev='$_POST[nazev]',
          verejna=$_POST[typ],
          poznamka='$_POST[poznamka]'
        where
          id_druziny=".moje_druzina_cislo()."
      "; 
      //echo $sql;
      dbQuery($db_jmeno,$sql,$db_spojeni);
    }
  }
  
    
  if (isset($chyba_zobraz)){
    echo "<div class=\"chyba_ramecek\">$chyba_zobraz</div>";
    unset($chyba_zobraz);
  }
    


  if (ma_pravo($_SESSION["id_uzivatele"],$GLOBALS['ID_PRAVO_DRD'])){
    ?>
    <form action="<?echo $_SERVER["REQUEST_URI"]?>" id="odhlasit_drd" method="post">
      <input type="hidden" name="akce" value="odhlasit_drd">
    </form>
    <?
    
    if (!v_druzine()){
    echo 
      "
      <strong>Jsi přihlášen na Mistrovství v DrD.</strong> 
      <ul>
        <li>
          <strong><a href=\"javascript: document.getElementById('odhlasit_drd').submit()\">Odhlásit se z Mistrovství v DrD</a></strong>
        </li>
      </ul>
      ";
    }
    else {
      echo 
        "
        <strong>Jsi přihlášen na Mistrovství v DrD.</strong> 
        <ul>
          <li>
            <strong>Pro odhlášení z Mistrovství v DrD nejprve opusť družinu.</strong>
          </li>
        </ul>
        ";
    }
    
    
      
      
      if (!v_druzine()){
        echo "<h2>Moje družina</h2>";
        echo "<p><em>Nejsi členem žádné družiny</em>. Můžeš se do nějaké družiny přidat, nebo si <a href=\"zalozit druzinu\" onclick=\"ukaz(0); return false;\">založit vlastní</a>.</p>";
        ?>
        <span id="objekt0" style="display: none;">
          <form action="<?echo $_SERVER['REQUEST_URI'];?>" method="post">
            <input type="hidden" name="akce" value="zaloz_druzinu" />            
            <div class="registrace_radek">
              <h3>Založit vlastní družinu</h3>
            </div>
            <div class="registrace_radek">
              <div class="registrace_popis"><strong>název družiny</strong></div>
              <div class="registrace_input"><input type="text" name="nazev"></div>
            </div>
            <div class="registrace_radek">
              <div class="registrace_popis"><strong>typ zobrazení</strong></div>
              <div class="registrace_input" style="padding-top: 2px;">
                <select class="registrace_select2" name="verejna">
                  <option value="1" selected="selected">veřejná</option>
                  <option value="0">neveřejná</option>              
                </select>
              </div>
            </div>
            <div class="registrace_radek">
              <em>Neveřejná</em> má v přehledu družin zobrazen název, poznámku a informace o správci.<br />
              U <em>veřejné</em> družiny jsou zobrazeny informace o všech členech (přihlašovací jméno, rasa a povolání postavy pro DrD).
            </div>
            <div class="registrace_radek">
              <br /><strong>Poznámka</strong>  
              <div class="registrace_textarea"><textarea name="poznamka"></textarea></div>
              Do <em>poznámky</em> vlož krátkou zprávu, která se zobrazí v přehledu družin (můžeš takle například shánět chybějící členy či provokovat soupeře).
            </div>
            <div class="buttonky" style="padding-left: 200px;"><input type="image" src="files/styly/styl-aktualni/tlacitka/odeslat.gif" value="Submit" alt="odeslat" style="width: 88px; height: 26px; margin-top: 10px;"></div>
          </form>
        </span>
        <?
        
        //
        echo "<h2 style=\"clear:both;\">Výpis mých žádostí o členství v družině</h2>";
        $sql="
          select
            id_druziny
          from
            drd_prihlasky
          where
            id_uzivatele=$_SESSION[id_uzivatele]
            and rok=".var_getvalue_sn('rok').";
        ";
        //echo $sql;
        $result=dbQuery($db_jmeno,$sql,$db_spojeni);
        if (mysql_num_rows($result) > 0){
          echo "<ul>";
          while($zaznam=mysql_fetch_row($result)){
            echo "<li>podaná žádost družině <strong>č. ".($zaznam[0]-DRD_POSUN)." - ".druzina_jmeno($zaznam[0])."</strong> (<a href=\"javascript: document.getElementById('zrusit_zadost$zaznam[0]').submit()\">zrušit žádost</a>)";
            ?>
            <form action="<?echo $_SERVER["REQUEST_URI"]?>" id="zrusit_zadost<?echo $zaznam[0]?>" method="post">
              <input type="hidden" name="akce" value="zrusit_zadost">
              <input type="hidden" name="id_druziny" value="<?echo $zaznam[0]?>">
            </form>
            <?
          }
          echo "</ul>";
        }
        else {
          echo "<ul><li>Nemáš podanou žádnou žádost</li></ul>";
        }

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
                <h3 style=\"float: left; margin-top: 0px; padding-top: 0px;\">č. ".($zaznam[0]-DRD_POSUN)." - ".druzina_jmeno($zaznam[0])."</h3> <div style=\"float: right\"><a href=\"členové\" onclick=\"ukaz(100$zaznam[0]); return false;\">podat přihlášku</a> / <a href=\"členové\" onclick=\"ukaz($zaznam[0]); return false;\">členové</a></div>
                <div style=\"clear: both\">
                  <strong>Správce:</strong> ".druzina_spravce($zaznam[0])."<br />
                  <strong>Herní blok: </strong>".dekoduj_blok($zaznam[1])."<br />
                  <strong>Pán Jeskyně: </strong>".druzina_pj($zaznam[0])."<br />
                  <strong>Poznámka: </strong>".nl2br(druzina_poznamka($zaznam[0]))."<br />";
                  echo '<span id="objekt'.$zaznam[0].'" style="display: none;">';
                  druzina_clenove($zaznam[0]);
              echo "</span>";
            ?>
            <form action="<?echo $_SERVER["REQUEST_URI"]?>" method="post">
              <input type="hidden" name="akce" value="podat_prihlasku">
              <input type="hidden" name="id_druziny" value="<?echo $zaznam[0]?>">
              <span id="objekt<?echo "100$zaznam[0]"?>" style="display: none;">
              <br /><strong>Vzkaz pro správce družiny</strong>
              <div class="registrace_textarea"><textarea style="width: 450px; height: 60px;" name="vzkaz_spravce"></textarea></div>
              <div class="buttonky" style="padding-left: 200px;"><input type="image" src="files/styly/styl-aktualni/tlacitka/odeslat.gif" value="Submit" alt="odeslat" style="width: 88px; height: 26px; margin-top: 10px;"></div></span>
            </form>
            <?
            echo "</div></div>";
          }
        }
        else {
          echo "<ul><li>Ještě není založena žádná družina</li></ul>";
        }
        
      }
      else {
        echo "<h2>Moje družina (".druzina_jmeno(moje_druzina_cislo()).")</h2>";
        if(jsem_spravce(moje_druzina_cislo())){
          ?>
           <strong>Informace o prvním kole</strong>
          <?
          $sql="
            select
              blok
            from
              drd_druziny
            where
              id_druziny=".moje_druzina_cislo().";
          "; 
          $result=dbQuery($db_jmeno,$sql,$db_spojeni);
          $blok_cislo=mysql_result($result,0,0);
          $blok=dekoduj_blok($blok_cislo);
          
          if ($blok_cislo > 0){
            $sql="
              select
                jmeno_pj
              from
                drd_pj
              where
                blok$blok_cislo=".moje_druzina_cislo().";
            ";
            //echo $sql;
            $result=dbQuery($db_jmeno,$sql,$db_spojeni);
            $pj=mysql_result($result,0,0);
          }
          else {
            $pj="nevybrán";
          }
          
          echo "<ul><li><strong>pán jeskyně: </strong>$pj</li><li><strong>herní blok</strong>: $blok</li></ul>"
          ?>
          <strong>Členové družiny</strong>
        <? 
        $sql="
          select
            id_uzivatele
          from
            drd_uzivatele_druziny
          where
            id_druziny=".moje_druzina_cislo().";          
        ";
        $result=dbQuery($db_jmeno,$sql,$db_spojeni);
        if (mysql_num_rows($result) > 0){
          echo "<ul>";
          while($zaznam=mysql_fetch_row($result)){
            vypis_clena_uvnitr($zaznam[0]);
          }
          echo "</li>";
          echo "</ul>";
        }
        else {
         echo "a kruva, tady máme prázdnou družinu, která si spokojeně žije vlastním životem:-/";
        }
        ?>
        <strong style="clear:both;">Žádosti o členství v družině</strong>
          <?
          $druzina_cislo=moje_druzina_cislo();
          $sql="
            select 
              prih.id_uzivatele,
              prih.id_prihlasky,
              prih.vzkaz_spravce,
              uziv.id_uzivatele,
              uziv.login_uzivatele,
              uziv.email1_uzivatele
            from
              drd_prihlasky prih,
              uzivatele_hodnoty uziv
            where
              prih.id_druziny=$druzina_cislo and
              prih.id_uzivatele=uziv.id_uzivatele 
          ";
          //echo $sql;
          $result=dbQuery($db_jmeno,$sql,$db_spojeni);
          if (mysql_num_rows($result) > 0){
            echo "<ul>";
            while($zaznam=mysql_fetch_row($result)){?>
            <form action="<?echo $_SERVER["REQUEST_URI"]?>" id="prijmout_prihlasku<?echo $zaznam[1]?>" method="post">
              <input type="hidden" name="akce" value="prijmout_prihlasku">
              <input type="hidden" name="id_prihlasky" value="<?echo $zaznam[1]?>">
            </form>
            <form action="<?echo $_SERVER["REQUEST_URI"]?>" id="odmitnout_prihlasku<?echo $zaznam[1]?>" method="post">
              <input type="hidden" name="akce" value="odmitnout_prihlasku">
              <input type="hidden" name="id_prihlasky" value="<?echo $zaznam[1]?>">
            </form>
            <?
              if (!empty($zaznam[2])){
                $vzkaz="<br /><strong>Vzkaz: </strong>$zaznam[2]";
              } 
              echo "<li><strong>id: ".$zaznam[3].", $zaznam[4]</strong> - $zaznam[5] (<a href=\"javascript: document.getElementById('prijmout_prihlasku$zaznam[1]').submit()\">přijmout</a>/<a href=\"javascript: document.getElementById('odmitnout_prihlasku$zaznam[1]').submit()\">odmítnout</a>)$vzkaz</li>";
            }
            echo "</ul>";
          }
          else {
            echo "<ul><li>V tuto chvíli nepodal nikdo přihlášku do této družiny</li></ul>";
          } ?>
        
        <strong>Opustit družinu</strong>
        <form action="<?echo $_SERVER["REQUEST_URI"]?>" id="opustit_druzinu" method="post">
          <input type="hidden" name="akce" value="opustit_druzinu">
          <input type="hidden" name="opustit_druzinu" value="1">
        </form>
        <?
        echo "<ul><li><a href=\"javascript: document.getElementById('opustit_druzinu').submit()\">Chci opustit tuto družinu</a></li></ul>";?>
          

<?php /* ---------------------------Výběr PJ------------------------------ */ ?>
<?php
  $limitObsazeniBloku=$GLOBALS['DRD_LIMIT_BLOKU']; //maximální počet účastníků v bloku
  //TODO, zatím hardcode //$limitVytizeniPJ=1; //maximální počet bloků, kolik může PJ hrát (stále možno ručně zrušit nějaké bloky pomocí -1 v databázi)
  //načtení počtů registrovaných na bloky
  $obsazeniBloku=array('blok1'=>0,'blok2'=>0,'blok3'=>0);
  $odpoved=dbQuery('
    SELECT abs(blok) as blok, count(1) as pocet 
    FROM drd_druziny 
    WHERE rok='.ROK.' 
    GROUP BY abs(blok)');
  while($radek=mysql_fetch_array($odpoved))
    $obsazeniBloku['blok'.$radek['blok']]=$radek['pocet'];
?>
          
          <form action="<?echo $_SERVER['REQUEST_URI'];?>" method="post">
            <input type="hidden" name="akce" value="vyber_pj" />
            <input type="hidden" name="cislo_druziny" value="<?echo moje_druzina_cislo()?>" />              
              <h2>Nastavení bloku a Pána Jeskyně pro první kolo</h2>
            <div class="registrace_radek">
              <strong>Volní PJ pro první kolo</strong>
            </div>
            <div class="registrace_radek">
              <select class="registrace_select2" name="pj" style="width: 325px;">
              <?
              $sql="
                  select
                    blok
                  from
                    drd_druziny
                  where
                    id_druziny=".moje_druzina_cislo().";
                ";
                $result=dbQuery($db_jmeno,$sql,$db_spojeni);
                $blok=mysql_result($result,0,0);
              ?>
                <option value="0" <?$blok == 0 ? $sel='selected="selected"' : $sel=""; echo $sel; unset($sel);?>>nevybrán blok ani PJ</option>
                <?
                $sql="
                  select
                    id_pj,
                    jmeno_pj,
                    blok1
                  from
                    drd_pj
                  where
                    (blok1=0 or
                    blok1=".moje_druzina_cislo().")
                    ".(DRD_VYBER_PJ?'':'and 1=0')."
                    AND ".$obsazeniBloku['blok1'].'<'.$limitObsazeniBloku."
                    AND (blok2<1 OR blok3<1) -- nektery z ostatnich bloku musi byt PJ nevyuzity, jinak je pretizen
                    AND rok=$ROK_AKTUALNI
                  order by
                    jmeno_pj asc
                ";
                $result=dbQuery($db_jmeno,$sql,$db_spojeni);
                if (mysql_num_rows($result) > 0){
                  ?><option value="-1" <?$blok == -1 ? $sel='selected="selected"' : $sel=""; echo $sel; unset($sel);?>>blok I. (ráno), PJ nevybrán</option><?
                  while($zaznam=mysql_fetch_row($result)){
                    $id_pj=$zaznam[0];
                    $jmeno_pj=$zaznam[1];
                    $zaznam[2] == moje_druzina_cislo() ? $sel='selected="selected"' : $sel="";
                    echo '<option value="'.$zaznam[0].'_blok1" '.$sel.'>blok I. (ráno), '.$zaznam[1].'</option>';
                    unset($sel);
                  }
                }
                $sql="
                  select
                    id_pj,
                    jmeno_pj,
                    blok2
                  from
                    drd_pj
                  where
                    (blok2=0 or
                    blok2=".moje_druzina_cislo().")
                    ".(DRD_VYBER_PJ?'':'and 1=0')."
                    AND ".$obsazeniBloku['blok2'].'<'.$limitObsazeniBloku."
                    AND (blok1<1 OR blok3<1)
                    AND rok=$ROK_AKTUALNI
                  order by
                    jmeno_pj asc
                ";
                $result=dbQuery($db_jmeno,$sql,$db_spojeni);
                if (mysql_num_rows($result) > 0){
                  ?><option value="-2" <?$blok == -2 ? $sel='selected="selected"' : $sel=""; echo $sel; unset($sel);?>>blok II. (odpoledne), PJ nevybrán</option><?
                  while($zaznam=mysql_fetch_row($result)){
                    $id_pj=$zaznam[0];
                    $jmeno_pj=$zaznam[1];
                    $zaznam[2] == moje_druzina_cislo() ? $sel='selected="selected"' : $sel="";
                    echo '<option value="'.$zaznam[0].'_blok2" '.$sel.'>blok II. (odpoledne), '.$zaznam[1].'</option>';
                    unset($sel);
                  }
                }
                $sql="
                  select
                    id_pj,
                    jmeno_pj,
                    blok3
                  from
                    drd_pj
                  where
                    (blok3=0 or
                    blok3=".moje_druzina_cislo().")
                    ".(DRD_VYBER_PJ?'':'and 1=0')."
                    AND ".$obsazeniBloku['blok3'].'<'.$limitObsazeniBloku."
                    AND (blok1<1 OR blok2<1)
                    AND rok=$ROK_AKTUALNI
                  order by
                    jmeno_pj asc
                ";
                $result=dbQuery($db_jmeno,$sql,$db_spojeni);
                if (mysql_num_rows($result) > 0){
                  ?><option value="-3" <?$blok == -3 ? $sel='selected="selected"' : $sel=""; echo $sel; unset($sel);?>>blok III. (večer), PJ nevybrán</option><?
                  while($zaznam=mysql_fetch_row($result)){
                    $id_pj=$zaznam[0];
                    $jmeno_pj=$zaznam[1];
                    $zaznam[2] == moje_druzina_cislo() ? $sel='selected="selected"' : $sel="";
                    echo '<option value="'.$zaznam[0].'_blok3" '.$sel.'>blok III. (večer), '.$zaznam[1].'</option>';
                    unset($sel);
                  }
                }
                
                ?>
              </select>
            </div>
            <div class="registrace_radek">
              O zpřístupnění výběru PJů budeme informovat v novinkách a správce družin emailem.
            </div>
            <div class="buttonky" style="padding-left: 200px;"><input type="image" src="files/styly/styl-aktualni/tlacitka/odeslat.gif" value="Submit" alt="odeslat" style="width: 88px; height: 26px; margin-top: 10px;"></div>
          </form>
          <div style="clear: both;"><br /></div>
<?php /* --------------------------/Výběr PJ------------------------------ */ ?>

          
          <h2>Změna atributů družiny</h2>
          <?
            $sql="
              select
                poznamka,
                verejna,
                nazev
              from
                drd_druziny
              where
                id_druziny=".moje_druzina_cislo().";
            ";
            $result=dbQuery($db_jmeno,$sql,$db_spojeni);
            $poznamka=mysql_result($result,0,0);
            $verejna=mysql_result($result,0,1);
            $jmeno=mysql_result($result,0,2);
          ?>
          <form action="<?echo $_SERVER['REQUEST_URI'];?>" method="post">
            <input type="hidden" name="akce" value="zmen_druzinu" />
            <input type="hidden" name="id_druziny" value="<?echo moje_druzina_cislo()?>" />      
            <div class="registrace_radek">
              <div class="registrace_popis"><strong>název družiny</strong></div>
              <div class="registrace_input"><input type="text" name="nazev" value="<?echo $jmeno?>"></div>
            </div>
            <div class="registrace_radek">
              <div class="registrace_popis"><strong>typ zobrazení</strong></div>
              <div class="registrace_input" style="padding-top: 2px;">
                <select class="registrace_select2" name="typ">
                  <option value="1" <?$verejna == 1 ? $sel='selected="selected"' : $sel=""; echo $sel; unset($sel)?>>veřejná</option>
                  <option value="0"<?$verejna == 0 ? $sel='selected="selected"' : $sel=""; echo $sel; unset($sel)?>>neveřejná</option>              
                </select>
              </div>
            </div>
            <div class="registrace_radek">
              <strong>poznámka:</strong><br /> 
              <div class="registrace_textarea"><textarea name="poznamka"><?echo $poznamka?></textarea></div>
              Do <em>poznámky</em> vlož krátkou zprávu, která se zobrazí v přehledu družin (můžeš takle například shánět chybějící členy či provokovat soupeře).<br /><br />
            </div>
            <div class="buttonky" style="padding-left: 200px;"><input type="image" src="files/styly/styl-aktualni/tlacitka/odeslat.gif" value="Submit" alt="odeslat" style="width: 88px; height: 26px; margin-top: 10px;"></div>
          </form>
          
          <?
        }
        else {
          ?>
          <h3>Informace o 1. kole</h3>
          <?
          $sql="
            select
              blok
            from
              drd_druziny
            where
              id_druziny=".moje_druzina_cislo().";
          "; 
          $result=dbQuery($db_jmeno,$sql,$db_spojeni);
          $blok_cislo=mysql_result($result,0,0);
          $blok=dekoduj_blok($blok_cislo);
          
          if ($blok_cislo > 0){
            $sql="
              select
                jmeno_pj
              from
                drd_pj
              where
                blok$blok_cislo=".moje_druzina_cislo().";
            ";
            $result=dbQuery($db_jmeno,$sql,$db_spojeni);
            $pj=mysql_result($result,0,0);
          }
          else {
            $pj="nevybrán";
          }
          
          echo "<ul><li><strong>pán jeskyně: </strong>$pj</li><li><strong>herní blok</strong>: $blok</li></ul>"
          ?>
          
          
          
          <strong>Členové družiny</strong>
          <? 
          $sql="
            select
              id_uzivatele
            from
              drd_uzivatele_druziny
            where
              id_druziny=".moje_druzina_cislo().";          
          ";
          $result=dbQuery($db_jmeno,$sql,$db_spojeni);
          if (mysql_num_rows($result) > 0){
            echo "<ul>";
            while($zaznam=mysql_fetch_row($result)){
              vypis_clena_uvnitr($zaznam[0]);
              if (jsem_spravce(moje_druzina_cislo(),$zaznam[0])){
                echo " (správce družiny)";
              }
              echo "</li>";
            }
            echo "</ul>";
          }
          else {
            "a kruva, tady máme prázdnou družinu, která si spokojeně žije vlastním životem:-/";
          }
          ?><strong>Opustit družinu</strong>
        <form action="<?echo $_SERVER["REQUEST_URI"]?>" id="opustit_druzinu" method="post">
          <input type="hidden" name="akce" value="opustit_druzinu">
          <input type="hidden" name="opustit_druzinu" value="1">
        </form>
        <?
        echo "<ul><li><a href=\"javascript: document.getElementById('opustit_druzinu').submit()\">Chci opustit tuto družinu</a></li></ul>";
        }
        
        
        //co se zobrazi i spraci i nespravci

        
        //nastaveni postavy:
        $sql="
          select
            jmeno,
            rasa,
            povolani
          from
            drd_postava
          where
            id_uzivatele=$_SESSION[id_uzivatele]
            and rok=".var_getvalue_sn('rok').";
        ";
        //echo $sql;
        $result=dbQuery($db_jmeno,$sql,$db_spojeni);
      
          $jmeno=mysql_result($result,0,0);
          $rasa=mysql_result($result,0,1);
          $povolani=mysql_result($result,0,2);
    
        ?>
        <h2 style="clear: both;">Moje postava</h2>
        <form action="<?echo $_SERVER["REQUEST_URI"]?>" method="post">
          <input type="hidden" name="akce" value="uprav_postavu" />
          <input type="text" name="jmeno" value="<?echo $jmeno?>" />
          <select class="registrace_select2" name="rasa">
            <option value="0" <?$rasa == 0 ? $sel='selected="selected"' : $sel=""; echo $sel; unset($sel);?>>rasa</option>              
            <option value="1" <?$rasa == 1 ? $sel='selected="selected"' : $sel=""; echo $sel; unset($sel);?>>Člověk</option>
            <option value="2" <?$rasa == 2 ? $sel='selected="selected"' : $sel=""; echo $sel; unset($sel);?>>Barbar</option>
            <option value="3" <?$rasa == 3 ? $sel='selected="selected"' : $sel=""; echo $sel; unset($sel);?>>Elf</option>
            <option value="4" <?$rasa == 4 ? $sel='selected="selected"' : $sel=""; echo $sel; unset($sel);?>>Trpaslík</option>
            <option value="5" <?$rasa == 5 ? $sel='selected="selected"' : $sel=""; echo $sel; unset($sel);?>>Kudůk</option>
            <option value="6" <?$rasa == 6 ? $sel='selected="selected"' : $sel=""; echo $sel; unset($sel);?>>Hobit</option>
            <option value="7" <?$rasa == 7 ? $sel='selected="selected"' : $sel=""; echo $sel; unset($sel);?>>Kroll</option>
          </select>
          <select class="registrace_select2" name="povolani">
            <option value="0" <?$povolani == 0 ? $sel='selected="selected"' : $sel=""; echo $sel; unset($sel);?>>povolání</option>
            <option value="1" <?$povolani == 1 ? $sel='selected="selected"' : $sel=""; echo $sel; unset($sel);?>>Bojovník</option>              
            <option value="2" <?$povolani == 2 ? $sel='selected="selected"' : $sel=""; echo $sel; unset($sel);?>>Šermíř</option>
            <option value="3" <?$povolani == 3 ? $sel='selected="selected"' : $sel=""; echo $sel; unset($sel);?>>Čaroděj</option>
            <option value="4" <?$povolani == 4 ? $sel='selected="selected"' : $sel=""; echo $sel; unset($sel);?>>Mág</option>
            <option value="5" <?$povolani == 5 ? $sel='selected="selected"' : $sel=""; echo $sel; unset($sel);?>>Pyrofor</option>
            <option value="6" <?$povolani == 6 ? $sel='selected="selected"' : $sel=""; echo $sel; unset($sel);?>>Theurg</option>
            <option value="7" <?$povolani == 7 ? $sel='selected="selected"' : $sel=""; echo $sel; unset($sel);?>>Chodec</option>
            <option value="8" <?$povolani == 8 ? $sel='selected="selected"' : $sel=""; echo $sel; unset($sel);?>>Druid</option>
            <option value="9" <?$povolani == 9 ? $sel='selected="selected"' : $sel=""; echo $sel; unset($sel);?>>Lupič</option>
            <option value="10" <?$povolani == 10 ? $sel='selected="selected"' : $sel=""; echo $sel; unset($sel);?>>Sicco</option>
          </select>
          <input type="image" src="files/styly/styl-aktualni/tlacitka/odeslat.gif" value="Submit" alt="odeslat" style="position: relative; top: 8px;">
          <p>V textových polích "Vybavení", "Schopnosti" a "Poznámka" oddělujte jednotlivé položky odentrováním.</p>
          <p>Svůj <b>vyplněný osobní deník</b> si můžete prohlédnout a <b>vytisknout</b> <a href="drd-osobni-denik" onclick="window.open(this.href);return false">zde</a>.</p>
          <p><br /></p>
          <?
          if (ma_pravo($_SESSION["id_uzivatele"],$GLOBALS['ID_PRAVO_DRD'])){
            if ($povolani == 2)$povolani=1;
            if ($povolani == 4)$povolani=3;
            if ($povolani == 6)$povolani=5;
            if ($povolani == 8)$povolani=7;
            
          ?>
          <div class=postavy_radek>
            <div class="postavy_vlastnost">
              Síla:
            </div>
            <div class="postavy_hodnota">
              <?echo $postavy[$rasa][$povolani][1]?>
            </div>
          </div>
          <div class=postavy_radek>
            <div class="postavy_vlastnost">
              Obratnost:
            </div>
            <div class="postavy_hodnota">
              <?echo $postavy[$rasa][$povolani][2]?>
            </div>
          </div>
          <div class=postavy_radek>
            <div class="postavy_vlastnost">
              Odolnost:
            </div>
            <div class="postavy_hodnota">
              <?echo $postavy[$rasa][$povolani][3]?>
            </div>
          </div>
          <div class=postavy_radek>
            <div class="postavy_vlastnost">
              Inteligence:
            </div>
            <div class="postavy_hodnota">
              <?echo $postavy[$rasa][$povolani][4]?>
            </div>
          </div>
          <div class=postavy_radek>
            <div class="postavy_vlastnost">
              Charisma:
            </div>
            <div class="postavy_hodnota">
              <?echo $postavy[$rasa][$povolani][5]?>
            </div>
          </div>
          <div class=postavy_radek>
            <div class="postavy_vlastnost">
              Počet životů:
            </div>
            <div class="postavy_hodnota">
              <?echo $postavy[$rasa][$povolani][6]?>
            </div>
          </div>
          
          <form action="mistrovstvi-v-drd/prihlaska-na-drd" method="post">
            <input type="hidden" name="akce" value="uprav_postavu" />
            <div class=postavy_radek>
              <h3>Zbraně tváří v tvář</h3>
            </div>
            <div class=postavy_radek>
              <div class="postavy_zbran1">Název</div>
              <div class="postavy_zbran2">ÚČ</div>
              <div class="postavy_zbran2">Útoč</div>
              <div class="postavy_zbran2">OZ</div>
            </div>
            
            <?
            $sql="
              select 
                nazev,
                uc,
                ut,
                oz
              from
                postavy_zbrane_f2f
              where
                id_uzivatele=$_SESSION[id_uzivatele]
                and rok=".var_getvalue_sn('rok')."
              order by
                id_zbrane_f2f asc;
            ";
            $result=dbQuery($db_jmeno,$sql,$db_spojeni);
            if (mysql_num_rows($result) > 0){
              $poc=1;
              while($zaznam=mysql_fetch_row($result)){
              ?>
              <div class=postavy_radek>
                <div class="postavy_zbran1"><input type="text" name="zbran<?echo $poc?>_1" value="<?echo $zaznam[0]?>" /></div>
                <div class="postavy_zbran2"><input type="text" name="zbran<?echo $poc?>_2" value="<?echo $zaznam[1]?>" /></div>
                <div class="postavy_zbran2"><input type="text" name="zbran<?echo $poc?>_3" value="<?echo $zaznam[2]?>" /></div>
                <div class="postavy_zbran2"><input type="text" name="zbran<?echo $poc?>_4" value="<?echo $zaznam[3]?>" /></div>
              </div>
              <?
              $poc++;
              }
              for ($i=($poc+1); $i<=5; $i++){
              ?>
              <div class=postavy_radek>
                <div class="postavy_zbran1"><input type="text" name="zbran<?echo $poc?>_1" /></div>
                <div class="postavy_zbran2"><input type="text" name="zbran<?echo $poc?>_2" /></div>
                <div class="postavy_zbran2"><input type="text" name="zbran<?echo $poc?>_3" /></div>
                <div class="postavy_zbran2"><input type="text" name="zbran<?echo $poc?>_4" /></div>
              </div>
              <?
              }
            }
            else {
              for ($i=1; $i<=5; $i++){
              ?>
              <div class=postavy_radek>
                <div class="postavy_zbran1"><input type="text" name="zbran<?echo $i?>_1" /></div>
                <div class="postavy_zbran2"><input type="text" name="zbran<?echo $i?>_2" /></div>
                <div class="postavy_zbran2"><input type="text" name="zbran<?echo $i?>_3" /></div>
                <div class="postavy_zbran2"><input type="text" name="zbran<?echo $i?>_4" /></div>
              </div>
              <?
              }
            }
            ?>
    
            <div class=postavy_radek>
              <h3>Zbraně na dálku</h3>
            </div>
            <div class=postavy_radek>
                <div class="postavy_zbran3">Název</div>
                <div class="postavy_zbran2">ÚČ</div>
                <div class="postavy_zbran2">Útoč</div>
                <div class="postavy_zbran2">m</div>
                <div class="postavy_zbran2">s</div>
                <div class="postavy_zbran2">v</div>
              </div>
            
            <?
            $sql="
              select 
                nazev,
                uc,
                ut,
                d1,
                d2,
                d3
              from
                postavy_zbrane_str
              where
                id_uzivatele=$_SESSION[id_uzivatele]
                and rok=".var_getvalue_sn('rok')."
              order by
                id_zbrane_str asc;
            ";
            $result=dbQuery($db_jmeno,$sql,$db_spojeni);
            if (mysql_num_rows($result) > 0){
              $poc=1;
              while($zaznam=mysql_fetch_row($result)){
              ?>
              <div class=postavy_radek>
                <div class="postavy_zbran3"><input type="text" name="szbran<?echo $poc?>_1" value="<?echo $zaznam[0]?>" /></div>
                <div class="postavy_zbran2"><input type="text" name="szbran<?echo $poc?>_2" value="<?echo $zaznam[1]?>" /></div>
                <div class="postavy_zbran2"><input type="text" name="szbran<?echo $poc?>_3" value="<?echo $zaznam[2]?>" /></div>
                <div class="postavy_zbran2"><input type="text" name="szbran<?echo $poc?>_4" value="<?echo $zaznam[3]?>" /></div>
                <div class="postavy_zbran2"><input type="text" name="szbran<?echo $poc?>_5" value="<?echo $zaznam[4]?>" /></div>
                <div class="postavy_zbran2"><input type="text" name="szbran<?echo $poc?>_6" value="<?echo $zaznam[5]?>" /></div>
              </div>
              <?
              $poc++;
              }
              for ($i=($poc+1); $i<=4; $i++){
              ?>
              <div class=postavy_radek>
                <div class="postavy_zbran3"><input type="text" name="szbran<?echo $poc?>_1" /></div>
                <div class="postavy_zbran2"><input type="text" name="szbran<?echo $poc?>_2" /></div>
                <div class="postavy_zbran2"><input type="text" name="szbran<?echo $poc?>_3" /></div>
                <div class="postavy_zbran2"><input type="text" name="szbran<?echo $poc?>_4" /></div>
                <div class="postavy_zbran2"><input type="text" name="szbran<?echo $poc?>_5" /></div>
                <div class="postavy_zbran2"><input type="text" name="szbran<?echo $poc?>_6" /></div>
              </div>
              <?
              }
            }
            else {
              for ($i=1; $i<=4; $i++){
              ?>
              <div class=postavy_radek>
                <div class="postavy_zbran3"><input type="text" name="szbran<?echo $i?>_1" /></div>
                <div class="postavy_zbran2"><input type="text" name="szbran<?echo $i?>_2" /></div>
                <div class="postavy_zbran2"><input type="text" name="szbran<?echo $i?>_3" /></div>
                <div class="postavy_zbran2"><input type="text" name="szbran<?echo $i?>_4" /></div>
                <div class="postavy_zbran2"><input type="text" name="szbran<?echo $i?>_5" /></div>
                <div class="postavy_zbran2"><input type="text" name="szbran<?echo $i?>_6" /></div>
              </div>
              <?
              }
            }
            ?>
            
      
            <div class=postavy_radek>
              <h3>Zvláštní schopnosti a kouzla</h3>
            </div>
            <?
            $sql="
              select 
                schopnosti
              from
                postavy_schopnosti
              where
                id_uzivatele=$_SESSION[id_uzivatele]
                and rok=".var_getvalue_sn('rok').";
            ";
            $result=dbQuery($db_jmeno,$sql,$db_spojeni);
            if (mysql_num_rows($result) > 0){
              $schopnosti=mysql_result($result,0,0);
            }
            ?>
            <div class=postavy_radek>
              <div class="postavy_textarea">
                <textarea name="schopnosti"><?echo @$schopnosti ?></textarea>
              </div>
            </div>
            
            <div class=postavy_radek>
              <h3>Vybavení</h3>
            </div>
            <?
            $sql="
              select 
                vybaveni
              from
                postavy_vybaveni
              where
                id_uzivatele=$_SESSION[id_uzivatele]
                and rok=".var_getvalue_sn('rok').";
            ";
            $result=dbQuery($db_jmeno,$sql,$db_spojeni);
            if (mysql_num_rows($result) > 0){
              $vybaveni=mysql_result($result,0,0);
            }
            ?>
            <div class=postavy_radek>
              <div class="postavy_textarea">
                <textarea name="vybaveni"><?echo @$vybaveni ?></textarea>
              </div>
            </div>
            
            <div class=postavy_radek>
              <h3>Poznámka</h3>
            </div>
            <?
            $sql="
              select 
                poznamka
              from
                postavy_poznamka
              where
                id_uzivatele=$_SESSION[id_uzivatele]
                and rok=".var_getvalue_sn('rok').";
            ";
            $result=dbQuery($db_jmeno,$sql,$db_spojeni);
            if (mysql_num_rows($result) > 0){
              $poznamka=mysql_result($result,0,0);
            }
            ?>
            <div class=postavy_radek>
              <div class="postavy_textarea">
                <textarea name="poznamka"><?echo @$poznamka ?></textarea>
              </div>
            </div>
            
            <input type="image" src="files/styly/styl-aktualni/tlacitka/odeslat.gif" value="Submit" alt="odeslat" style="position: relative; top: 8px;">
          
          </form>
          <?
          }
          ?>
        </form>
        <?
        echo "<h2>Výpis družin (informativní)</h2>";
        $sql="select id_druziny, blok
          from drd_druziny
          where rok=".ROK."
          order by id_druziny asc";
        $result=dbQuery($sql);
        if(mysql_num_rows($result)>0)
        {
          while($zaznam=mysql_fetch_row($result))
          {
            echo "
              <div style=\"margin-top: 20px;\">
                <h3 style=\"float: left; margin-top: 0px; padding-top: 0px;\">č. ".($zaznam[0]-DRD_POSUN)." - ".druzina_jmeno($zaznam[0])."</h3> <div style=\"float: right\"><a href=\"členové\" onclick=\"ukaz($zaznam[0]); return false;\">členové</a></div>
                <div style=\"clear: both\">
                  <strong>Správce:</strong> ".druzina_spravce($zaznam[0])."<br />
                  <strong>Herní blok: </strong>".dekoduj_blok($zaznam[1])."<br />
                  <strong>Pán Jeskyně: </strong>".druzina_pj($zaznam[0])."<br />
                  <strong>Poznámka: </strong>".nl2br(druzina_poznamka($zaznam[0]))."<br />";
                  echo '<span id="objekt'.$zaznam[0].'" style="display: none;">';
                  druzina_clenove($zaznam[0]);
              echo "</span>";
            ?>
            <form action="<?echo $_SERVER["REQUEST_URI"]?>" method="post">
              <input type="hidden" name="akce" value="podat_prihlasku">
              <input type="hidden" name="id_druziny" value="<?echo $zaznam[0]?>">
              <span id="objekt<?echo "100$zaznam[0]"?>" style="display: none;">
              <br /><strong>Vzkaz pro správce družiny</strong>
              <div class="registrace_textarea"><textarea style="width: 450px; height: 60px;" name="vzkaz_spravce"></textarea></div>
              <div class="buttonky" style="padding-left: 200px;"><input type="image" src="files/styly/styl-aktualni/tlacitka/odeslat.gif" value="Submit" alt="odeslat" style="width: 88px; height: 26px; margin-top: 10px;"></div></span>
            </form>
            <?
            echo "</div></div>";
          }
        }
        else {
          echo "<ul><li>Ještě není založena žádná družina</li></ul>";
        }
        
        
      }
      
      
      //nemam druzinu
      //{
      //-> zalozit - OK
      //-> vypis mych podanych zadosti (zrusit)
      //-> vypis druzin (s moznosti prihlasit)
      //}
      //
      //mam druzinu
      //{
      //->spravce->vypis clenu (vyhodit) - OK
      //->spravce->upravit informace - TODO
      //->spravce->pozvanky->prijmou/zrusit - OK
      //->spravce->vybrat PJe - OK
      //->nastaveni rasy a povolani - OK
      
      //}
      
  }
  else {
    ?>
    <form action="<?echo $_SERVER["REQUEST_URI"]?>" id="prihlasit_drd" method="post">
      <input type="hidden" name="akce" value="prihlasit_drd">
    </form>
    <?php
    echo 
      "<p>Na této stránce máte možnost přihlásit se na Mistrovství ČR v Dračím doupěti 2011. Kromě přihlášení samotného zde můžete založit novou družinu nebo se přihlásit do nějaké již existující.</p>
  <p>Letos máte, stejně jako loni, možnost zvolit si herní bok ve kterém chcete odehrát první část dobrodružství a také PJ pro svou první část.</p>
  <p>Podrobnější návod k přihlašování na Mistrovství v DrD najdete <a href=\"drd/navod-na-prihlaseni\">na této stránce</a>.</p>
      <ul>
        <li>
          <strong><a href=\"javascript: document.getElementById('prihlasit_drd').submit()\">Přihlásit se na Mistrovství v DrD</a></strong>
        </li>
      </ul>
      ";
  }
}
else
{
  echo "
  <p>Na této stránce máte možnost přihlásit se na Mistrovství ČR v Dračím doupěti 2011. Kromě přihlášení samotného zde můžete založit novou družinu nebo se přihlásit do nějaké již existující.</p>
  <p>Letos máte, stejne jako loni, možnost zvolit si herní bok ve kterém chcete odehrát první část dobrodružství a také PJ pro svou první část.</p>
  <p>Podrobnější návod k přihlašování na Mistrovství v DrD najdete <a href=\"mistrovstvi-v-drd/navod---prihlaseni-na-drd\">na této stránce</a>.</p>
  <p>Pro přihlášení na <strong>Mistrovství v DrD</strong> musíš být registrovaný na webu GC a zároveň mít podanou <a href=\"gamecon/prihlaska\">přihlášku na GameCon 2011</a>.</p>";
}


?>
