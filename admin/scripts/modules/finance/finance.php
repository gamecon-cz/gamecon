<?php

/**
 * Rychlé finanční transakce (obsolete) (starý kód)
 *
 * nazev: Finance
 * pravo: 108
 */

if(!empty($_POST['priznat']))
{
  $q='INSERT IGNORE INTO r_uzivatele_zidle(id_uzivatele,id_zidle) VALUES '; //pozor, insert ignore zabije i jiné chyby než duplikáty
  foreach(explode(',',$_POST['priznat']) as $uid)
  {
    $q.="\n".'('.(int)$uid.','.Z_VCAS.'),';
  }
  $q=substr($q,0,-1).';';
  dbQuery($q);
  back();
}

if(!empty($_POST['odebrat']))
{
  dbQuery('DELETE FROM r_uzivatele_zidle WHERE id_zidle='.Z_VCAS);
  back();
}



$x=new XTemplate('finance.xtpl');
SLEVA_AKTIVNI ? $x->parse('finance.slevaAno') : $x->parse('finance.slevaNe');
if(isset($_GET['minimum']))
{
  $min=(int)$_GET['minimum'];
  $o=dbQuery("SELECT u.* FROM uzivatele_hodnoty u JOIN r_uzivatele_zidle z ON(z.id_uzivatele=u.id_uzivatele AND z.id_zidle=".Z_PRIHLASEN.")");
  $ids='';
  while($r=mysqli_fetch_assoc($o))
  {
    $un=new Uzivatel($r);
    $un->nactiPrava();
    if(($stav=$un->finance()->stav()) >= $min)
    {
      $x->assign([
        'login' => $un->prezdivka(),
        'stav'  => $stav,
        'aktivity'  =>  $un->finance()->cenaAktivity(),
        'ubytovani' =>  $un->finance()->cenaUbytovani(),
        'predmety'  =>  $un->finance()->cenaPredmety(),
        'vcas'      =>  $un->maPravo(P_SLEVA_VCAS) ? '<img src="files/design/ok-s.png">' : '<img src="files/design/error-s.png">',
      ]);
      $x->parse('finance.uzivatele.uzivatel');
      $ids.=$un->id().',';
    }
  }
  $x->assign('minimum',$min);
  $x->assign('ids',substr($ids,0,-1));
  $ids ? $x->parse('finance.uzivatele') : $x->parse('finance.nikdo');
}
$x->parse('finance');
$x->out('finance');
