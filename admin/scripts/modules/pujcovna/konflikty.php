<?php

use Gamecon\Kostymy\FormatPujcovny;
use Gamecon\Kostymy\PrehledyPujcovny;
use Gamecon\Kostymy\StavKostymuEnum;
use Gamecon\XTemplate\XTemplate;

/**
 * Rezervace, které nejde splnit: překryv vzniklý přesunem aktivity a rezervace kostýmů v opravě nebo vyřazených.
 * Přesun programu se kvůli kostýmu neblokuje, konflikt řeší správce (výměna kostýmu, zrušení rezervace).
 *
 * nazev: Konflikty
 * ikona: triangle-exclamation
 * pravo: 115
 * submenu_group: 1
 * submenu_order: 5
 */

$prehledyKonfliktu = PrehledyPujcovny::zGlobals();
$dvojiceVKonfliktu = $prehledyKonfliktu->konfliktyRezervaci();
$rezervaceMimoProvoz = $prehledyKonfliktu->rezervaceKostymuMimoProvoz();
$ucastniciKonfliktu = FormatPujcovny::uzivatelePodleId([
    ...array_column(array_column($dvojiceVKonfliktu, 'prvni'), 'id_uzivatele'),
    ...array_column(array_column($dvojiceVKonfliktu, 'druha'), 'id_uzivatele'),
    ...array_column($rezervaceMimoProvoz, 'id_uzivatele'),
]);

$tplKonfliktu = new XTemplate(__DIR__ . '/konflikty.xtpl');
$prirazRezervaci = static function (string $predpona, array $radek) use ($tplKonfliktu, $ucastniciKonfliktu): void {
    $tplKonfliktu->assign([
        $predpona . 'IdUcastnika'    => (int) $radek['id_uzivatele'],
        $predpona . 'JmenoUcastnika' => htmlspecialchars(FormatPujcovny::jmeno($ucastniciKonfliktu[(int) $radek['id_uzivatele']] ?? null)),
        $predpona . 'NazevAktivity'  => htmlspecialchars($radek['nazev_akce']),
        $predpona . 'CasAktivity'    => htmlspecialchars(FormatPujcovny::casAktivity($radek['zacatek'], $radek['konec'])),
    ]);
};

foreach ($dvojiceVKonfliktu as $dvojice) {
    $tplKonfliktu->assign([
        'idKostymu'    => (int) $dvojice['prvni']['id_kostymu'],
        'nazevKostymu' => htmlspecialchars($dvojice['prvni']['nazev_kostymu']),
    ]);
    $prirazRezervaci('prvni', $dvojice['prvni']);
    $prirazRezervaci('druha', $dvojice['druha']);
    $tplKonfliktu->parse('konflikty.prekryvy.dvojice');
}
if ($dvojiceVKonfliktu === []) {
    $tplKonfliktu->parse('konflikty.prekryvy.zadne');
}
$tplKonfliktu->parse('konflikty.prekryvy');

foreach ($rezervaceMimoProvoz as $rezervaceKostymuMimoProvoz) {
    $tplKonfliktu->assign([
        'idKostymu'    => (int) $rezervaceKostymuMimoProvoz['id_kostymu'],
        'nazevKostymu' => htmlspecialchars($rezervaceKostymuMimoProvoz['nazev_kostymu']),
        'stavKostymu'  => htmlspecialchars(StavKostymuEnum::from($rezervaceKostymuMimoProvoz['stav_kostymu'])->getNazev()),
    ]);
    $prirazRezervaci('rezervace', $rezervaceKostymuMimoProvoz);
    $tplKonfliktu->parse('konflikty.mimoProvoz.rezervace');
}
if ($rezervaceMimoProvoz === []) {
    $tplKonfliktu->parse('konflikty.mimoProvoz.zadna');
}
$tplKonfliktu->parse('konflikty.mimoProvoz');

$tplKonfliktu->parse('konflikty');
$tplKonfliktu->out('konflikty');
