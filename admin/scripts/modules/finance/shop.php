<?php

use Gamecon\Web\ObrazkyEshopu;

/**
 * nazev: Shop
 * ikona: cart-shopping
 * pravo: 108
 * submenu_group: 5
 */

/** @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */

$obrazkyEshopu = new ObrazkyEshopu();
$obrazkyEshopu->zpracujPost();

require __DIR__ . '/_produktyAdmin.php';

echo $obrazkyEshopu->html();

require __DIR__ . '/../_import-eshopu.php';

require __DIR__ . '/../penize/_kfcMrizkovyProdej.php';
