<?php

$this->bezDekorace(true);

Aktivita::prihlasovatkoZpracuj($u);
Aktivita::vyberTeamuZpracuj($u);


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
$aktivity = Aktivita::zFiltru($filtr, array('nazev_akce'));

// Zobrazení aktivit
$a = reset($aktivity);
$dalsi = next($aktivity);
while($a) {

  // vlastnosti per termín
  $t->assign(array(
    'a'             =>  $a,
    'prihlasovatko' =>  $a->prihlasovatko($u),
    //TODO ne/přihlašovatelnost odlišená vzhledem (třídou?) termínu aktivity
    //TODO ajax na zobrazení bubliny ne/úspěšného přihlášení
  ));
  if( $v = $a->vyberTeamu($u) ) {
    $t->assign('vyber', $v);
    $t->parse('aktivity.aktivita.vyberTeamu');
  }
  $t->parse('aktivity.aktivita.termin');

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
    $t->assign('popis', $popis);
    $t->parse('aktivity.aktivita');
  }

  // bižuterie pro běh cyklu
  $a = $dalsi;
  $dalsi = next($aktivity);

}

// záhlaví
if(isset($org)) {
  $t->assign('text', $org->jmenoNick());
  $t->parse('aktivity.zahlavi');
}

if(isset($typ)) {
  $t->assign('text', $typ->oTypu());
  $t->parse('aktivity.zahlavi');
}
