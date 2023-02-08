<?php

use Gamecon\Role\Zidle;
use Gamecon\Shop\Shop;
use Gamecon\XTemplate\XTemplate;

require __DIR__ . '/sdilene-hlavicky.php';

$t = new XTemplate(__DIR__ . '/stravenky.xtpl');

$o = dbQuery('
    SELECT
      u.id_uzivatele, u.login_uzivatele,
      p.nazev
    FROM uzivatele_hodnoty u
    JOIN platne_zidle_uzivatelu z ON(z.id_uzivatele = u.id_uzivatele AND z.id_zidle = ' . Zidle::PRIHLASEN_NA_LETOSNI_GC . ')
    JOIN shop_nakupy n ON(n.id_uzivatele = u.id_uzivatele AND n.rok = ' . ROK . ')
    JOIN shop_predmety p ON(p.id_predmetu = n.id_predmetu AND p.typ = ' . Shop::JIDLO . ')
    ORDER BY u.id_uzivatele, p.ubytovani_den DESC, p.nazev DESC
  ');

$curr = mysqli_fetch_assoc($o);
$next = mysqli_fetch_assoc($o);
while ($curr) {
    $t->assign($curr);
    $t->parse('stravenky.uzivatel.jidlo');
    if (!$next || $curr['id_uzivatele'] != $next['id_uzivatele']) {
        $t->parse('stravenky.uzivatel');
    }
    $curr = $next;
    $next = mysqli_fetch_assoc($o);
}

$t->parse('stravenky');
$t->out('stravenky');
