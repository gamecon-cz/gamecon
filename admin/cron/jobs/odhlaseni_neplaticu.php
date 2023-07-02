<?php

declare(strict_types=1);

use Gamecon\Uzivatel\HromadneOdhlaseniNeplaticu;
use Gamecon\Uzivatel\Exceptions\NevhodnyCasProHromadneOdhlasovani;
use Gamecon\Kanaly\GcMail;
use Gamecon\Cas\DateTimeCz;
use Gamecon\Logger\Zaznamnik;
use Gamecon\Report\BfgrReport;

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
        logs("Hromadné odhlášení už bylo provedeno {$odhlaseniProvedenoKdy->format(DateTimeCz::FORMAT_DB)}({$odhlaseniProvedenoKdy->relativni()})");
        return;
    }
}

try {
    /** musíme použít @see \Generator::current kód spustili a vyhodnotil se */
    $hromadneOdhlaseniNeplaticu->neplaticiAKategorie()->current();
} catch (NevhodnyCasProHromadneOdhlasovani $nevhodnyCasProHromadneOdhlasovani) {
    logs($nevhodnyCasProHromadneOdhlasovani->getMessage());
    return;
}

// abychom neodhlásili nešťastlivce, od kterého dorazili finance chvíli před odhlašováním neplatičů
requireOnceIsolated(__DIR__ . '/../fio_stazeni_novych_plateb.php');

// jistota je jistota
$vynutZalohuDatabaze = true;
require __DIR__ . '/../zaloha_databaze.php';

$bfgrSoubor = sys_get_temp_dir() . '/' . uniqid('bfgr-', true) . '.xlsx';
$bfgrReport = new BfgrReport($systemoveNastaveni);
$bfgrReport->exportuj('xlsx', true, $bfgrSoubor);

$zaznamnik = new Zaznamnik();
try {
    $hromadneOdhlaseniNeplaticu->hromadneOdhlasit(zdrojOdhlaseniZaklad: 'automaticky', zaznamnik: $zaznamnik);
} catch (NevhodnyCasProHromadneOdhlasovani $nevhodnyCasProHromadneOdhlasovani) {
    logs($nevhodnyCasProHromadneOdhlasovani->getMessage());
    return;
}
$odhlasenoCelkem = $hromadneOdhlaseniNeplaticu->odhlasenoCelkem();

$zprava          = "Hromadně odhlášeno $odhlasenoCelkem účastníků z GC";
$zaznamy         = implode(";\n", $zaznamnik->zpravy());
$uvodProCfo      = "Právě jsme odhlásili $odhlasenoCelkem účastníků z letošního Gameconu.";
$oddelovacProCfo = str_repeat('═', mb_strlen($uvodProCfo));
$cfosEmaily      = Uzivatel::cfosEmaily();
(new GcMail($systemoveNastaveni))
    ->adresati($cfosEmaily ?: ['info@gamecon.cz'])
    ->predmet($zprava)
    ->text(<<<TEXT
            $uvodProCfo

            $oddelovacProCfo

            $zaznamy
            TEXT,
    )
    ->prilohaSoubor($bfgrSoubor)
    ->odeslat(GcMail::FORMAT_TEXT);

logs($zprava);

logs('Email o hromadném odhlášení odeslán');
