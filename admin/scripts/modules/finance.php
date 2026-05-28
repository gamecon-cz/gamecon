<?

/** 
 * Rychlé finanční transakce (obsolete) (starý kód)
 *
 * nazev: Finance
 * pravo: 108
 */


//inicializace proměnných pro starý kód, kde se počítá s implicitní nulou
$db_jmeno=null;
$db_spojeni=null;
$_POST['akce']=isset($_POST['akce'])?$_POST['akce']:null;
$_POST['penize_pripsat']=isset($_POST['penize_pripsat'])?$_POST['penize_pripsat']:null;


if ($_POST["penize_pripsat"] == 1){
  $sql="
    select
      id_uzivatele,
      jmeno_uzivatele,
      prijmeni_uzivatele,
      login_uzivatele
    from
      uzivatele_hodnoty
    where
      id_uzivatele=$_POST[gc_id]
  ";
  $result=dbQuery($db_jmeno,$sql,$db_spojeni);
  if (mysql_num_rows($result)>0){
    $id_uzivatele=mysql_result($result,0,0);
    $jmeno_uzivatele=mysql_result($result,0,1);
    $prijmeni_uzivatele=mysql_result($result,0,2);
    $login_uzivatele=mysql_result($result,0,3);
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
    
    echo("
      <strong>Info</strong><br />
      Uživateli s GCID $_POST[gc_id] ($login_uzivatele, $jmeno_uzivatele $prijmeni_uzivatele) bylo připsáno $_POST[castka] GameCorun.<br />
      <br /><strong>Finanční historie uživatele</strong><br />");
    echo financeHistorieVypis($id_uzivatele);    
    }
  }
  else {
    echo "Žádný uživatel s tímto GCID ($_POST[gc_id] neexistuje)!";
  }

}
?>

<h1>Finance</h1>
<form action="<?echo $_SERVER['REQUEST_URI'];?>" method="post" name="penize_pripsat">
  <input type="hidden" name="penize_pripsat" value="1">
  <strong>ID:</strong> <input type="text" name="gc_id"><br>
  <strong>Částka:</strong> <input type="text" name="castka"><br>
  <strong>se slevou:</strong> <input type="checkbox" name="sleva" value="ano" /><br />
  <strong>Poznámka:</strong><br />
  <textarea name="poznamka"></textarea><br />
  <input type="submit" value="Připsat částku" />
</form>
<?
if ($_POST["akce"] == "rychloubytovani"){
  echo "<div class=\"adm_box\">";
  $sql="
    select
      id_uzivatele
    from
      uzivatele_hodnoty
    where
      id_uzivatele=$_POST[gc_id]
  ";
  $result=dbQuery($db_jmeno,$sql,$db_spojeni);
  if (mysql_num_rows($result) == 1){
    $id_ubytovavaneho=mysql_result($result,0,0);
    $chyba=false;
  }
  else {
    $chyba=true;
    echo "<strong>CHYBA - špatné GC ID!</strong>";
  }

  if ($chyba == false){
    $sql="
    update
      prihlaska_ostatni
    set
      pokoj=$_POST[cislo_pokoje]
    where
      id_uzivatele=$id_ubytovavaneho
      and rok=".var_getvalue_sn('rok')."
    ";
    dbQuery($db_jmeno,$sql,$db_spojeni);
    echo "<strong>Číslo pokoje přiřazeno</strong>"; 
  }  
  echo "</div>";                                                
}

?>


<div class="adm_box">
    <h3>Rychloubytování</h3>
    <form  method="post">
      <input type="hidden" name="akce" value="rychloubytovani" />
      <table class="invisible_table">
        <tr>
          <td><strong>ID:</strong></td><td>
            <input type="text" name="gc_id" />
          </td>
        </tr>
        <tr>
          <td><strong>číslo pokoje:</strong></td><td>
            <input type="text" name="cislo_pokoje" />
          </td>
        </tr>
        <tr style="background:none">
          <td colspan="2"><input type="submit" value="Přidělit číslo pokoje" /></td>
        </tr>
      </table>
    </form>
    </div>
  <br /><br /><a href="/finance">zpět</a>  


  