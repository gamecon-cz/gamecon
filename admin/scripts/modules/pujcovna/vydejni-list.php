<?php

use Gamecon\Kostymy\FormatPujcovny;
use Gamecon\Kostymy\PrehledyPujcovny;
use Gamecon\XTemplate\XTemplate;

/**
 * Kostýmy k vydání na aktivity v příštích hodinách, k vytištění.
 *
 * nazev: Výdejní list
 * ikona: list-check
 * pravo: 115
 * submenu_group: 1
 * submenu_order: 3
 */

$hodinVydejnihoListu = max(1, min(48, (int) (get('hodin') ?: 4)));
$radkyVydejnihoListu = PrehledyPujcovny::zGlobals()->vydejniList($hodinVydejnihoListu);
$ucastniciVydejnihoListu = FormatPujcovny::uzivatelePodleId(array_column($radkyVydejnihoListu, 'id_uzivatele'));

$tplVydejnihoListu = new XTemplate(__DIR__ . '/vydejni-list.xtpl');
$tplVydejnihoListu->assign('hodin', $hodinVydejnihoListu);
$predchoziAktivita = null;
foreach ($radkyVydejnihoListu as $radekVydejnihoListu) {
    if ($predchoziAktivita !== null && $predchoziAktivita !== $radekVydejnihoListu['id_akce']) {
        $tplVydejnihoListu->parse('vydejniList.aktivita');
    }
    $predchoziAktivita = $radekVydejnihoListu['id_akce'];
    $ucastnikListu = $ucastniciVydejnihoListu[(int) $radekVydejnihoListu['id_uzivatele']] ?? null;
    $tplVydejnihoListu->assign([
        'casAktivity'    => htmlspecialchars(FormatPujcovny::casAktivity($radekVydejnihoListu['zacatek'], $radekVydejnihoListu['konec'])),
        'nazevAktivity'  => htmlspecialchars($radekVydejnihoListu['nazev_akce']),
        'idUcastnika'    => (int) $radekVydejnihoListu['id_uzivatele'],
        'jmenoUcastnika' => htmlspecialchars(FormatPujcovny::jmeno($ucastnikListu)),
        'idKostymu'      => (int) $radekVydejnihoListu['id_kostymu'],
        'nazevKostymu'   => htmlspecialchars($radekVydejnihoListu['nazev_kostymu']),
        'stav'           => $radekVydejnihoListu['id_vydeje'] !== null
            ? 'vydaný ' . htmlspecialchars(FormatPujcovny::denACas($radekVydejnihoListu['vydano']))
            : '☐',
    ]);
    $tplVydejnihoListu->parse('vydejniList.aktivita.kostym');
}
if ($predchoziAktivita !== null) {
    $tplVydejnihoListu->parse('vydejniList.aktivita');
} else {
    $tplVydejnihoListu->parse('vydejniList.zadny');
}
$tplVydejnihoListu->parse('vydejniList');
$tplVydejnihoListu->out('vydejniList');
