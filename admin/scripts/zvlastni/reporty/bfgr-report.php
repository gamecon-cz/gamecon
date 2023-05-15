<?php
// takzvaný BFGR (Big f**king Gandalf report)

use Gamecon\Report\BfgrReport;

require __DIR__ . '/sdilene-hlavicky.php';

global $systemoveNastaveni;

$bfgrReport = new BfgrReport($systemoveNastaveni);
$bfgrReport->exportuj(get('format'));
