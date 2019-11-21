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
  ['aktivity',                                                      'Historie přihlášení na aktivity',                                ['csv', 'html']],
  ['pocty-her',                                                     'Účastníci a počty jejich aktivit'],
  ['pocty-her-graf',                                                'Graf rozložení rozmanitosti her',                                ['html']],
  ['rozesilani-ankety',                                             'Rozesílání ankety s tokenem',                                    ['html']],
  ['parovani-ankety',                                               'Párování ankety a údajů uživatelů',                              ['html']],
  ['grafy-ankety',                                                  'Grafy k anketě',                                                 ['html']],
  ['update-zustatku',                                               'UPDATE příkaz zůstatků pro letošní GC',                          ['html']],
  ['neprihlaseni-vypraveci',                                        'Nepřihlášení a neubytovaní vypravěči',                           ['html']],
  ['duplicity',                                                     'Duplicitní uživatelé',                                           ['html']],
  ['stravenky',                                                     'Stravenky uživatelů',                                            ['html']],
  ['stravenky?ciste',                                               'Stravenky (bianco)',                                             ['html']],
  ['programove-reporty',                                            'Programový report (2015)',                                       ['csv', 'html']],
  ['zaplnenost-programu-ucastniku',                                 'Zaplněnost programu účastníků (2015)',                           ['csv', 'html']],
  // MAILY
  ['maily-prihlaseni',                                              'Maily – přihlášení na GC (vč. unsubscribed)',                    ['csv', 'html']],
  ['maily-neprihlaseni',                                            'Maily – nepřihlášení na GC',                                     ['csv', 'html']],
  ['maily-vypraveci',                                               'Maily – vypravěči (vč. unsubscribed)',                           ['csv', 'html']],
  ['maily-dle-data-ucasti?start=0',                                 'Maily - nedávní účastníci (prvních 2000)'],
  ['maily-dle-data-ucasti?start=2000',                              'Maily - dávní účastníci (dalších 2000)'],
  // FINANCE
  ['finance-lide-v-databazi-a-zustatky',                            'Finance: Lidé v databázi + zůstatky',                            ['csv', 'html']],
  ['finance-aktivity-negenerujici-slevu',                           'Finance: Aktivity negenerující slevu',                           ['csv', 'html']],
  ['finance-prijmy-a-vydaje-infopultaka',                           'Finance: Příjmy a výdaje infopulťáka',                           ['csv', 'html']],
  // ZÁZEMÍ & PROGRAM
  ['zazemi-a-program-drd-historie-ucasti',                          'Zázemí & Program: DrD: Historie účasti',                         ['csv', 'html']],
  ['zazemi-a-program-drd-seznam-prihlasenych-pro-aktualni-rok',     'Zázemí & Program: DrD: Seznam přihlášených pro aktuální rok',    ['csv', 'html']],
  ['zazemi-a-program-zarizeni-mistnosti',                           'Zázemí & Program: Zařízení místností',                           ['csv', 'html']],
  ['zazemi-a-program-honko-report-pro-aktualni-rok',                'Zázemí & Program: Hoňko report pro aktuální rok',                ['csv', 'html']],
  ['zazemi-a-program-emaily-na-vypravece-dle-linii',                'Zázemí & Program: Emaily na vypravěče dle linií',                ['csv', 'html']],
  ['zazemi-a-program-emaily na ucastniky dle linii',                'Zázemí & Program: Emaily na účastníky dle linií',                ['csv', 'html']],
  ['zazemi-a-program-aktivity-pro-dotaznik-dle-linii',              'Zázemí & Program: Aktivity pro dotazník dle linií',              ['csv', 'html']],
  ['zazemi-a-program-potvrzeni-pro-navstevniky-mladsi-patnacti-let','Zázemí & Program: Potvrzení pro návštěvníky mladší patnácti let',['csv', 'html']],
  ['zazemi-a-program-casy-a-umisteni-aktivit',                      'Zázemí & Program: Časy a umístění aktivit',                      ['csv', 'html']],
  ['zazemi-a-program-prehled-mistnosti',                            'Zázemí & Program: Přehled místností',                            ['csv', 'html']],
  ['zazemi-a-program-seznam-ucastniku-a-tricek',                    'Zázemí & Program: Seznam účastníků a triček',                    ['csv', 'html']],
  ['zazemi-a-program-seznam-ucastniku-a-tricek-grouped',            'Zázemí & Program: Seznam účastníků a triček (grouped)',          ['csv', 'html']],
  // BFGR
  ['celkovy-report',                                           '<br>Celkový report '.ROK.'<br><br>',                                  ['csv', 'html']],
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

foreach(dbQuery('SELECT * FROM reporty ORDER BY nazev') as $r) {
  $t->assign($r);
  $t->parse('reporty.quick');
}

$t->parse('reporty');
$t->out('reporty');
