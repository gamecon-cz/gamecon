<?

/** 
 * Úpravy novinek na webu
 *
 * nazev: Web
 * pravo: 105
 */

if(!isset($_REQUEST['akce'])) $_REQUEST['akce']='novinky';
if(!isset($_POST["detail_akce"])) $_POST["detail_akce"]='';
$db_jmeno=$db_spojeni='';

function stav_novinky($stav){
  switch ($stav){
    case 'Y': return "Publikováno";
    break;
    case 'N': return "Nepublikovat";
    break;
    case 'P': return "V přípravě";
    break;
  }
}

function uzivatele_login($id_uzivatele){
global $db_jmeno,$db_spojeni;
  $sql="
    select
      login_uzivatele
    from
      uzivatele_hodnoty
    where
      id_uzivatele=$id_uzivatele
  ";
  $result=dbQuery($db_jmeno,$sql,$db_spojeni);
  $login=mysql_result($result,0,0);
  if (empty($login)){
    $login="<em>žádný</em>";
  }
  return $login;
}

function novinky_na_web($na_stranku){
global $db_jmeno,$db_spojeni;
  $sql="
  select
    obsah
  from
    novinky_obsah
  where
    stav='Y'
    and id_novinky > 9
  order by
    publikovano desc
  ";
  //--limit 0,$na_stranku
  $novinka="<h1>Novinky</h1>";
  $result=dbQuery($db_jmeno,$sql,$db_spojeni);
  while ($zaznam=mysql_fetch_row($result)){
    $novinka .= $zaznam[0];
  }
  $novinka=str_replace("'","''",$novinka);
  $novinka .= '<p><a href="/novinky/archiv-novinek">Archiv starších novinek</a></p>';
  
  //Nějaká hrůza z dřívějška (vím jaká, a proto to sem nemá cenu psát, neměňte to, celé to přepište)
  /*
  $sql="
  update
    stranky_obsah
  set
    obsah_stranky='$novinka'
  where
    id_stranky=283;
  ";
  dbQuery($db_jmeno,$sql,$db_spojeni);
  //echo $novinka;
  */
}

?>



<?

//Editace obsahu stránek
if($_REQUEST['akce']=='editaceStranek')
  require_once('editace-stranek.php');

/////////////////////////////////////
//           NOVINKY               //
/////////////////////////////////////
if (($_REQUEST["akce"] == "novinky") and (empty($_REQUEST["detail_akce"]))){
  echo "<h2>Nový záznam</h2>";?>
  <p>
    <a href="javascript: document.getElementById('nova').submit()">Vložit novinku</a>
    <form id="nova"  method="post" /><input type="hidden" name="akce" value="novinky"><input type="hidden" name="detail_akce" value="nova"></form>
  </p>
  <?
  echo "<h2>Výpis novinek</h2><p>";
  $sql="
    select
        id_novinky
      , stav
      , autor
      , publikoval
      , date_format(publikovano,'%e.%m.%Y')
      , posledni_zmena
      , substring(obsah,1,250)
    from
      novinky_obsah
    order by
      id_novinky desc
  ";
  $result=dbQuery($db_jmeno,$sql,$db_spojeni);
  if (mysql_num_rows($result)){
    ?>
    <table style="width: 100%;">
      <tr>
        <th>Publikována</th>
        <th>Stav</th>
        <th>Autor</th>
        <th>Obsah</th>
        <th></th>
        <th></th>
      </tr>
    <?
    while ($zaznam=mysql_fetch_row($result)){
      if ($zaznam[1] == "Y"){
        $publikovano_radek="<td><strong>$zaznam[4]</strong></td>";
      }
      else {
        $publikovano_radek="<td><strong>není</strong></td>";
      } 
      echo "
        <tr>
          $publikovano_radek
          <td>".stav_novinky($zaznam[1])."</td>
          <td>".@uzivatele_login($zaznam[2])."</td>
          <td>".strip_tags($zaznam[6])."...</td>
          <td><a href=\"javascript: document.getElementById('uprav_$zaznam[0]').submit()\">upravit</a></td>
          <td><a href=\"javascript: document.getElementById('smaz_$zaznam[0]').submit()\">smazat</a></td>
          <form id=\"uprav_$zaznam[0]\"  method=\"post\" /><input type=\"hidden\" name=\"akce\" value=\"novinky\"><input type=\"hidden\" name=\"detail_akce\" value=\"upravit\"  /><input type=\"hidden\" name=\"cislo_novinky\" value=\"$zaznam[0]\" /></form>
          <form id=\"smaz_$zaznam[0]\"  method=\"post\" /><input type=\"hidden\" name=\"akce\" value=\"novinky\"><input type=\"hidden\" name=\"detail_akce\" value=\"smazat\"  /><input type=\"hidden\" name=\"cislo_novinky\" value=\"$zaznam[0]\" /></form>
      </tr>
      ";
    }
    echo "</table>";
  }
  else {
    echo "<strong>Není vytvořena žádná novinka.</strong>";
  }
  echo "</p>";
}

if (($_REQUEST["akce"] == "novinky") and ($_POST["detail_akce"] == "nova")){
  echo "<h2>Vytvoření novinky</h2>";
  ?>
  <form  method="post" /">
    <input type="hidden" name="akce" value="novinky" />
    <input type="hidden" name="detail_akce" value="nova_ulozit" />
    <strong>Stav novinky:</strong><br />
    <select name="stav">
      <option value="P">V přípravě</option>
      <option value="Y">Publikováno</option>
      <option value="N">Nepublikovat</option>
    </select>
    <br />
    <em>V přípravě</em> - novinka není na webu zobrazena, admini do ní mohou zasahovat<br />
    <em>Publikováno</em> - novinka je na webu<br />
    <em>Nepublikovat</em> - novinka není zobrazena, ani není určena k zobrazení (můžou se do ní ukládat průběžné informace atp.)<br />
    <br />
    <strong>Vlastní text novinky:</strong><br />
    <textarea name="obsah" style="width: 700px; height: 300px; scroll: auto;">Obsah novinky</textarea>
    <br />
    <input type="submit" value="Uložit novinku"> <br /><br />
  </form>  
    
    <strong>Vzor pro psaní novinek</strong><br />
      &lt;h2&gt;1.1.2010&lt;/h2&gt; - každá novinka musí mít uvedeno datum v &lt;h2&gt;<br />
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
      ('$_POST[stav]',$_SESSION[id_admin],$_SESSION[id_admin],NOW(),NOW(),$_SESSION[id_admin],'".addslashes($_POST['obsah'])."')
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
      &lt;h2&gt;1.1.2010&lt;/h2&gt; - každá novinka musí mít uvedeno datum v &lt;h2&gt;<br />
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
        , obsah='".addslashes($_POST['obsah'])."'
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
