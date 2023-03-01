<?php

use Gamecon\Uzivatel\OdhlaseniNeplaticu;
use Gamecon\Uzivatel\Exceptions\NevhodnyCasProHromadneOdhlasovani;
use Gamecon\Kanaly\GcMail;

require_once __DIR__ . '/cron_zavadec.php';

try {
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

$potize             = false;
$odhlaseniNeplaticu = new OdhlaseniNeplaticu($systemoveNastaveni);
try {
    $odhlaseno = $odhlaseniNeplaticu->hromadneOdhlasit();
} catch (NevhodnyCasProHromadneOdhlasovani $nevhodnyCasProHromadneOdhlasovani) {
    logs($nevhodnyCasProHromadneOdhlasovani->getMessage());
    return;
} catch (Chyba $chyba) {
    $potize    = $chyba->getMessage();
    $odhlaseno = $odhlaseniNeplaticu->odhlaseno();
}

$zprava = "Hromadně odhlášeno $odhlaseno účastníků z GC";
(new GcMail())
    ->adresat('info@gamecon.cz')
    ->predmet($zprava)
    ->text("Právě jsme odhlásili $odhlaseno účastníků z letošního Gameconu."
        . ($potize
            ? ("\n\nU některých se vyskytly komplikace $potize")
            : ''
        )
    )
    ->odeslat();

logs($zprava);
