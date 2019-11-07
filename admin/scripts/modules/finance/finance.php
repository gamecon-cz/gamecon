<?php

/**
 * Rychlé finanční transakce (obsolete) (starý kód)
 *
 * nazev: Finance
 * pravo: 108
 */

if(post('sleva')) {
  $uzivatel = Uzivatel::zId(post('uzivatel'));
  if(!$uzivatel) chyba('Uživatel neexistuje.');
  if(!$uzivatel->gcPrihlasen()) chyba('Uživatel není přihlášen na GameCon.');
  $uzivatel->finance()->pripisSlevu(post('sleva'), post('poznamka'), $u);
  oznameni('Sleva připsána.');
}

if (get('ajax') === 'uzivatel-k-vyplaceni-aktivity') {
  $organizatoriAkciQuery=dbQuery(<<<SQL
SELECT uzivatele_hodnoty.*
FROM uzivatele_hodnoty
JOIN r_uzivatele_zidle
    ON r_uzivatele_zidle.id_uzivatele = uzivatele_hodnoty.id_uzivatele AND r_uzivatele_zidle.id_zidle IN($1, $2)
GROUP BY uzivatele_hodnoty.id_uzivatele
SQL
    , [Z_ORG_AKCI, Z_PRIHLASEN]
  );
  $organizatorAkciData = [];
  while($organizatorAkciRadek=mysqli_fetch_assoc($organizatoriAkciQuery)) {
    $organizatorAkci = new Uzivatel($organizatorAkciRadek);
    $bonusZaAktivity = $organizatorAkci->finance()->slevaVypravecMax();
    if (!$bonusZaAktivity) {
      continue;
    }
    $organizatorAkciData[] = [
      'id' => $organizatorAkci->id(),
      'jmeno' => $organizatorAkci->jmenoNick(),
      'bonusZaAktivity' => $bonusZaAktivity,
    ];
  }

  header('Content-type: application/json');
  echo json_encode(
    $organizatorAkciData,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit();
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
$x->parse('finance.vyplatitBonusZaVedeniAktivity');
$x->parse('finance');
$x->out('finance');
