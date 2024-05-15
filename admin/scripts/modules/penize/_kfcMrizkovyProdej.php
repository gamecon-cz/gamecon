<?php

use Gamecon\Web\VerzeSouboru;

/** @var \Gamecon\XTemplate\XTemplate $x */
/** @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */

$x->assign([
    'cssVersions' => new VerzeSouboru(__DIR__ . '/../../../files/ui', 'css'),
    'jsVersions'  => new VerzeSouboru(__DIR__ . '/../../../files/ui', 'js'),
]);

$x->assign('basePathApi', URL_ADMIN . '/api/');
$x->assign('rocnik', $systemoveNastaveni->rocnik());
$x->parse("{$x->root()}.kfc");
