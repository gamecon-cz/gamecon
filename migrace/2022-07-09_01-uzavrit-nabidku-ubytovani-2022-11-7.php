<?php
/** @var \Godric\DbMigrations\Migration $this */

$ubytovani = \Gamecon\Shop\TypPredmetu::UBYTOVANI;
$this->q(<<<SQL
UPDATE shop_predmety
SET nabizet_do = '2022-07-10 23:59:59'
WHERE model_rok = 2022
AND typ = $ubytovani
SQL
);
