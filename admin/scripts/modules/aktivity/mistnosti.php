<?php

/**
 * Stránka pro přehled všech přihlášených na aktivity
 *
 * nazev: Místnosti
 * ikona: door-open
 * pravo: 102
 * submenu_group: 4
 * submenu_order: 1
 */

use Gamecon\XTemplate\XTemplate;
use Gamecon\Aktivita\SqlStruktura\LokaceSqlStruktura;

if (post('upravit')) {
    dbInsertUpdate(LokaceSqlStruktura::LOKACE_TABULKA, $_POST['fields']);
    back();
}

if (post('nahoru')) {
    dbExecute('UPDATE lokace SET poradi=poradi+1 WHERE poradi=$0', [post('poradi') - 1]);
    dbExecute('UPDATE lokace SET poradi=poradi-1 WHERE id_lokace=$0', [post('nahoru')]);
    back();
}

if (post('dolu')) {
    dbExecute('UPDATE lokace SET poradi=poradi-1 WHERE poradi=$0', [post('poradi') + 1]);
    dbExecute('UPDATE lokace SET poradi=poradi+1 WHERE id_lokace=$0', [post('dolu')]);
    back();
}

if (post('novaMistnost')) {
    $a      = dbOneLine('SELECT MAX(poradi) AS posledni FROM lokace');
    $poradi = $a['posledni'] + 1;
    dbInsertUpdate(LokaceSqlStruktura::LOKACE_TABULKA, [LokaceSqlStruktura::NAZEV => 'Nová místnost ' . $poradi, LokaceSqlStruktura::PORADI => $poradi]);
    back();
}

$tpl = new XTemplate(__DIR__ . '/mistnosti.xtpl');

$o = dbFetch('SELECT * FROM lokace ORDER BY poradi');
$l = $o->rowCount();
for ($i = 0; $r = $o->fetch(PDO::FETCH_ASSOC); $i++) {
    $tpl->assign($r);
    if ($i > 0)
        $tpl->parse('mistnosti.mistnost.nahoru');
    if ($i + 1 < $l)
        $tpl->parse('mistnosti.mistnost.dolu');
    $tpl->parse('mistnosti.mistnost');
}

$tpl->parse('mistnosti');
$tpl->out('mistnosti');
