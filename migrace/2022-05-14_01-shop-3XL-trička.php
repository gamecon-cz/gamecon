<?php

use Gamecon\Shop\Shop;

/** @var \Godric\DbMigrations\Migration $this */

$verejny = Shop::VEREJNY;
$podpultovy = Shop::PODPULTOVY;

$this->q(<<<SQL
INSERT IGNORE INTO `shop_predmety`
    (`nazev`, `model_rok`, `cena_aktualni`, `stav`, `auto`, `nabizet_do`, `kusu_vyrobeno`, `typ`, `ubytovani_den`, `popis`)
VALUES
    ('Tričko modré pánské XXXL', 2022, 200.00, $podpultovy, 0, '2022-06-30 23:59:00', NULL, 3, NULL, ''),
    ('Tričko účastnické pánské XXXL', 2022, 250.00, $verejny, 0, '2022-06-30 23:59:00', NULL, 3, NULL, '')
SQL
);
