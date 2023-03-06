<?php

use Gamecon\Uzivatel\HromadneOdhlaseniNeplaticu;
use Gamecon\Uzivatel\Exceptions\NevhodnyCasProHromadneOdhlasovani;
use Gamecon\Kanaly\GcMail;
use Gamecon\Cas\DateTimeCz;

require_once __DIR__ . '/cron_zavadec.php';

try {
    $casovaTolerance = new DateInterval('PT1S');
    /** @var DateTimeImmutable $cas */
    $cas = require __DIR__ . '/odlozeny_cas.php';
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

$potize                     = false;
$hromadneOdhlaseniNeplaticu = new HromadneOdhlaseniNeplaticu($systemoveNastaveni);

$odhlaseniProvedenoKdy = $hromadneOdhlaseniNeplaticu->odhlaseniProvedenoKdy();
if ($odhlaseniProvedenoKdy) {
    logs("Hromadné odhlášení už bylo provedeno {$odhlaseniProvedenoKdy->format(DateTimeCz::FORMAT_DB)}");
    return;
}

// abychom neodhlásli nešťastlivce, od kterého dorazili finance chvíli před odhlašováním neplatičů
require __DIR__ . '/fio_stazeni_novych_plateb.php';

try {
    $hromadneOdhlaseniNeplaticu->hromadneOdhlasit();
} catch (NevhodnyCasProHromadneOdhlasovani $nevhodnyCasProHromadneOdhlasovani) {
    logs($nevhodnyCasProHromadneOdhlasovani->getMessage());
    return;
} catch (Chyba $chyba) {
    $potize = $chyba->getMessage();
}
$odhlasenoCelkem = $hromadneOdhlaseniNeplaticu->odhlasenoCelkem();

$zprava = "Hromadně odhlášeno $odhlasenoCelkem účastníků z GC";
(new GcMail())
    ->adresat('info@gamecon.cz')
    ->predmet($zprava)
    ->text("Právě jsme odhlásili $odhlasenoCelkem účastníků z letošního Gameconu."
        . ($potize
            ? ("\n\nU některých se vyskytly komplikace $potize")
            : ''
        )
    )
    ->odeslat();

logs($zprava);
