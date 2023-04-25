<?php

declare(strict_types=1);

use Gamecon\Kanaly\GcMail;
use Gamecon\Cas\DateTimeCz;
use Gamecon\Uzivatel\Exceptions\NevhodnyCasProHromadneOdhlasovani;
use Gamecon\Role\Role;
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
foreach ([1 => '+1 week', 2 => '+1 day'] as $poradiOznameni => $posun) {
    //  za týden mínus hodinu nebo za 23 hodin
    $overenaPlatnostZpetne           = DateTimeGamecon::overenaPlatnostZpetne($systemoveNastaveni)
        ->modifyStrict($posun); // jako kdybychom bychom pouštěli hromadné odhlašování za týden / zítra
    $nejblizsiHromadneOdhlasovaniKdy = DateTimeGamecon::nejblizsiHromadneOdhlasovaniKdy(
        $systemoveNastaveni,
        $overenaPlatnostZpetne,
    );

    if ($nejblizsiHromadneOdhlasovaniKdy > $systemoveNastaveni->ted()->modify($posun)) {
        logs("Hromadné odhlášení bude až za dlouhou dobu, {$nejblizsiHromadneOdhlasovaniKdy->format(DateTimeCz::FORMAT_DB)} ({$nejblizsiHromadneOdhlasovaniKdy->relativniVBudoucnu()}).\nE-mail pro CFO s nespárovanými platbami necháme na příští běh CRONu.");
        // POJISTKA PROTI PŘÍLIŽ BRZKÉMU SPUŠTĚNÍ
        return; // nejbližší odhlašování bude až za dlouhou dobu, tohle necháme na příštím CRONu
    }

    if (!$znovu || $systemoveNastaveni->jsmeNaOstre()) {
        $odhlaseniProvedenoKdy = $hromadneOdhlaseniNeplaticu->odhlaseniProvedenoKdy($nejblizsiHromadneOdhlasovaniKdy);
        if ($odhlaseniProvedenoKdy) {
            $odhlaseniProvedenoKdy = DateTimeCz::createFromInterface($odhlaseniProvedenoKdy);
            logs("Hromadné odhlášení už bylo provedeno {$odhlaseniProvedenoKdy->relativni()} ({$odhlaseniProvedenoKdy->format(DateTimeCz::FORMAT_DB)}). Už nemá smyl informovat CFO o nespárovaných platbách.");
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
}

if (!$poradiOznameni) {
    return;
}

// abychom měli čerstvé informace o platbách
require __DIR__ . '/../fio_stazeni_novych_plateb.php';

$zpravy = [];
foreach ($platby->nesparovanePlatby($systemoveNastaveni->rocnik()) as $platba) {
    $zpravy[] = "Nespárovaná platba s FIO ID '{$platba->fioId()}' s částkou {$platba->castka()} ze dne {$platba->provedeno()}";
}

$pocetNesparovanychPlateb = count($zpravy);
if ($pocetNesparovanychPlateb === 0) {
    logs('Žádné nespárované platby');
    return;
}

$cfosEmaily   = Uzivatel::cfosEmaily();
$zpravyString = implode(";\n", $zpravy);
$brzy         = match ($poradiOznameni) {
    1 => 'Za týden',
    2 => 'Zítra',
    default => 'Brzy'
};
$uvod         = "$brzy Gamecon systém hromadně odhlásí neplatiče. Přitom ale máme $pocetNesparovanychPlateb nespárovaných plateb a hrozí komplikace.";
$oddelovac    = str_repeat('═', mb_strlen($uvod));
(new GcMail())
    ->adresati($cfosEmaily ?: ['info@gamecon.cz'])
    ->predmet("$brzy bude hromadné odhlášení a stále máme $pocetNesparovanychPlateb nespárovaných plateb")
    ->text(<<<TEXT
        $uvod

        $oddelovac

        $zpravyString
        TEXT,
    )
    ->odeslat();

$platby->zalogujCfoNotifikovanONesparovanychPlatbach(
    $systemoveNastaveni->rocnik(),
    $pocetNesparovanychPlateb,
    $poradiOznameni,
    Uzivatel::zId(Uzivatel::SYSTEM),
);
