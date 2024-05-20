<?php

use Gamecon\Web\VerzeSouboru;
use Gamecon\XTemplate\XTemplate;

/** @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */

$x = new XTemplate(__DIR__ . '/_kfcMrizkovyProdej.xtpl');

$x->assign([
    'cssVersions' => new VerzeSouboru(__DIR__ . '/../../../files/ui', 'css'),
    'jsVersions'  => new VerzeSouboru(__DIR__ . '/../../../files/ui', 'js'),
]);

$x->assign('basePathApi', URL_ADMIN . '/api/');
$x->assign('rocnik', $systemoveNastaveni->rocnik());

$x->parse('kfc');
$x->out('kfc');
