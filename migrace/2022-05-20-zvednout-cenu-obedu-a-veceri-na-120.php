<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
UPDATE`shop_predmety`
SET nabizet_do = NOW(), stav = 0
WHERE TRIM(nazev) LIKE 'Oběd %' OR TRIM(nazev) LIKE 'Večeře %' COLLATE utf8_czech_ci
AND model_rok = 2022 AND cena_aktualni = 100
SQL
);

$this->q(<<<SQL
INSERT INTO `shop_predmety` (`nazev`, `model_rok`, `cena_aktualni`, `stav`, `auto`, `nabizet_do`, `kusu_vyrobeno`, `typ`, `ubytovani_den`, `popis`)
VALUES
('Večeře neděle', 2022,  120, 1, 0,  '2022-07-11 23:59:00',  NULL, 4, 4,  ''),
('Oběd neděle', 2022,  120, 1, 0,  '2022-07-11 23:59:00',  NULL, 4, 4,  ''),
('Večeře sobota', 2022,  120, 1, 0,  '2022-07-11 23:59:00',  NULL, 4, 3,  ''),
('Oběd sobota', 2022,  120, 1, 0,  '2022-07-11 23:59:00',  NULL, 4, 3,  ''),
('Večeře pátek', 2022,  120, 1, 0,  '2022-07-11 23:59:00',  NULL, 4, 2,  ''),
('Oběd pátek', 2022,  120, 1, 0,  '2022-07-11 23:59:00',  NULL, 4, 2,  ''),
('Večeře čtvrtek', 2022,  120, 1, 0,  '2022-07-11 23:59:00',  NULL, 4, 1,  ''),
('Oběd čtvrtek', 2022,  120, 1, 0,  '2022-07-11 23:59:00',  NULL, 4, 1,  '');
SQL
);
