<?php

declare(strict_types=1);

use Gamecon\Kanaly\EmailovaSablona\EmailoveSablony;
use Gamecon\Cas\DateTimeCz;
use Gamecon\Uzivatel\Platby;
use Gamecon\Cas\DateTimeGamecon;
use Gamecon\Uzivatel\HromadneOdhlaseniNeplaticu;

/** @var bool $znovu */

require_once __DIR__ . '/../_cron_zavadec.php';

$cronNaCas = require __DIR__ . '/../_cron_na_cas.php';
if (!$cronNaCas) {
    return;
}

set_time_limit(30);

global $systemoveNastaveni;

$platby = new Platby($systemoveNastaveni);

if (!$platby->nejakeNesparovanePlatby($systemoveNastaveni->rocnik())) {
    return;
}

$hromadneOdhlaseniNeplaticu = new HromadneOdhlaseniNeplaticu($systemoveNastaveni);

$poradiOznameni = null;
$posuny         = [1 => '+1 day', 2 => '+1 week'];
foreach ($posuny as $poradiOznameni => $posun) {
    //  za týden mínus hodinu nebo za 23 hodin
    $overenaPlatnostZpetne           = DateTimeGamecon::overenaPlatnostZpetne($systemoveNastaveni)
        ->modifyStrict($posun); // jako kdybychom bychom pouštěli hromadné odhlašování za týden / zítra
    $nejblizsiHromadneOdhlasovaniKdy = DateTimeGamecon::nejblizsiHromadneOdhlasovaniKdy(
        $systemoveNastaveni,
        $overenaPlatnostZpetne,
    );

    if ($nejblizsiHromadneOdhlasovaniKdy > $systemoveNastaveni->ted()->modify($posun)) {
        // POJISTKA PROTI PŘÍLIŽ BRZKÉMU SPUŠTĚNÍ
        logs("E-mail pro CFO s nespárovanými platbami: Hromadné odhlášení s posunem '$posun' bude až za dlouhou dobu, {$nejblizsiHromadneOdhlasovaniKdy->format(DateTimeCz::FORMAT_DB)} ({$nejblizsiHromadneOdhlasovaniKdy->relativniVBudoucnu($systemoveNastaveni->ted())}). Přeskakuji.");
        $poradiOznameni = null;
        continue;
    }

    logs("E-mail pro CFO s nespárovanými platbami: zkouším {$nejblizsiHromadneOdhlasovaniKdy->format(DateTimeCz::FORMAT_DB)} ({$nejblizsiHromadneOdhlasovaniKdy->relativniVBudoucnu($systemoveNastaveni->ted())}) (posun '$posun')");

    if ($znovu && !$systemoveNastaveni->jsmeNaOstre()) {
        break; // zkusíme hned
    }

    $odhlaseniProvedenoKdy = $hromadneOdhlaseniNeplaticu->odhlaseniProvedenoKdy($nejblizsiHromadneOdhlasovaniKdy);
    if ($odhlaseniProvedenoKdy) {
        $odhlaseniProvedenoKdy = DateTimeCz::createFromInterface($odhlaseniProvedenoKdy);
        logs("Hromadné odhlášení už bylo provedeno {$odhlaseniProvedenoKdy->format(DateTimeCz::FORMAT_DB)} ({$odhlaseniProvedenoKdy->relativni()}). Už nemá smyl informovat CFO o nespárovaných platbách.");

        return;
    }

    $cfoNotifikovanOBrzkemHromadnemOdhlaseniKdy = $platby->cfoNotifikovanONesparovanychPlatbachKdy(
        $systemoveNastaveni->rocnik(),
        $poradiOznameni,
    );
    if (!$cfoNotifikovanOBrzkemHromadnemOdhlaseniKdy) {
        break; // tohle oznámení jsme ještě neposlali
    }
    logs("{$poradiOznameni}. email pro CFO o nespárovaných platbách už byl odeslán {$cfoNotifikovanOBrzkemHromadnemOdhlaseniKdy->format(DateTimeCz::FORMAT_DB)}");
    $poradiOznameni = null;
}

if (!$poradiOznameni) {
    return;
}

// abychom měli čerstvé informace o platbách
// local scope
requireOnceIsolated(__DIR__ . '/../fio_stazeni_novych_plateb.php');

$zpravy = [];
foreach ($platby->nesparovanePlatby($systemoveNastaveni->rocnik()) as $platba) {
    $zpravy[] = "Nespárovaná platba s FIO ID {$platba->fioId()} s částkou {$platba->castka()} ze dne {$platba->provedeno()}";
}

$pocetNesparovanychPlateb = count($zpravy);
if ($pocetNesparovanychPlateb === 0) {
    logs('Žádné nespárované platby');

    return;
}

$cfosEmaily   = Uzivatel::cfosEmaily();
$zpravyString = implode(";\n", $zpravy);

$finalniPosun                    = $posuny[$poradiOznameni];
$overenaPlatnostZpetne           = DateTimeGamecon::overenaPlatnostZpetne($systemoveNastaveni)
    ->modifyStrict($finalniPosun);
$nejblizsiHromadneOdhlasovaniKdy = DateTimeGamecon::nejblizsiHromadneOdhlasovaniKdy(
    $systemoveNastaveni,
    $overenaPlatnostZpetne,
);

$brzy = mb_ucfirst($nejblizsiHromadneOdhlasovaniKdy->relativniVBudoucnu($systemoveNastaveni->ted(), true));
(new EmailoveSablony($systemoveNastaveni))
    ->vytvorMail('cfo_nesparovane_platby', [
        'kdy'    => $brzy,
        'pocet'  => $pocetNesparovanychPlateb,
        'seznam' => $zpravyString,
    ])
    ->adresati($cfosEmaily
                   ?: ['info@gamecon.cz'])
    ->odeslat();

$platby->zalogujCfoNotifikovanONesparovanychPlatbach(
    rocnik:              $systemoveNastaveni->rocnik(),
    poradiOznameni:      $poradiOznameni,
    nesparovanychPlateb: $pocetNesparovanychPlateb,
    provedl:             Uzivatel::zId(Uzivatel::SYSTEM),
);

logs('E-mail pro CFO s nespárovanými platbami: e-mail odeslán');
