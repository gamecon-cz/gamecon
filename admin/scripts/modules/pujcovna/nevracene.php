<?php

use Gamecon\Kostymy\FormatPujcovny;
use Gamecon\Kostymy\PrehledyPujcovny;
use Gamecon\XTemplate\XTemplate;

/**
 * Vydané kostýmy, které se nevrátily do konce aktivity a doby na vrácení, i u těch, kdo se mezitím odhlásili.
 *
 * nazev: Nevrácené
 * ikona: clock-rotate-left
 * pravo: 115
 * submenu_group: 1
 * submenu_order: 4
 */

$nevraceneKostymy = PrehledyPujcovny::zGlobals()->nevracenePoTerminu();
$drziteleKostymu = FormatPujcovny::uzivatelePodleId(array_column($nevraceneKostymy, 'id_uzivatele'));

$tplNevracenych = new XTemplate(__DIR__ . '/nevracene.xtpl');
foreach ($nevraceneKostymy as $nevracenyKostym) {
    $drzitelKostymu = $drziteleKostymu[(int) $nevracenyKostym['id_uzivatele']] ?? null;
    $tplNevracenych->assign([
        'idKostymu'      => (int) $nevracenyKostym['id_kostymu'],
        'nazevKostymu'   => htmlspecialchars($nevracenyKostym['nazev_kostymu']),
        'idUcastnika'    => (int) $nevracenyKostym['id_uzivatele'],
        'jmenoUcastnika' => htmlspecialchars(FormatPujcovny::jmeno($drzitelKostymu)),
        'kontakt'        => htmlspecialchars(implode(', ', array_filter([(string) $drzitelKostymu?->mail(), (string) $drzitelKostymu?->telefon()]))),
        'nazevAktivity'  => htmlspecialchars($nevracenyKostym['nazev_akce'] ?? '(aktivita už neexistuje)'),
        'vydano'         => htmlspecialchars(FormatPujcovny::denACas($nevracenyKostym['vydano'])),
        'vratitDo'       => htmlspecialchars(FormatPujcovny::denACas($nevracenyKostym['vratit_do'])),
        'odhlasen'       => $nevracenyKostym['id_rezervace'] === null
            ? 'odhlášený z aktivity'
            : '',
    ]);
    $tplNevracenych->parse('nevracene.kostym');
}
if ($nevraceneKostymy === []) {
    $tplNevracenych->parse('nevracene.zadny');
}
$tplNevracenych->parse('nevracene');
$tplNevracenych->out('nevracene');
