<?php

use Gamecon\Uzivatel\HromadneOdhlaseniNeplaticu;
use Gamecon\Uzivatel\Exceptions\NevhodnyCasProHromadneOdhlasovani;
use Gamecon\Kanaly\GcMail;
use Gamecon\Cas\DateTimeCz;
use Gamecon\Logger\Zaznamnik;
use Gamecon\Cas\DateTimeGamecon;

require_once __DIR__ . '/_cron_zavadec.php';

try {
    $casovaTolerance = new DateInterval('PT1S');
    /** @var DateTimeImmutable $cas */
    $cas = require __DIR__ . '/_odlozeny_cas.php';
} catch (RuntimeException $runtimeException) {
    logs($runtimeException->getMessage());
    exit(1);
}

global $systemoveNastaveni;
$ted = $systemoveNastaveni->ted();

$sleep = $cas->getTimestamp() - $ted->getTimestamp();

if ($sleep >= 300) {
    logs("Čas spuštění bude až za $sleep sekund. Necháme to až na příští běh CRONu.");
    return;
}

set_time_limit($sleep + 1);

sleep($sleep);

set_time_limit(30);

global $systemoveNastaveni;

$hromadneOdhlaseniNeplaticu = new HromadneOdhlaseniNeplaticu($systemoveNastaveni);

$odhlaseniProvedenoKdy = $hromadneOdhlaseniNeplaticu->odhlaseniProvedenoKdy();
if ($odhlaseniProvedenoKdy) {
    logs("Hromadné odhlášení už bylo provedeno {$odhlaseniProvedenoKdy->format(DateTimeCz::FORMAT_DB)}");
    return;
}

// abychom neodhlásili nešťastlivce, od kterého dorazili finance chvíli před odhlašováním neplatičů
require __DIR__ . '/fio_stazeni_novych_plateb.php';

// jistota je jistota
$vynutZalohuDatabaze = true;
require __DIR__ . '/zaloha_databaze.php';

$zaznamnik = new Zaznamnik();
try {
    $hromadneOdhlaseniNeplaticu->hromadneOdhlasit($zaznamnik);
} catch (NevhodnyCasProHromadneOdhlasovani $nevhodnyCasProHromadneOdhlasovani) {
    logs($nevhodnyCasProHromadneOdhlasovani->getMessage());
    return;
}
$odhlasenoCelkem = $hromadneOdhlaseniNeplaticu->odhlasenoCelkem();

$zprava     = "Hromadně odhlášeno $odhlasenoCelkem účastníků z GC";
$zaznamy    = implode(";\n", $zaznamnik->zpravy());
$cfosEmaily = Uzivatel::cfosEmaily();
(new GcMail())
    ->adresati($cfosEmaily ?: ['info@gamecon.cz'])
    ->predmet($zprava)
    ->text(<<<TEXT
        Právě jsme odhlásili $odhlasenoCelkem účastníků z letošního Gameconu.
        ═════════════════════════════════════════════════════════════════════
        $zaznamy
        TEXT
    )
    ->odeslat();

logs($zprava);

$rok                             = $systemoveNastaveni->rocnik();
$nejblizsiHromadneOdhlasovaniKdy = DateTimeGamecon::nejblizsiVlnaKdy($systemoveNastaveni, $systemoveNastaveni->ted());
foreach ($zaznamnik->entity() as $uzivatel) {
    set_time_limit(30); // pro jistotu - v každém cyklu se odpočet time limitu resetuje
    /** @var Uzivatel $uzivatel */
    $a                = $uzivatel->koncovkaDlePohlavi('a');
    $emailOdhlasenemu = (new GcMail())
        ->adresat($uzivatel->mail())
        ->predmet("Byl{$a} jsi odhlášen{$a} z Gameconu {$rok}")
        ->text(<<<TEXT
            Právě jsme tě odhlásili z letošního Gameconu.
            ═════════════════════════════════════════════
            Pokud jsi platbu zapomněl{$a} poslat, přihlaš se zpět v další vlně aktivit, která bude {$nejblizsiHromadneOdhlasovaniKdy->formatCasStandard()} a platbu ohlídej.
            TEXT
        )
        ->odeslat();
}
