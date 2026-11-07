<?php

use Gamecon\Web\VerzeSouboru;
use Gamecon\XTemplate\XTemplate;

/**
 * @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni
 * @var Uzivatel $u
 */

require_once __DIR__ . '/../_jwt-konstanty.php';

$produktyAdminTemplate = new XTemplate(__DIR__ . '/_produktyAdmin.xtpl');

$produktyAdminTemplate->assign([
    'cssVersions' => new VerzeSouboru(__DIR__ . '/../../../files/ui', 'css'),
    'jsVersions'  => new VerzeSouboru(__DIR__ . '/../../../files/ui', 'js'),
]);

$produktyAdminTemplate->assign('basePathApi', URL_ADMIN . '/api/');
$produktyAdminTemplate->assign('rocnik', $systemoveNastaveni->rocnik());
$produktyAdminTemplate->assign('jwtKonstanty', jwtKonstantyJs($u, $systemoveNastaveni));

$produktyAdminTemplate->parse('produktyAdmin');
$produktyAdminTemplate->out('produktyAdmin');
