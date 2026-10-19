<?php
require __DIR__ . '/sdilene-hlavicky.php';

/** @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */

(new \Gamecon\Shop\EshopExport($systemoveNastaveni->rocnik()))->report()->tFormat(get('format'));
