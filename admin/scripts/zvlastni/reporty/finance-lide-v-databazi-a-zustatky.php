<?php
require __DIR__ . '/sdilene-hlavicky.php';

use Gamecon\Report\FinanceLideVDatabaziAZustatky;

/** @var $systemoveNastaveni \Gamecon\SystemoveNastaveni\SystemoveNastaveni */

(new FinanceLideVDatabaziAZustatky($systemoveNastaveni))->exportuj(get('format'));
