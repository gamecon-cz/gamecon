<?php

declare(strict_types=1);

use Gamecon\Report\FinanceReportNeplaticu;

require_once __DIR__ . '/sdilene-hlavicky.php';

/** @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */

(new FinanceReportNeplaticu($systemoveNastaveni))->exportuj(get('format'));
