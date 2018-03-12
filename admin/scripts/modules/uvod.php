<?php

/**
 * Úvodní stránka sloužící pro infopult a další účely. Zajišťuje registraci na
 * DrD, Trojboj, Gamecon, Placení aj.
 *
 * nazev: Úvod
 * pravo: 100
 */

if(!empty($_POST['datMaterialy']) && $uPracovni && $uPracovni->gcPrihlasen())
{
  $uPracovni->dejZidli(Z_PRITOMEN);
  back();
}

if(post('platba') && $uPracovni && $uPracovni->gcPrihlasen())
{
  $uPracovni->finance()->pripis(post('platba'), $u, post('poznamka'));
  back();
}

if(($castka = post('sleva')) && $uPracovni && $uPracovni->gcPrihlasen()) {
  $uPracovni->finance()->pripisSlevu($castka, post('poznamka'), $u);
  back();
}

if(!empty($_POST['gcPrihlas']) && $uPracovni && !$uPracovni->gcPrihlasen())
{
  $uPracovni->gcPrihlas();
  back();
}

if(!empty($_POST['rychloreg']))
{
  $tab = $_POST['rychloreg'];
  if(empty($tab['login_uzivatele'])) $tab['login_uzivatele'] = $tab['email1_uzivatele'];
  $tab['nechce_maily'] = isset($tab['nechce_maily']) ? dbNow() : null;
  try {
    $nid = Uzivatel::rychloreg($tab, [
      'informovat'  =>  post('informovat'),
    ]);
  } catch(DuplicitniEmailException $e) {
    throw new Chyba('Uživatel s zadaným e-mailem už v databázi existuje');
  } catch(DuplicitniLoginException $e) {
    throw new Chyba('Uživatel s loginem odpovídajícím zadanému e-mailu už v databázi existuje');
  }
  if($nid)
  {
    if($uPracovni) Uzivatel::odhlasKlic('uzivatel_pracovni');
    $_SESSION["id_uzivatele"] = $nid;
    $uPracovni = Uzivatel::prihlasId($nid,'uzivatel_pracovni');
    if(!empty($_POST['vcetnePrihlaseni'])) $uPracovni->gcPrihlas();
    back();
  }
}

if(!empty($_POST['telefon']) && $uPracovni)
{
  dbQueryS('UPDATE uzivatele_hodnoty SET telefon_uzivatele=$0 WHERE id_uzivatele='.$uPracovni->id(),[$_POST['telefon']]);
  $uPracovni->otoc();
  back();
}

