<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Vlastní znění upomínky, které si CFO napíše v adminu. Nepatří do
// systemove_nastaveni - jeho `hodnota` je varchar(255), tedy kratší než jeden
// odstavec mailu. Drží se jeden řádek na ročník, ať se loňský text nepřepíše.
$this->q('
CREATE TABLE IF NOT EXISTS `upominka_vlastni_zneni` (
  `rocnik`      smallint(6) NOT NULL,
  `predmet`     varchar(255) COLLATE utf8mb4_czech_ci NOT NULL,
  `text`        text COLLATE utf8mb4_czech_ci NOT NULL,
  `zmenil`      int(11) DEFAULT NULL,
  `zmeneno_kdy` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`rocnik`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_czech_ci
');
