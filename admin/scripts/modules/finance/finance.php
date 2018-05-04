<?php

/**
 * Rychlé finanční transakce (obsolete) (starý kód)
 *
 * nazev: Finance
 * pravo: 108
 */

if(($castka = post('sleva')) && $uPracovni && $uPracovni->gcPrihlasen()) {
  $uPracovni->finance()->pripisSlevu($castka, post('poznamka'), $u);
  back();
}

$x=new XTemplate('finance.xtpl');
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
      ]);
      $x->parse('finance.uzivatele.uzivatel');
      $ids.=$un->id().',';
    }
  }
  $x->assign('minimum',$min);
  $x->assign('ids',substr($ids,0,-1));
  $ids ? $x->parse('finance.uzivatele') : $x->parse('finance.nikdo');
}

$x->assign([
  'id'              =>  $uPracovni ? $uPracovni->id() : null,
  'org'             =>  $u->jmenoNick(),
]);
$x->parse('finance.pripsatSlevu');
$x->parse('finance');
$x->out('finance');
