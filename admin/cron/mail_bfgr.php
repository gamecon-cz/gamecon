<?php

use Gamecon\Kanaly\GcMail;
use Gamecon\Cas\DateTimeCz;
use Gamecon\Uzivatel\Exceptions\NevhodnyCasProHromadneOdhlasovani;
use Gamecon\Role\Role;
use Gamecon\Uzivatel\Platby;

require_once __DIR__ . '/_cron_zavadec.php';

$cronNaCas = require __DIR__ . '/_cron_na_cas.php';
if (!$cronNaCas) {
    return;
}

set_time_limit(30);

global $systemoveNastaveni;

$bfgrSoubor = sys_get_temp_dir() . '/' . uniqid('bfgr-', true) . '.xlsx';
$bfgrReport = new \Gamecon\Report\BfgrReport($systemoveNastaveni);
$bfgrReport->exportuj('xlsx', true, $bfgrSoubor);

$uvod      = "Gamecon systém hromadně odhlásí neplatiče. Přitom ale máme XY a hrozí komplikace.";
$oddelovac = str_repeat('═', mb_strlen($uvod));
(new GcMail())
    ->adresati(['jaroslav.tyc.83@gmail.com'])
    ->predmet("bude hromadné odhlášení a stále máme XY nespárovaných plateb")
    ->text(<<<TEXT
        $uvod

        $oddelovac

        BLABLA
        TEXT
    )
    ->prilohaSoubor($bfgrSoubor)
    ->odeslat();
