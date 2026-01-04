<?php

use Gamecon\Command\FioStazeniNovychPlateb;
use Gamecon\Logger\JobResultLogger;

require_once __DIR__ . '/_cron_zavadec.php';

/** @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */
global $systemoveNastaveni;

if (!defined('FIO_TOKEN') || FIO_TOKEN === '') {
    logs('FIO_TOKEN není definován, přeskakuji aktualizaci plateb.');

    return;
}

$fioStazeni = new FioStazeniNovychPlateb(
    $systemoveNastaveni,
    new JobResultLogger(),
);

$fioStazeni->stahniNoveFioPlatby();
