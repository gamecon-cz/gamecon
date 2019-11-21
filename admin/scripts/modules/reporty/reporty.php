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

$reporty = dbFetchAll('SELECT skript, nazev, format_csv, format_html FROM univerzalni_reporty');

$t = new XTemplate('reporty.xtpl');

foreach($reporty as $r) {
  $kontext = [
    'nazev' =>  str_replace('{ROK}', ROK, $r['nazev']),
    'html'  =>  $r['format_html']
      ? '<a href="reporty/'.$r['skript'].(strpos('?', $r['skript']) === false ? '?' : '&').'format=html" target="_blank">html</a>'
      : '',
    'csv'   =>  $r['format_csv']
      ? '<a href="reporty/'.$r['skript'].(strpos('?', $r['skript']) === false ? '?' : '&').'?format=csv">csv</a>'
      : '',
  ];
  $t->assign($kontext);
  $t->parse('reporty.report');
}

foreach(dbQuery('SELECT * FROM quick_reporty ORDER BY nazev') as $r) {
  $t->assign($r);
  $t->parse('reporty.quick');
}

$t->parse('reporty');
$t->out('reporty');
