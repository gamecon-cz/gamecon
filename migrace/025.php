<?php

$this->q("

DELETE FROM `r_prava_zidle` WHERE `id_prava` = '1017';
DELETE FROM `r_prava_soupis` WHERE `id_prava` = '1017';
DELETE FROM `akce_typy` WHERE `akce_typy`.`id_typu` = 3;
DELETE FROM `stranky` WHERE `id_stranky` = '28';
DELETE FROM `stranky` WHERE `id_stranky` = '77';
DELETE FROM `stranky` WHERE `id_stranky` = '94';

-- vyřadit parcon z nabídky 'předmětů'
UPDATE shop_predmety SET stav = 0 WHERE typ = 6;

-- odstranit parcon z komentáře sloupce
ALTER TABLE `shop_predmety`
CHANGE `typ` `typ` tinyint(4) NOT NULL COMMENT '1-předmět, 2-ubytování, 3-tričko, 4-jídlo, 5-vstupné' AFTER `kusu_vyrobeno`;

");
