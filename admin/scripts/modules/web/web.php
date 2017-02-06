<?php

/** 
 * Úpravy novinek na webu
 *
 * nazev: Web
 * pravo: 105
 */

$t = new XTemplate('web.xtpl');

foreach(Novinka::zVsech() as $novinka) {
  $t->assign('novinka', $novinka);
  $t->parse('web.novinka');
}

$t->parse('web');
$t->out('web');

