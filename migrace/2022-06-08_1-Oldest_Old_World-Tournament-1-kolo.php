<?php

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\StavAktivity;

$oldestOldWorldTournament2022Wrapped = Aktivita::zNazvuARoku('Oldest Old World Tournament - WFB 6.5 edition - 1. kolo', ROK);

if (!$oldestOldWorldTournament2022Wrapped) {
    return;
}

$oldestOldWorldTournament2022 = reset($oldestOldWorldTournament2022Wrapped);
unset($oldestOldWorldTournament2022Wrapped);

$uzivatelGamecon = Uzivatel::zNicku('Gamecon');
$uzivatelGamecon->gcPrihlas($uzivatelGamecon);

$puvodniStav = $oldestOldWorldTournament2022->stav();
$oldestOldWorldTournament2022->aktivuj();
$oldestOldWorldTournament2022->prihlas($uzivatelGamecon, Aktivita::STAV /* ignorovat stav */ | Aktivita::DOPREDNE /* povolit přihlášení ikdyž není registrace na aktivity ještě spuštěná */);
$oldestOldWorldTournament2022->zamknoutProTeam($uzivatelGamecon);
$oldestOldWorldTournament2022->prihlasTym(
    [],
    '',
    null /* beze změny */,
    flatten($oldestOldWorldTournament2022->dalsiKola()),
    Aktivita::STAV /* ignorovat stav */ | Aktivita::DOPREDNE /* povolit přihlášení ikdyž není registrace na aktivity ještě spuštěná */
);

if ($puvodniStav->jeAktivovana()) {
    $publikovana = StavAktivity::PUBLIKOVANA;

    /** @var \Godric\DbMigrations\Migration $this */

    $this->q(<<<SQL
UPDATE akce_seznam
SET stav = {$publikovana}
WHERE id_akce = {$oldestOldWorldTournament2022->id()}
SQL
    );
}
