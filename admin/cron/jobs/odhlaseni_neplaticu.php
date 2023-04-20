<?php

use Gamecon\Uzivatel\HromadneOdhlaseniNeplaticu;
use Gamecon\Uzivatel\Exceptions\NevhodnyCasProHromadneOdhlasovani;
use Gamecon\Kanaly\GcMail;
use Gamecon\Cas\DateTimeCz;
use Gamecon\Logger\Zaznamnik;

/** @var bool $znovu */

require_once __DIR__ . '/../_cron_zavadec.php';

$cronNaCas = require __DIR__ . '/../_cron_na_cas.php';
if (!$cronNaCas) {
    return;
}

set_time_limit(30);

global $systemoveNastaveni;

$hromadneOdhlaseniNeplaticu = new HromadneOdhlaseniNeplaticu($systemoveNastaveni);

if (!$znovu || $systemoveNastaveni->jsmeNaOstre()) {
    $odhlaseniProvedenoKdy = $hromadneOdhlaseniNeplaticu->odhlaseniProvedenoKdy();
    if ($odhlaseniProvedenoKdy) {
        $odhlaseniProvedenoKdy = DateTimeCz::createFromInterface($odhlaseniProvedenoKdy);
        logs("Hromadné odhlášení už bylo provedeno {$odhlaseniProvedenoKdy->relativni()} ({$odhlaseniProvedenoKdy->format(DateTimeCz::FORMAT_DB)})");
        return;
    }
}

// abychom neodhlásili nešťastlivce, od kterého dorazili finance chvíli před odhlašováním neplatičů
require __DIR__ . '/../fio_stazeni_novych_plateb.php';

// jistota je jistota
$vynutZalohuDatabaze = true;
require __DIR__ . '/../zaloha_databaze.php';

$zaznamnik = new Zaznamnik();
try {
    $hromadneOdhlaseniNeplaticu->hromadneOdhlasit('automaticky', $zaznamnik);
} catch (NevhodnyCasProHromadneOdhlasovani $nevhodnyCasProHromadneOdhlasovani) {
    logs($nevhodnyCasProHromadneOdhlasovani->getMessage());
    return;
}
$odhlasenoCelkem = $hromadneOdhlaseniNeplaticu->odhlasenoCelkem();

{ // local scope
    $zprava          = "Hromadně odhlášeno $odhlasenoCelkem účastníků z GC";
    $zaznamy         = implode(";\n", $zaznamnik->zpravy());
    $uvodProCfo      = "Právě jsme odhlásili $odhlasenoCelkem účastníků z letošního Gameconu.";
    $oddelovacProCfo = str_repeat('═', mb_strlen($uvodProCfo));
    $cfosEmaily      = Uzivatel::cfosEmaily();
    (new GcMail())
        ->adresati($cfosEmaily ?: ['info@gamecon.cz'])
        ->predmet($zprava)
        ->text(<<<TEXT
            $uvodProCfo

            $oddelovacProCfo

            $zaznamy
            TEXT,
        )
        ->odeslat();

    logs($zprava);
}
