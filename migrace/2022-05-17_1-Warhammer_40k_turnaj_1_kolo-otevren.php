<?php

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\StavAktivity;

$warhammer40kTurnaj2022Wrapped = Aktivita::zNazvuARoku('Warhammer 40k turnaj - 1.kolo', ROK);

if (!$warhammer40kTurnaj2022Wrapped) {
    return;
}

$warhammer40kTurnaj2022 = reset($warhammer40kTurnaj2022Wrapped);
unset($warhammer40kTurnaj2022Wrapped);

$uzivatelGamecon = Uzivatel::zNicku('Gamecon');
$uzivatelGamecon->gcPrihlas($uzivatelGamecon);

$puvodniStav = $warhammer40kTurnaj2022->stav();
$warhammer40kTurnaj2022->aktivuj();
$warhammer40kTurnaj2022->prihlas(
    $uzivatelGamecon,
    $uzivatelGamecon,
    Aktivita::STAV /* ignorovat stav */ | Aktivita::DOPREDNE /* povolit přihlášení ikdyž není registrace na aktivity ještě spuštěná */,
);
$warhammer40kTurnaj2022->zamknoutProTeam($uzivatelGamecon);
$warhammer40kTurnaj2022->prihlasTym(
    [],
    $uzivatelGamecon,
    '',
    null /* beze změny */,
    flatten($warhammer40kTurnaj2022->dalsiKola()),
    Aktivita::STAV /* ignorovat stav */ | Aktivita::DOPREDNE /* povolit přihlášení ikdyž není registrace na aktivity ještě spuštěná */
);

if ($puvodniStav->jeAktivovana()) {
    $publikovana = StavAktivity::PUBLIKOVANA;

    /** @var \Godric\DbMigrations\Migration $this */

    $this->q(<<<SQL
UPDATE akce_seznam
SET stav = {$publikovana}
WHERE id_akce = {$warhammer40kTurnaj2022->id()}
SQL
    );
}
