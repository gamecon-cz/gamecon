<?php

use Gamecon\Shop\Shop;
use Gamecon\Shop\Predmet;
use Gamecon\Shop\TypPredmetu;
use Gamecon\XTemplate\XTemplate;

/**
 * nazev: Shop
 * pravo: 110
 *
 */

/** @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */

$template = new XTemplate(__DIR__ . '/shop.xtpl');

$template->parse('shop.typ');

$template->assign([
    'cssVersions' => new \Gamecon\Web\VerzeSouboru(__DIR__ . '/../../../files/ui', 'css'),
    'jsVersions'  => new \Gamecon\Web\VerzeSouboru(__DIR__ . '/../../../files/ui', 'js'),
]);
$template->assign('basePathApi', URL_ADMIN . '/api/');
$template->assign('rocnik', $systemoveNastaveni->rocnik());
$template->parse('shop.kfc');

$template->parse('shop');
$template->out('shop');
