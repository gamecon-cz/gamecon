<?php
/** @var \Godric\DbMigrations\Migration $this */

$jidlo = \Gamecon\Shop\TypPredmetu::JIDLO;
$this->q(<<<SQL
UPDATE shop_predmety
SET nabizet_do = '2022-07-18 00:00:00'
WHERE model_rok = 2022
AND typ = $jidlo
AND stav = 1
SQL
);
