<?php

use Gamecon\Uzivatel\Platby;

require_once __DIR__ . '/../_cron_zavadec.php';

/** @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */
global $systemoveNastaveni;

if (!defined('FIO_TOKEN') || FIO_TOKEN === '') {
    logs('FIO_TOKEN není definován, přeskakuji aktualizaci historických plateb.');

    return;
}

$platbyService = new Platby($systemoveNastaveni);

logsText('Zpracovávám platby z Fio API za posledních 90 dní...');
$fioPlatby = $platbyService->nactiZPoslednichDni(90, false);

if (!$fioPlatby) {
    logsText('...žádné zaúčtovatelné platby za posledních 90 dní.', false);

    return;
}

foreach ($fioPlatby as $fioPlatba) {
    logs(' - platba ' . $fioPlatba->id(), false);
    $gcPlatba = $platbyService->dejGcPlatbuPodleFioPlatby($fioPlatba);
    if (!$gcPlatba) {
        continue;
    }
    if ($platbyService->doplnPlatbu($gcPlatba, $fioPlatba)) {
        logs('  - údaje platby doplněny');
    }
}
