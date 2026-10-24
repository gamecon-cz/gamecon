<?php

use Gamecon\Web\VerzeSouboru;
use Gamecon\XTemplate\XTemplate;

/**
 * Jídla, ze kterých si účastníci vybírají k objednaným obědům a večeřím.
 *
 * nazev: Jídelníček 🍽️
 * pravo: 108
 * submenu_group: 5
 */

/**
 * @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni
 * @var Uzivatel $u
 */

require_once __DIR__ . '/../_jwt-konstanty.php';

$t = new XTemplate(__DIR__ . '/jidelnicek.xtpl');

$t->assign([
    'cssVersions'  => new VerzeSouboru(__DIR__ . '/../../../files/ui', 'css'),
    'jsVersions'   => new VerzeSouboru(__DIR__ . '/../../../files/ui', 'js'),
    'rocnik'       => $systemoveNastaveni->rocnik(),
    'jwtKonstanty' => jwtKonstantyJs($u, $systemoveNastaveni),
]);

$t->parse('jidelnicek');
$t->out('jidelnicek');
