<?php
if (defined('UNIT_TESTS') && UNIT_TESTS) {
    return;
}

$warhammer40kTurnaj2022Wrapped = Aktivita::zNazvuARoku('Warhammer 40k turnaj - 1.kolo', ROK);

$warhammer40kTurnaj2022 = reset($warhammer40kTurnaj2022Wrapped);
unset($warhammer40kTurnaj2022Wrapped);

$uzivatelGamecon = Uzivatel::zNicku('Gamecon');
$uzivatelGamecon->gcPrihlas();

function flatten(array $mutliDimensionalArray) {
    $flattened = [];
    array_walk_recursive($mutliDimensionalArray, function ($array) use (&$flattened) {
        $flattened[] = $array;
    });
    return $flattened;
}

$puvodniStav = $warhammer40kTurnaj2022->stav();
$warhammer40kTurnaj2022->aktivuj();
$warhammer40kTurnaj2022->prihlas($uzivatelGamecon, Aktivita::STAV /* ignorovat stav */);
$warhammer40kTurnaj2022->zamknout($uzivatelGamecon);
$warhammer40kTurnaj2022->prihlasTym([], '', null /* beze změny */, flatten($warhammer40kTurnaj2022->dalsiKola()));
$warhammer40kTurnaj2022->aktivuj();

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
UPDATE akce_seznam
SET stav = {$puvodniStav->id()}
WHERE id_akce = {$warhammer40kTurnaj2022->id()}
SQL
);
