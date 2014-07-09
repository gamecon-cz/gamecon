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

if(!empty($_POST['platba']) && $uPracovni && $uPracovni->gcPrihlasen())
{
  Finance::pripis($uPracovni,$_POST['platba'],$_POST['poznamka'],$u);
  back();
}

if(!empty($_POST['zrusitNovacka']) && $uPracovni && $uPracovni->gcPrihlasen())
{
  dbQueryS('UPDATE uzivatele_hodnoty SET guru=NULL WHERE id_uzivatele=$0',array($_POST['zrusitNovacka']));
  back();
}

if(!empty($_POST['gcPrihlas']) && $uPracovni && !$uPracovni->gcPrihlasen())
{
  $uPracovni->gcPrihlas();
  back();
}

if(!empty($_POST['rychloreg']))
{
  $tab=$_POST['rychloreg'];
  if(empty($tab['login_uzivatele'])) $tab['login_uzivatele']=$tab['email1_uzivatele'];
  $nid=Uzivatel::rychloreg($tab);
  if($nid)
  {
    if($uPracovni) Uzivatel::odhlasKlic('uzivatel_pracovni');
    $_SESSION["id_uzivatele"]=$nid;
    $uPracovni=Uzivatel::prihlasId($nid,'uzivatel_pracovni');
    if(!empty($_POST['vcetnePrihlaseni'])) $uPracovni->gcPrihlas();
    back();
  }
}

if(!empty($_POST['telefon']) && $uPracovni)
{
  dbQueryS('UPDATE uzivatele_hodnoty SET telefon_uzivatele=$0 WHERE id_uzivatele='.$uPracovni->id(),array($_POST['telefon']));
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

$x=new XTemplate('uvod.xtpl');
if($uPracovni && $uPracovni->gcPrihlasen())
{
  $up=$uPracovni;
  $x->assign('ok', $ok='<img src="files/design/ok-s.png" style="margin-bottom:-2px">');
  $x->assign('err',$err='<img src="files/design/error-s.png" style="margin-bottom:-2px">');
  $a=$up->koncA();
  $novacci=array_map(function($n){
    global $ok, $err;
    $a=$n->koncA()?$n->koncA().',':',&ensp;';
    return 
      $n->jmenoNick()
      .'&emsp;<form method="post" class="radkovy"><input type="submit" value="zrušit"><input type="hidden" name="zrusitNovacka" value="'.$n->id().'"></form>'
      .'<ul><li>'
      .($n->gcPrihlasen() ? $ok.' přihlášen'.$a : $err.' nepřihlášen'.$a )
      .($n->gcPritomen()  ? $ok.' dorazil'.$a   : $err.' nedorazil'.$a )
      .($n->finance()->stav()<0 ? $err : $ok)
      .' '.$n->finance()->stavHr().''
      .'</ul></li>';
  },$up->novacci());
  $pokoj = Pokoj::zUzivatele($up);
  $spolubydlici = $pokoj ? $pokoj->ubytovani() : array();
  $x->assign(array(
    'a'               =>  $up->koncA(),
    'stav'            =>  ($up->finance()->stav()<0 ? $err : $ok ).' '.$up->finance()->stavHr(),
    'prehled'         =>  $up->finance()->prehledHtml(),
    'slevyAktivity'   =>  ($akt=$up->finance()->slevyAktivity()) ?
      '<li>'.implode('<li>',$akt) :
      '(žádné)',
    'slevyVse'        =>  ($vse=$up->finance()->slevyVse()) ?
      '<li>'.implode('<li>',$vse) :
      '(žádné)',
    'novacci'         =>  $novacci ?
      '<li>'.implode('<li>',$novacci) :
      '(žádní)',
    'potvrditZruseni' =>  $up->gcPritomen() && $up->finance()->stav()>=0 || !GAMECON_BEZI ? 'false' : 'true',
    'statut'          =>  
      $up->maPravo(P_ORG) ? '<span style="color:red;font-weight:bold">Organizátor</span>' : 
      ($up->maPravo(P_ORG_AKCI) ? '<span style="color:blue;font-weight:bold">Vypravěč</span>' : 
      'Účastník'),
    'telefon'         =>  $up->telefon(),
    'mail'            =>  $up->mail(),
    'id'              =>  $up->id(),
    'pokoj'           =>  $pokoj ? $pokoj->cislo() : '(nepřidělen)',
    'spolubydlici'    =>  array_uprint($spolubydlici, function($e){ return '<li>'.$e->jmenoNick().' ('.$e->id().')</li>'; }),
    'aa'              =>  $u->koncA(),
    'org'             =>  $u->jmenoNick(),
    'up'              =>  $up,
  ));
  if($up->finance()->stav() < 0 && !$up->gcPritomen()) $x->parse('uvod.uzivatel.nepritomen.upoMaterialy');
  if(!$up->gcPritomen())    $x->parse('uvod.uzivatel.nepritomen');
  elseif(!$up->gcOdjel())   $x->parse('uvod.uzivatel.pritomen');
  else                      $x->parse('uvod.uzivatel.odjel');
  if(!$up->telefon()) $x->parse('uvod.uzivatel.bezTelefonu');
  if(!$up->gcPritomen()) $x->parse('uvod.uzivatel.gcOdhlas');
  $x->parse('uvod.uzivatel');
}
else if($uPracovni && !$uPracovni->gcPrihlasen()) // kvůli zkratovému vyhodnocení a nevolání metody na non-object
{
  $x->assign(array(
    'a'   =>  $uPracovni->koncA(),
    'ka'  =>  $uPracovni->koncA() ? 'ka' : '',
    'rok' =>  ROK
  ));
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
while($r=mysql_fetch_assoc($o)) {
  $zbyva = $r['zbyva'] === null ? '&infin;' : $r['zbyva'];
  $moznosti.='<option value="'.$r['id_predmetu'].'"'.($r['zbyva']>0||$r['zbyva']===null?'':' disabled').'>'.$r['nazev'].' ('.$zbyva.') '.$r['cena'].'&thinsp;Kč</option>';
}
$x->assign('predmety',$moznosti);
$x->parse('uvod');
$x->out('uvod');

?>
