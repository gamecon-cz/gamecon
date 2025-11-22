<?php

use Gamecon\Report\BfsrReport;

require __DIR__ . '/sdilene-hlavicky.php';

/** @var $systemoveNastaveni */

ini_set('memory_limit', '512M');
set_time_limit(300);

$format = get('format');
$userId = get('userId');
$userId = $userId !== null
    ? (int)$userId
    : null;
$bfsrReport = new BfsrReport($systemoveNastaveni);
$bfsrReport->exportuj($format, $userId);
