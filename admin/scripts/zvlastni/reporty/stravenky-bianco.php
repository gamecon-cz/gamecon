<?php

use Gamecon\Shop\Shop;
use Gamecon\XTemplate\XTemplate;

require __DIR__ . '/sdilene-hlavicky.php';

$t = new XTemplate(__DIR__ . '/stravenky-bianco.xtpl');

$o = dbQuery('SELECT nazev FROM shop_predmety WHERE model_rok = ' . ROCNIK . ' AND typ = ' . Shop::JIDLO);
while ($r = mysqli_fetch_assoc($o)) {
    $jidla[] = $r['nazev'];
}

for ($i = 0; $i < 24; $i++) {
    foreach ($jidla as $jidlo) {
        $t->assign('nazev', $jidlo);
        $t->parse('stravenky.uzivatel.jidlo');
    }
    $t->parse('stravenky.uzivatel');
}

// Netisknout upozornění, protože se tiskne 1 list. Zabíralo by místo.

$t->parse('stravenky');
$t->out('stravenky');
