<?php

/** @var \Godric\DbMigrations\Migration $this */

use Gamecon\Shop\Shop;

$verejny = Shop::VEREJNY;
$podpultovy = Shop::PODPULTOVY;
$typTricko = Shop::TRICKO;

$this->q(<<<SQL
INSERT IGNORE INTO `shop_predmety`
    (`nazev`, `model_rok`, `cena_aktualni`, `stav`, `auto`, `nabizet_do`, `kusu_vyrobeno`, `typ`, `ubytovani_den`, `popis`)
VALUES
    ('Tričko červené pánské XXXL',  2022,   200.00, $podpultovy,  0, '2022-06-30 23:59:00', NULL, $typTricko, NULL, ''),
    ('Tílko modré dámské XXL',      2022,   200.00,	$podpultovy,  0, '2022-06-30 23:59:00', NULL, $typTricko, NULL, ''),
    ('Tílko červené dámské XXL',    2022,	200.00,	$podpultovy,  0, '2022-06-30 23:59:00', NULL, $typTricko, NULL, ''),
    ('Tílko účastnické dámské XXL', 2022,	250.00,	$verejny,     0, '2022-06-30 23:59:00', NULL, $typTricko, NULL, '')
SQL
);
