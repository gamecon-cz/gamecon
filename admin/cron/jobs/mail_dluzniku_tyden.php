<?php

declare(strict_types=1);

use Gamecon\Logger\JobResultLogger;
use Gamecon\Uzivatel\Enum\TypUpominky;
use Gamecon\Uzivatel\UpominaniDluzniku;
use Gamecon\Command\FioStazeniNovychPlateb;

/** @var bool $znovu */

require_once __DIR__ . '/../_cron_zavadec.php';

$cronNaCas = require __DIR__ . '/../_cron_na_cas.php';
if (!$cronNaCas) {
    return;
}

set_time_limit(60);

global $systemoveNastaveni;

$jobResultLogger = new JobResultLogger();

$upominaneDluzniku = new UpominaniDluzniku(
    $systemoveNastaveni,
    $jobResultLogger,
    new FioStazeniNovychPlateb($systemoveNastaveni, $jobResultLogger),
);
$upominaneDluzniku->odesliUpominkyDluznikum(TypUpominky::TYDEN, $znovu);
