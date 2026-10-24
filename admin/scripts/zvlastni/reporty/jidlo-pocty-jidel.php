<?php
/**
 * Kolik se má uvařit kterého jídla z jídelníčku. Porce bez výběru se počítají k výchozímu jídlu.
 */
require __DIR__ . '/sdilene-hlavicky.php';

use Gamecon\Report\JidlaZJidelnicku;

/** @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */

Report::zPoli(JidlaZJidelnicku::HLAVICKY_POCTU, (new JidlaZJidelnicku())->poctyJidel($systemoveNastaveni->rocnik()))
    ->tFormat(get('format'));
