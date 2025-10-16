<?php
// takzvaný BFGR (Big f**king Gandalf report)

use Gamecon\Report\BfgrReport;

require __DIR__ . '/sdilene-hlavicky.php';

global $systemoveNastaveni;

ini_set('memory_limit', '512M');
set_time_limit(300);

$bfgrReport = new BfgrReport($systemoveNastaveni);
$bfgrReport->exportuj(
    format: get('format'),
    vcetneStavuNeplatice: true,
    idUzivatele: get('id')
);
