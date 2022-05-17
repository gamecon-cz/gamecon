<?php
$warhammer40kTurnaj2022Wrapped = Aktivita::zNazvuARoku('Warhammer 40k turnaj - 1.kolo', ROK);

if (!$warhammer40kTurnaj2022Wrapped) {
    return;
}

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
$warhammer40kTurnaj2022->prihlas($uzivatelGamecon, Aktivita::STAV /* ignorovat stav */ | Aktivita::DOPREDNE /* povolit přihlášení ikdyž není registrace na aktivity ještě spuštěná */);
$warhammer40kTurnaj2022->zamknout($uzivatelGamecon);
$warhammer40kTurnaj2022->prihlasTym([], '', null /* beze změny */, flatten($warhammer40kTurnaj2022->dalsiKola()));

if ($puvodniStav->jeAktivovana()) {
    $publikovana = Stav::PUBLIKOVANA;

    /** @var \Godric\DbMigrations\Migration $this */

    $this->q(<<<SQL
UPDATE akce_seznam
SET stav = {$publikovana}
WHERE id_akce = {$warhammer40kTurnaj2022->id()}
SQL
    );
}
