<?php

/**
 * Položky e-shopu
 *
 * nazev: E-shop
 * pravo: 108
 */

/** @var Uzivatel $u */

$x = new XTemplate('eshop.xtpl');

$x->parse('eshop');
$x->out('eshop');
