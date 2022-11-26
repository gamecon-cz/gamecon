<?php

use Gamecon\Shop\Shop;
use Gamecon\XTemplate\XTemplate;

$covidSekce = static function (Shop $shop): string {
    $covidTemplate = new XTemplate(__DIR__ . '/covid-sekce.xtpl');
    $covidTemplate->assign('covidFreePotvrzeni', $shop->covidFreePotrvzeniHtml((int)date('Y')));
    $covidTemplate->parse('covid');
    return $covidTemplate->text('covid');
};

return $covidSekce;
