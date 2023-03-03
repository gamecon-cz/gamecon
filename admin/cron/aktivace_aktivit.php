<?php

use Gamecon\Uzivatel\HromadneOdhlaseniNeplaticu;
use Gamecon\Uzivatel\Exceptions\NevhodnyCasProHromadneOdhlasovani;
use Gamecon\Kanaly\GcMail;
use Gamecon\Cas\DateTimeCz;
use Gamecon\Aktivita\HromadneAkceAktivit;
use Gamecon\Aktivita\Exceptions\NevhodnyCasProAutomatickouHromadnouAktivaci;

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

$potize              = false;
$hromadneAkceAktivit = new HromadneAkceAktivit($systemoveNastaveni);

$automatickaAktivaceProvedenaKdy = $hromadneAkceAktivit->automatickaAktivaceProvedenaKdy();
if ($automatickaAktivaceProvedenaKdy) {
    logs("Hromadná aktivace aktivit už byla provedeno '{$automatickaAktivaceProvedenaKdy->format(DateTimeCz::FORMAT_DB)}'");
    return;
}

try {
    $hromadneAkceAktivit->hromadneAktivovatAutomaticky();
} catch (NevhodnyCasProAutomatickouHromadnouAktivaci $nevhodnyCasProAutomatickouHromadnouAktivaci) {
    logs($nevhodnyCasProAutomatickouHromadnouAktivaci->getMessage());
    return;
} catch (Chyba $chyba) {
    $potize = $chyba->getMessage();
}
$automatickyAktivovanoCelkem = $hromadneAkceAktivit->automatickyAktivovanoCelkem();

$zprava = "Hromadně aktivováno $automatickyAktivovanoCelkem aktivit";
(new GcMail())
    ->adresat('info@gamecon.cz')
    ->predmet($zprava)
    ->text("Právě jsme aktivovali $automatickyAktivovanoCelkem aktivit."
        . ($potize
            ? ("\n\nU některých se vyskytly komplikace $potize")
            : ''
        )
    )
    ->odeslat();

logs($zprava);
