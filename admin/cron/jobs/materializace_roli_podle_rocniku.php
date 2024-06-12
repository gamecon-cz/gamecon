<?php

declare(strict_types=1);

use Gamecon\Role\RolePodleRocniku;

/** @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */
require_once __DIR__ . '/../_cron_zavadec.php';

$cronNaCas = require __DIR__ . '/../_cron_na_cas.php';
if (!$cronNaCas) {
    return;
}

$rolePodleRocniku = new RolePodleRocniku();

// až v roce 2022 jsme začali logovat odhlašování z rolí
for ($rocnik = 2022; $rocnik <= $systemoveNastaveni->rocnik(); $rocnik++) {
    logs("Materializuji role pro ročník $rocnik");
    $rolePodleRocniku->prepocitejHistoriiRoliProRocnik($rocnik);
}

logs('Materializace rolí podle ročníku dokončena');
