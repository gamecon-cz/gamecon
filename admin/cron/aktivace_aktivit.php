<?php

use Gamecon\Uzivatel\HromadneOdhlaseniNeplaticu;
use Gamecon\Uzivatel\Exceptions\NevhodnyCasProHromadneOdhlasovani;
use Gamecon\Kanaly\GcMail;
use Gamecon\Cas\DateTimeCz;
use Gamecon\Aktivita\HromadneAkceAktivit;
use Gamecon\Aktivita\Exceptions\NevhodnyCasProAutomatickouHromadnouAktivaci;

require_once __DIR__ . '/_cron_zavadec.php';

$cronNaCas = require __DIR__ . '/_cron_na_cas.php';
if (!$cronNaCas) {
    return;
}

set_time_limit(30);

global $systemoveNastaveni;

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
