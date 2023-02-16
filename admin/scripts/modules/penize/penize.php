<?php

use Gamecon\XTemplate\XTemplate;

/**
 * Koutek pro šéfa financí GC
 *
 * nazev: Peníze
 * pravo: 110
 */

/**
 * @var Uzivatel $u
 * @var Uzivatel|null $uPracovni
 * @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni
 */

$x = new XTemplate(__DIR__ . '/penize.xtpl');

$x->parse('penize');
$x->out('penize');
