<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
ALTER TABLE `shop_predmety`
CHANGE `typ` `typ` TINYINT NOT NULL COMMENT '1-předmět, 2-ubytování, 3-tričko, 4-jídlo, 5-vstupné, 6-parcon, 7-vyplaceni' AFTER `kusu_vyrobeno`;

INSERT INTO `shop_predmety` (`nazev`, `model_rok`, `cena_aktualni`, `stav`, `auto`, `nabizet_do`, `kusu_vyrobeno`, `typ`, `ubytovani_den`, `popis`)
VALUES ('Proplacení bonusu', 2019, 0, 1, 0, NULL, NULL, 7, NULL, 'Pro vyplacení bonusů za vedení aktivit')
SQL
);

