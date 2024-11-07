<?php
require __DIR__ . '/sdilene-hlavicky.php';

use Gamecon\Report\StavyUctuUcastniku;

/** @var $systemoveNastaveni \Gamecon\SystemoveNastaveni\SystemoveNastaveni */

(new StavyUctuUcastniku($systemoveNastaveni))->exportuj(get('format'));
