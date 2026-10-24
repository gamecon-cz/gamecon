<?php

use Gamecon\XTemplate\XTemplate;

/**
 * Úpravy novinek na webu
 *
 * nazev: Web
 * ikona: globe
 * pravo: 105
 */

$t = new XTemplate(__DIR__ . '/web.xtpl');

foreach (Novinka::zVsech() as $novinka) {
    $t->assign('novinka', $novinka);
    $t->parse('web.novinka');
}

$t->parse('web');
$t->out('web');

