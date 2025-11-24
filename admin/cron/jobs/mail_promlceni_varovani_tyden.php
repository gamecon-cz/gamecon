<?php

declare(strict_types=1);

use Gamecon\Uzivatel\Enum\TypVarovaniPromlceni;
use Gamecon\Uzivatel\PromlceniZustatku;
use Gamecon\Logger\JobResultLogger;

/** @var bool $znovu */

require_once __DIR__ . '/../_cron_zavadec.php';

$cronNaCas = require __DIR__ . '/../_cron_na_cas.php';
if (!$cronNaCas) {
    return;
}

set_time_limit(60);

global $systemoveNastaveni;

$promlceniZustatku = new PromlceniZustatku(
    $systemoveNastaveni,
    new JobResultLogger(),
);
$promlceniZustatku->odesliVarovneEmaily(TypVarovaniPromlceni::TYDEN, $znovu);
