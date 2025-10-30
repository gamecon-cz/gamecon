<?php

use Gamecon\Report\BfsrReport;

require __DIR__ . '/sdilene-hlavicky.php';

/** @var $systemoveNastaveni */

ini_set('memory_limit', '512M');
set_time_limit(300);

$bfsrReport = new BfsrReport($systemoveNastaveni);
$bfsrReport->exportuj(get('format'));
