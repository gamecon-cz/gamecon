<?php

/**
 * Rychlé finanční transakce (obsolete) (starý kód)
 *
 * nazev: Finance
 * pravo: 108
 */

if(post('uzivatelProPripsaniSlevy')) {
  $uzivatel = Uzivatel::zId(post('uzivatelProPripsaniSlevy'));
  if(!$uzivatel) chyba(sprintf('Uživatel %d neexistuje.', post('uzivatelProPripsaniSlevy')));
  if(!post('sleva')) chyba('Zadej slevu.');
  if(!$uzivatel->gcPrihlasen()) chyba(sprintf('Uživatel %s není přihlášen na GameCon.', $uzivatel->jmenoNick()));
  $uzivatel->finance()->pripisSlevu(
    post('sleva'),
    post('poznamkaKUzivateliProPripsaniSlevy'),
    $u
  );
  $numberFormatter = NumberFormatter::create('cs', NumberFormatter::PATTERN_DECIMAL);
  oznameni(sprintf('Sleva %s připsána k uživateli %s.', $numberFormatter->formatCurrency(post('sleva'), 'CZK'), $uzivatel->jmenoNick()));
} else if (post('uzivatelKVyplaceniAktivity')) {
  $uzivatel = Uzivatel::zId(post('uzivatelKVyplaceniAktivity'));
  if(!$uzivatel) chyba(sprintf('Uživatel %d neexistuje.', post('uzivatelKVyplaceniAktivity')));
  if(!$uzivatel->gcPrihlasen()) chyba(sprintf('Uživatel %s není přihlášen na GameCon.', $uzivatel->jmenoNick()));
  $shop = new Shop($uzivatel);
  $prevedenaCastka = $shop->kupPrevodBonusuNaPenize();
  if (!$prevedenaCastka) {
    chyba(sprintf('Uživatel %s nemá žádný bonus k převodu.', $uzivatel->jmenoNick()));
  }
  $uzivatel->finance()->pripis(
    $prevedenaCastka,
    $u,
    post('poznamkaKVyplaceniBonusu')
  );
  $numberFormatter = NumberFormatter::create('cs', NumberFormatter::PATTERN_DECIMAL);
  oznameni(sprintf('Bonus %s vyplacen uživateli %s.', $numberFormatter->formatCurrency($prevedenaCastka, 'CZK'), $uzivatel->jmenoNick()));
}

if (get('ajax') === 'uzivatel-k-vyplaceni-aktivity') {
  $organizatoriAkciQuery=dbQuery(<<<SQL
SELECT uzivatele_hodnoty.*
FROM uzivatele_hodnoty
JOIN r_uzivatele_zidle
    ON r_uzivatele_zidle.id_uzivatele = uzivatele_hodnoty.id_uzivatele AND r_uzivatele_zidle.id_zidle IN($1, $2)
GROUP BY uzivatele_hodnoty.id_uzivatele
SQL
    , [Z_ORG_AKCI, Z_PRIHLASEN] // při změně změn hint v šabloně finance.xtpl
  );
  $numberFormatter = NumberFormatter::create('cs', NumberFormatter::PATTERN_DECIMAL);
  $organizatorAkciData = [];
  while($organizatorAkciRadek=mysqli_fetch_assoc($organizatoriAkciQuery)) {
    $organizatorAkci = new Uzivatel($organizatorAkciRadek);
    $nevyuzityBonusZaAktivity = $organizatorAkci->finance()->nevyuzityBonusZaAktivity();
    if (!$nevyuzityBonusZaAktivity) {
      continue;
    }
    $organizatorAkciData[] = [
      'id' => $organizatorAkci->id(),
      'jmeno' => $organizatorAkci->jmenoNick(),
      'nevyuzityBonusZaAktivity' => $numberFormatter->formatCurrency($nevyuzityBonusZaAktivity, 'CZK'),
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

$x->assign('rok', ROK);

$x->parse('finance');
$x->out('finance');
