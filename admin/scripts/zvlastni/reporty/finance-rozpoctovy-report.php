<?php
use Gamecon\Report\RozpoctovyReport;

require __DIR__ . '/sdilene-hlavicky.php';

global $systemoveNastaveni;

$rozpoctovyReport = new RozpoctovyReport($systemoveNastaveni);
$rozpoctovyReport->exportuj(
    format: get('format'),
);
