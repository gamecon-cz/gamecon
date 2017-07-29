<?php

/**
 * Stránka s linky na reporty
 *
 * Reporty jsou obecně neoptimalizovaný kód (cyklické db dotazy apod.), nepočítá
 * se s jejich časově kritickým použitím.
 *
 * nazev: Reporty
 * pravo: 104
 */

$reporty = [
  ['aktivity',          'Historie přihlášení na aktivity',        ['csv', 'html']],
  //['neplatici',         'Neplatiči letos'], // duplikuje bfgr a finance, nemá smysl udržovat
  //['spolupracovnici',   'Spolupracovníci (orgové, info, zázemí, vypravěči+aktivity)'], // neudržované, asi lze zjistit z bfgr
  ['pocty-her',         'Účastníci a počty jejich aktivit'],
  ['pocty-her-graf',    'Graf rozložení rozmanitosti her',        ['html']],
  ['rozesilani-ankety', 'Rozesílání ankety s tokenem',            ['html']],
  ['parovani-ankety',   'Párování ankety a údajů uživatelů',      ['html']],
  ['grafy-ankety',      'Grafy k anketě',                         ['html']],
  ['update-zustatku',   'UPDATE příkaz zůstatků pro letošní GC',  ['html']],
  ['ubytovani',         'Ubytování',                              ['csv', 'html']],
  //['celkova-ucast',     'Celková účast'], // už dlouho nefunkční, asi přehled účasti po letech
  ['neprihlaseni-vypraveci', 'Nepřihlášení a neubytovaní vypravěči', ['html']],
  ['duplicity',         'Duplicitní uživatelé',                   ['html']],
  ['stravenky',         'Stravenky uživatelů',                    ['html']],
  ['stravenky?ciste',   'Stravenky (bianco)',                     ['html']],
  ['programove-reporty', 'Programový report (2015)', ['csv', 'html']],
  ['zaplnenost-programu-ucastniku', 'Zaplněnost programu účastníků (2015)', ['csv', 'html']],
  ['maily-prihlaseni',  'Maily – přihlášení na GC (vč. unsubscribed)', ['csv', 'html']],
  ['maily-neprihlaseni','Maily – nepřihlášení na GC',             ['csv', 'html']],
  ['maily-vypraveci',   'Maily – vypravěči (vč. unsubscribed)',   ['csv', 'html']],
  ['maily-vsichni',     'Maily – všichni',                        ['csv', 'html']],
  ['celkovy-report',    '<br>Celkový report '.ROK.'<br><br>',     ['csv', 'html']],
];

$t = new XTemplate('reporty.xtpl');

foreach($reporty as $r) {
  $kontext = [
    'nazev' =>  $r[1],
    'html'  =>  '',
    'csv'   =>  '',
  ];
  if(!isset($r[2]) || $r[2][0] == 'csv') {
    $kontext['csv'] = '<a href="reporty/'.$r[0].'">csv</a>';
  }
  if(isset($r[2])) {
    if($r[2][0] == 'html') {
      $kontext['html'] = '<a href="reporty/'.$r[0].'" target="_blank">html</a>';
    } else {
      $kontext['html'] = '<a href="reporty/'.$r[0].'?format=html" target="_blank">html</a>';
    }
  }
  $t->assign($kontext);
  $t->parse('reporty.report');
}

foreach(dbIterator('SELECT * FROM reporty') as $r) {
  $t->assign($r);
  $t->parse('reporty.quick');
}

$t->parse('reporty');
$t->out('reporty');
