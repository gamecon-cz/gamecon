<?php
/**
 * Co dostane který účastník k obědům a večeřím: vybrané jídlo, jinak výchozí.
 */
require __DIR__ . '/sdilene-hlavicky.php';

use Gamecon\Report\JidlaZJidelnicku;

/** @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */

Report::zPoli(JidlaZJidelnicku::HLAVICKY_VYBERU, (new JidlaZJidelnicku())->vyberUcastniku($systemoveNastaveni->rocnik()))
    ->tFormat(get('format'));
