<?php

declare(strict_types=1);

use Gamecon\Cas\Exceptions\ChybnaZpetnaPlatnost;
use Gamecon\Uzivatel\HromadneOdhlaseniNeplaticu;
use Gamecon\Kanaly\EmailovaSablona\EmailoveSablony;
use Gamecon\Cas\DateTimeCz;
use Gamecon\Uzivatel\Exceptions\NevhodnyCasProHromadneOdhlasovani;
use Gamecon\Cas\DateTimeGamecon;

/** @var bool $znovu */

require_once __DIR__ . '/../_cron_zavadec.php';

$cronNaCas = require __DIR__ . '/../_cron_na_cas.php';
if (!$cronNaCas) {
    return;
}

set_time_limit(30);

global $systemoveNastaveni;

$hromadneOdhlaseniNeplaticu = new HromadneOdhlaseniNeplaticu($systemoveNastaveni);

$poradiOznameni = null;
$posuny         = [1 => '+3 day'];
foreach ($posuny as $poradiOznameni => $posun) {
    $overenaPlatnostZpetne           = DateTimeGamecon::overenaPlatnostZpetne($systemoveNastaveni)
        ->modifyStrict($posun); // jako kdybychom bychom pouštěli hromadné odhlašování za tři dny
    $nejblizsiHromadneOdhlasovaniKdy = DateTimeGamecon::nejblizsiHromadneOdhlasovaniKdy(
        $systemoveNastaveni,
        $overenaPlatnostZpetne,
    );

    if ($nejblizsiHromadneOdhlasovaniKdy > $systemoveNastaveni->ted()->modify($posun)) {
        // POJISTKA PROTI PŘÍLIŽ BRZKÉMU SPUŠTĚNÍ
        logs("E-maily s varováním neplatičům: Hromadné odhlášení s posunem '$posun' bude až za dlouhou dobu, {$nejblizsiHromadneOdhlasovaniKdy->format(DateTimeCz::FORMAT_DB)} ({$nejblizsiHromadneOdhlasovaniKdy->relativniVBudoucnu($systemoveNastaveni->ted())}).");
        $poradiOznameni = null;
        continue;
    }
    logs("E-maily s varováním neplatičům: Zkouším {$nejblizsiHromadneOdhlasovaniKdy->format(DateTimeCz::FORMAT_DB)} ({$nejblizsiHromadneOdhlasovaniKdy->relativniVBudoucnu($systemoveNastaveni->ted())}) (posun '$posun')");

    if ($znovu && !$systemoveNastaveni->jsmeNaOstre()) {
        break; // zkusíme hned
    }

    $odhlaseniProvedenoKdy = $hromadneOdhlaseniNeplaticu->odhlaseniProvedenoKdy($nejblizsiHromadneOdhlasovaniKdy);
    if ($odhlaseniProvedenoKdy) {
        logs("E-maily s varováním neplatičům: Hromadné odhlášení už bylo provedeno {$odhlaseniProvedenoKdy->format(DateTimeCz::FORMAT_DB)} {$odhlaseniProvedenoKdy->relativni()}. Už nemá smysl neplatiče varovat.");

        return;
    }

    $neplaticiInformovaniOBrzkemHromadnemOdhlaseniKdy = $hromadneOdhlaseniNeplaticu->neplaticiNotifikovaniOBrzkemHromadnemOdhlaseniKdy(
        $nejblizsiHromadneOdhlasovaniKdy,
        $poradiOznameni,
    );
    if (!$neplaticiInformovaniOBrzkemHromadnemOdhlaseniKdy) {
        logs("E-maily s varováním neplatičům: Pošleme {$poradiOznameni}. varování k blížícímu se odhlašování {$nejblizsiHromadneOdhlasovaniKdy->format(DateTimeCz::FORMAT_DB)}.");
        break; // tohle oznámení jsme ještě neposlali
    }
    logs("{$poradiOznameni}. email s varováním pro neplatiče o brzkém hromadném odhlášení už byl odeslán {$neplaticiInformovaniOBrzkemHromadnemOdhlaseniKdy->format(DateTimeCz::FORMAT_DB)}");
    $poradiOznameni = null;
}

if (!$poradiOznameni) {
    return;
}

// abychom měli čerstvé informace o neplatičích
requireOnceIsolated(__DIR__ . '/../fio_stazeni_novych_plateb.php');

$pocetPotencialnichNeplaticu     = 0;
$finalniPosun                    = $posuny[$poradiOznameni];
$overenaPlatnostZpetne           = DateTimeGamecon::overenaPlatnostZpetne($systemoveNastaveni)
    ->modifyStrict($finalniPosun);
$nejblizsiHromadneOdhlasovaniKdy = DateTimeGamecon::nejblizsiHromadneOdhlasovaniKdy(
    $systemoveNastaveni,
    $overenaPlatnostZpetne,
);
$kDatu                           = $systemoveNastaveni->ted()->modify($finalniPosun);

$posledniGcMail = null;
$sablony        = new EmailoveSablony($systemoveNastaveni);
try {
    foreach ($hromadneOdhlaseniNeplaticu->neplaticiAKategorie(
        $nejblizsiHromadneOdhlasovaniKdy,
        $overenaPlatnostZpetne,
        $kDatu,
    )
        as ['neplatic' => $neplatic, 'kategorie_neplatice' => $kategorieNeplatice]
    ) {
        set_time_limit(10);
        /** @var \Gamecon\Uzivatel\KategorieNeplatice $kategorieNeplatice */
        /** @var \Uzivatel $neplatic */
        $gcMail = $sablony
            ->vytvorMail('varovani_brzke_odhlaseni_neplatic', ['jmeno' => (string)$neplatic->jmenoNick()])
            ->adresat($neplatic->mail());
        $gcMail->odeslat();
        $posledniGcMail = $gcMail;
        $pocetPotencialnichNeplaticu++;
    }
} catch (NevhodnyCasProHromadneOdhlasovani $nevhodnyCasProHromadneOdhlasovani) {
    logs($nevhodnyCasProHromadneOdhlasovani->getMessage());

    return;
}

$finalniOverenaPlatnostZpetne           = DateTimeGamecon::overenaPlatnostZpetne($systemoveNastaveni)
    ->modifyStrict($posun); // jako kdybychom bychom pouštěli hromadné odhlašování za tři dny
$finalniNejblizsiHromadneOdhlasovaniKdy = DateTimeGamecon::nejblizsiHromadneOdhlasovaniKdy(
    $systemoveNastaveni,
    $finalniOverenaPlatnostZpetne,
);

$hromadneOdhlaseniNeplaticu->zalogujNotifikovaniNeplaticuOBrzkemHromadnemOdhlaseni(
    $pocetPotencialnichNeplaticu,
    $finalniNejblizsiHromadneOdhlasovaniKdy,
    $poradiOznameni,
    Uzivatel::zId(Uzivatel::SYSTEM),
);

$cfosEmaily = Uzivatel::cfosEmaily();
$sablony
    ->vytvorMail('cfo_neplatici_varovani_odeslano', [
        'pocet'           => $pocetPotencialnichNeplaticu,
        'priklad_predmet' => $posledniGcMail?->dejPredmet() ?? '',
        'priklad_text'    => $posledniGcMail?->dejText() ?? '',
    ])
    ->adresati($cfosEmaily
        ?: ['info@gamecon.cz'])
    ->odeslat();

logs('E-maily s varováním neplatičům: e-maily odeslány');
