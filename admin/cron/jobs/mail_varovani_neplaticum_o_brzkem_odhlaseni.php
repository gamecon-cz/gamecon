<?php

declare(strict_types=1);

use Gamecon\Cas\Exceptions\ChybnaZpetnaPlatnost;
use Gamecon\Uzivatel\HromadneOdhlaseniNeplaticu;
use Gamecon\Kanaly\GcMail;
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
        logs("Hromadné odhlášení bude až za dlouhou dobu, {$nejblizsiHromadneOdhlasovaniKdy->format(DateTimeCz::FORMAT_DB)} ({$nejblizsiHromadneOdhlasovaniKdy->relativniVBudoucnu()}).\nE-maily s varováním neplatičům necháme na příští běh CRONu.");
        return; // nejbližší odhlašování bude až za dlouhou dobu, tohle necháme na příštím CRONu
    }

    if (!$znovu || $systemoveNastaveni->jsmeNaOstre()) {
        $odhlaseniProvedenoKdy = $hromadneOdhlaseniNeplaticu->odhlaseniProvedenoKdy($nejblizsiHromadneOdhlasovaniKdy);
        if ($odhlaseniProvedenoKdy) {
            logs("Hromadné odhlášení už bylo provedeno {$odhlaseniProvedenoKdy->format(DateTimeCz::FORMAT_DB)} {$odhlaseniProvedenoKdy->relativni()}. Už nemá smysl neplatiče varovat.");
            return;
        }

        $neplaticiInformovaniOBrzkemHromadnemOdhlaseniKdy = $hromadneOdhlaseniNeplaticu->neplaticiNotifikovaniOBrzkemHromadnemOdhlaseniKdy(
            $nejblizsiHromadneOdhlasovaniKdy,
            $poradiOznameni,
        );
        if (!$neplaticiInformovaniOBrzkemHromadnemOdhlaseniKdy) {
            break; // tohle oznámení jsme ještě neposlali
        }
        logs("{$poradiOznameni}. email s varováním pro neplatiče o brzkém hromadném odhlášení už byl odeslán {$neplaticiInformovaniOBrzkemHromadnemOdhlaseniKdy->format(DateTimeCz::FORMAT_DB)}");
        $poradiOznameni = null;
    }
}

if (!$poradiOznameni) {
    return;
}

// abychom měli čerstvé informace o neplatičích
require __DIR__ . '/../fio_stazeni_novych_plateb.php';

$pocetPotencialnichNeplaticu = 0;
$rocnik                      = $systemoveNastaveni->rocnik();
try {
    $uvod      = "Prosíme zaplať své objednávky na Gamecon $rocnik";
    $oddelovac = str_repeat('═', mb_strlen($uvod));
    foreach ($hromadneOdhlaseniNeplaticu->neplaticiAKategorie()
             as ['uzivatel' => $uzivatel, 'kategorie_neplatice' => $kategorieNeplatice]
    ) {
        set_time_limit(10);
        $a = $uzivatel->koncovkaDlePohlavi();
        /** @var \Gamecon\Uzivatel\KategorieNeplatice $kategorieNeplatice */
        (new GcMail($systemoveNastaveni))
            ->adresat($uzivatel->mail())
            ->predmet("Nezaplacené objednávky Gamecon $rocnik")
            ->text(<<<TEXT
                $uvod

                $oddelovac

                "Ahoj {$uzivatel->jmenoNick()}, zaplať prosím všechny své objednávky, jinak Tě budeme muset za tři dny odhlásit z Gameconu $rocnik"
                TEXT,
            )
            ->odeslat();
        $pocetPotencialnichNeplaticu++;
    }
} catch (NevhodnyCasProHromadneOdhlasovani $nevhodnyCasProHromadneOdhlasovani) {
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
