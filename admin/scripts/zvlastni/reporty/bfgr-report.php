<?php
// takzvaný BFGR (Big f**king Gandalf report)

use Gamecon\Report\BfgrReport;

require __DIR__ . '/sdilene-hlavicky.php';

require_once __DIR__ . '/_bfgr_pomocne.php';

global $systemoveNastaveni;

$bfgrReport = new BfgrReport($systemoveNastaveni);
$bfgrReport->exportuj(get('format'));
