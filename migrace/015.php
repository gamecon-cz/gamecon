<?php

$this->q("

ALTER TABLE `shop_predmety`
CHANGE `typ` `typ` tinyint(4) NOT NULL COMMENT '1-předmět, 2-ubytování, 3-tričko, 4-jídlo, 5-vstupné, 6-parcon' AFTER `kusu_vyrobeno`;

INSERT INTO `shop_predmety` (`nazev`, `model_rok`, `cena_aktualni`, `stav`, `auto`, `nabizet_do`, `kusu_vyrobeno`, `typ`, `ubytovani_den`, `popis`)
VALUES ('Parcon', '2017', '150', '1', '0', NULL, NULL, '6', NULL, '');

INSERT INTO `r_prava_soupis` (`id_prava`, `jmeno_prava`, `popis_prava`)
VALUES ('1017', 'Parcon zdarma', 'Vstup na Parcon má zdarma');

");