if(!empty($_POST['prodej']))
{
  $prodej=$_POST['prodej'];
  unset($prodej['odeslano']);
  if($uPracovni)
    $prodej['id_uzivatele']=$uPracovni->id();
  if(!$prodej['id_uzivatele'])
    $prodej['id_uzivatele']=0;
  dbQuery('INSERT INTO shop_nakupy(id_uzivatele,id_predmetu,rok,cena_nakupni,datum) 
    VALUES ('.$prodej['id_uzivatele'].','.$prodej['id_predmetu'].','.ROK.',(SELECT cena_aktualni FROM shop_predmety WHERE id_predmetu='.$prodej['id_predmetu'].'),NOW())');
  back();
}

if(!empty($_POST['gcOdhlas']) && $uPracovni && !$uPracovni->gcPritomen())
{
  $uPracovni->gcOdhlas();
  back();
}

if(post('gcOdjed')) {
  $uPracovni->gcOdjed();
  back();
}

if(post('poznamkaNastav')) {
  $uPracovni->poznamka(post('poznamka'));
  back();
}

if(post('zmenitUdaj')) {
  try {
    dbUpdate('uzivatele_hodnoty', post('udaj'), ['id_uzivatele' => $uPracovni->id()]);
  } catch (DbDuplicateEntryException $e) {
    throw new Chyba('Uživatel se stejnou přezdívkou již existuje.');
  }
  $uPracovni->otoc();
  back();
}


$x=new XTemplate('uvod.xtpl');
if($uPracovni && $uPracovni->gcPrihlasen())
{
  $up=$uPracovni;
  $x->assign('ok', $ok='<img src="files/design/ok-s.png" style="margin-bottom:-2px">');
  $x->assign('err',$err='<img src="files/design/error-s.png" style="margin-bottom:-2px">');
  $a=$up->koncA();
  $pokoj = Pokoj::zUzivatele($up);
  $spolubydlici = $pokoj ? $pokoj->ubytovani() : [];
  $x->assign([
    'a'               =>  $up->koncA(),
    'stav'            =>  ($up->finance()->stav()<0 ? $err : $ok ).' '.$up->finance()->stavHr(),
    'prehled'         =>  $up->finance()->prehledHtml(),
    'slevyAktivity'   =>  ($akt=$up->finance()->slevyAktivity()) ?
      '<li>'.implode('<li>',$akt) :
      '(žádné)',
    'slevyVse'        =>  ($vse=$up->finance()->slevyVse()) ?
      '<li>'.implode('<li>',$vse) :
      '(žádné)',
    'id'              =>  $up->id(),
    'pokoj'           =>  $pokoj ? $pokoj->cislo() : '(nepřidělen)',
    'spolubydlici'    =>  array_uprint($spolubydlici, function($e){ return "<li> {$e->jmenoNick()} ({$e->id()}) {$e->telefon()} </li>"; }),
    'aa'              =>  $u->koncA(),
    'org'             =>  $u->jmenoNick(),
    'up'              =>  $up,
  ]);
  if($up->finance()->stav() < 0 && !$up->gcPritomen()) $x->parse('uvod.uzivatel.nepritomen.upoMaterialy');
  if(!$up->gcPritomen())    $x->parse('uvod.uzivatel.nepritomen');
  elseif(!$up->gcOdjel())   $x->parse('uvod.uzivatel.pritomen');
  else                      $x->parse('uvod.uzivatel.odjel');
  if(!$up->gcPritomen())    $x->parse('uvod.uzivatel.gcOdhlas');
  if(GC_BEZI && (!$up->gcPritomen() || $up->finance()->stav() < 0)) $x->parse('uvod.potvrditZruseniPrace');
  if($u->maPravo(P_PRIPSANI_SLEVY)) $x->parse('uvod.uzivatel.pripsatSlevu');
  $x->parse('uvod.uzivatel');
  $x->parse('uvod.slevy');
  $x->parse('uvod.objednavky');
}
else if($uPracovni && !$uPracovni->gcPrihlasen()) // kvůli zkratovému vyhodnocení a nevolání metody na non-object
{
  $x->assign([
    'a'   =>  $uPracovni->koncA(),
    'ka'  =>  $uPracovni->koncA() ? 'ka' : '',
    'rok' =>  ROK
  ]);
  $x->parse('uvod.neprihlasen');
}
else
  $x->parse('uvod.neUzivatel');

// načtení předmětů a form s rychloprodejem předmětů, fixme
$o=dbQuery('
  SELECT 
    CONCAT(nazev," ",model_rok) as nazev,
    kusu_vyrobeno-count(n.id_predmetu) as zbyva,
    p.id_predmetu,
    ROUND(p.cena_aktualni) as cena 
  FROM shop_predmety p 
  LEFT JOIN shop_nakupy n ON(n.id_predmetu=p.id_predmetu)
  WHERE p.stav > 0
  GROUP BY p.id_predmetu
  ORDER BY model_rok DESC, nazev');
$moznosti='<option value="0">(vyber)</option>';
while($r=mysqli_fetch_assoc($o)) {
  $zbyva = $r['zbyva'] === null ? '&infin;' : $r['zbyva'];
  $moznosti.='<option value="'.$r['id_predmetu'].'"'.($r['zbyva']>0||$r['zbyva']===null?'':' disabled').'>'.$r['nazev'].' ('.$zbyva.') '.$r['cena'].'&thinsp;Kč</option>';
}
$x->assign('predmety',$moznosti);

// form s osobními údaji
if($uPracovni) {
  $udaje = [
    'login_uzivatele'       =>  'Přezdívka',
    'jmeno_uzivatele'       =>  'Jméno',
    'prijmeni_uzivatele'    =>  'Příjmení',
    'ulice_a_cp_uzivatele'  =>  'Ulice',
    'mesto_uzivatele'       =>  'Město',
    'psc_uzivatele'         =>  'PSČ',
    'telefon_uzivatele'     =>  'Telefon',
    'datum_narozeni'        =>  'Narozen'.$uPracovni->koncA(),
    'email1_uzivatele'      =>  'E-mail',
    'poznamka'              =>  'Poznámka',
    'op'                    =>  'Číslo OP',
  ];
  $r = dbOneLine('SELECT '.implode(',', array_keys($udaje)).' FROM uzivatele_hodnoty WHERE id_uzivatele = '.$uPracovni->id());
  foreach($udaje as $sloupec => $nazev) {
    $hodnota = $r[$sloupec];
    $x->assign([
      'nazev' => $nazev,
      'sloupec' => $sloupec,
      'hodnota' => $hodnota,
    ]);
    if($hodnota == '' && $sloupec != 'poznamka') $x->parse('uvod.udaje.udaj.chybi');
    if($sloupec != 'poznamka') $x->parse('uvod.udaje.udaj.input');
    else $x->parse('uvod.udaje.udaj.text');
    $x->parse('uvod.udaje.udaj');
  }
  $x->parse('uvod.udaje');
}

$x->parse('uvod');
$x->out('uvod');
