<?php

$this->bezDekorace(true);

Aktivita::prihlasovatkoZpracuj($u);
Aktivita::vyberTeamuZpracuj($u);
Tym::vypisZpracuj($u);


// Přesměrování na kanonickou URL pokud existuje pro daný dotaz
if(get('rok') == ROK) unset($_GET['rok']);
if(array_keys($_GET) == array('req', 'typ') && $_GET['typ']) {
  back($_GET['typ']);
}

// převedení url parametrů do GET, aby se sjednotilo načítání filtrů
if($this->param('typ')) $_GET['typ'] = $_GET['req']; // TODO inteligentnější načítání z názvu typu
if($this->param('org')) $_GET['org'] = $_GET['req']; // TODO inteligentnější načítání z názvu typu

// Nabídka filtrů
$filtry = array();
$filtry[] = array(
  'nazev'     =>  'Typ',
  'name'      =>  'typ',
  'moznosti'  =>  Menu::$linie,
);
$roky = array(
  'nazev'     =>  'Rok',
  'name'      =>  'rok',
  'moznosti'  =>  array(),
);
for($i = ROK; $i >= 2009; $i--) {
  $roky['moznosti'][$i] = $i;
}
$filtry[] = $roky;
$filtry[] = array(
  'nazev' =>  'Obsazenost',
  'name'  =>  'jenVolne',
  'moznosti'  =>  array(
    '1'  =>  'jen volné',
    ''  =>  '(jakákoli)',
  )
);

// Zobrazení filtrovacího boxu
foreach($filtry as $filtr) {
  foreach($filtr['moznosti'] as $value => $moznost) {
    $t->assign('value', $value);
    $t->assign('nazev', $moznost);
    $t->assign('selected', get($filtr['name']) == $value ? 'selected' : '');
    $t->parse('aktivity.filtr.moznost');
  }
  $t->assign($filtr);
  $t->parse('aktivity.filtr');
}

// Vyfiltrování aktivit
$filtr = array();
$filtr['rok'] = get('rok') ?: ROK;
if($this->param('typ')) $filtr['typ'] = $this->param('typ')->id();
elseif(get('typ') && ($typ = Typ::zUrl(get('typ')))) $filtr['typ'] = $typ->id();
if($this->param('org')) $filtr['organizator'] = $this->param('org')->id();
if(get('jenVolne')) $filtr['jenVolne'] = true;
$filtr['jenViditelne'] = true;
$aktivity = Aktivita::zFiltru($filtr, ['nazev_akce', 'zacatek']);

// Statické stránky
// TODO hack
// TODO nejasné načítání typu
$stranky = [];
$prefixy = ['drd', 'legendy'];
if(isset($typ) && $typ && in_array($typ->url(), $prefixy))
  $stranky = Stranka::zUrlPrefixu($typ->url());
$t->parseEach($stranky, 'stranka', 'aktivity.stranka');

// Zobrazení aktivit
$a = reset($aktivity);
$dalsi = next($aktivity);
while($a) {

  //TODO hack přeskočení drd a lkd druhých kol
  if(($a->typ() == 8 || $a->typ() == 9) && $a->cenaZaklad() == 0) {
    $a = $dalsi;
    $dalsi = next($aktivity);
    continue;
  }

  // vlastnosti per termín
  $t->assign(array(
    'a'             =>  $a,
    'prihlasovatko' =>  $a->prihlasovatko($u),
    //TODO ne/přihlašovatelnost odlišená vzhledem (třídou?) termínu aktivity
    //TODO ajax na zobrazení bubliny ne/úspěšného přihlášení
  ));
  if( $v = $a->vyberTeamu($u) ) {
    $t->assign('vyber', $v);
    $t->parse('aktivity.aktivita.tymTermin.vyber');
  }
  if($a->teamova()) {
    if($tym = $a->tym()) {
      $t->assign('tym', $tym);
      $t->parse('aktivity.aktivita.tymTermin.tym');
      if($u && $a->prihlasen($u)) {
        $t->parse('aktivity.aktivita.tymTermin.vypis');
      }
    }
    $t->parse('aktivity.aktivita.tymTermin');
  } else {
    $t->parse('aktivity.aktivita.termin');
  }

  // vlastnosti per skupina (hack)
  if(!$dalsi || !$dalsi->patriPod() || $dalsi->patriPod() != $a->patriPod()) {
    if(CENY_VIDITELNE) {
      $do = new DateTime(SLEVA_DO);
      $t->assign(array(
        'cena' => $a->cena($u),
        'stdCena' => $a->cena(),
        'zakladniCena' => $a->cenaZaklad().'&thinsp;Kč',
        'rozhodneDatum' => $do->format('j.n.'),
        //TODO způsob načtení a zobrazení orgů (per termín, per aktivita, proklik na jejich osobní stránku, ...?)
        //TODO optimalizace načítání popisků (do jiné tabulky, jeden dotaz, pokud bude výkonnostně problém)
      ));
      if($a->bezSlevy())                $t->parse('aktivity.aktivita.fixniCena');
      elseif($u && $u->gcPrihlasen())   $t->parse('aktivity.aktivita.mojeCena');
      else                              $t->parse('aktivity.aktivita.cena');
    }
    foreach($a->tagy() as $tag) {
      $t->assign('tag', $tag);
      $t->parse('aktivity.aktivita.tag');
    }
    $popis = $a->popis();
    if(strlen($popis) > 370) $t->parse('aktivity.aktivita.vice');
    if(!$a->teamova()) $t->parse('aktivity.aktivita.organizatori');
    $t->assign('extra', $a->typ() == 9 ? 'drd' : '');
    $t->assign('popis', $popis);
    $t->parse('aktivity.aktivita');
  }

  // bižuterie pro běh cyklu
  $a = $dalsi;
  $dalsi = next($aktivity);

}

// záhlaví - vypravěč
if(isset($org)) {
  $t->assign([
    'jmeno'   =>  $org->jmenoNick(),
    'oSobe'   =>  $org->oSobe() ?: '<p><em>popisek od vypravěče nemáme</em></p>',
    'profil'  =>  $org->drdProfil(),
    'fotka'   =>  $org->fotkaAuto()->kvalita(85)->pokryjOrez(300,300),
  ]);
  if($org->oSobe()) $t->parse('aktivity.zahlavi.vypravec.viceLink');
  if($org->drdProfil()) {
    $t->parse('aktivity.zahlavi.vypravec.profilLink');
    $t->parse('aktivity.zahlavi.vypravec.profil');
  }
  $t->parse('aktivity.zahlavi.vypravec');
  $t->parse('aktivity.zahlavi');
}

// záhlaví - typ
if(isset($typ)) {
  $t->assign('text', $typ->oTypu());
  $t->parse('aktivity.zahlavi');
}
