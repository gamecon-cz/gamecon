<?php

use Gamecon\Shop\Shop;
use Gamecon\XTemplate\XTemplate;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

require __DIR__ . '/sdilene-hlavicky.php';

$t = new XTemplate(__DIR__ . '/stravenky-bianco.xtpl');

$systemoveNastaveni ??= SystemoveNastaveni::vytvorZGlobals();
$rocnik             = $systemoveNastaveni->rocnik();
$typJidlo           = Shop::JIDLO;

$o = dbQuery(<<<SQL
SELECT nazev,
       FIELD(SUBSTRING(TRIM(nazev), POSITION(' ' IN TRIM(nazev)) + 1), 'středa', 'čtvrtek', 'pátek', 'sobota', 'neděle') AS poradi_dne,
       FIELD(SUBSTRING(TRIM(nazev), 1, POSITION(' ' IN TRIM(nazev)) - 1), 'Snídaně', 'Oběd', 'Večeře') AS poradi_jidla
FROM shop_predmety
WHERE model_rok = {$rocnik}
  AND typ = {$typJidlo}
ORDER BY poradi_dne,
         poradi_jidla
SQL,
);
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
