<?php

declare(strict_types=1);

use Gamecon\Aktivita\AktivitaTym;

require_once __DIR__ . '/../_cron_zavadec.php';

set_time_limit(60);

global $systemoveNastaveni;

AktivitaTym::smazRozpracovaneTymy();
