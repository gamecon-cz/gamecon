<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
CREATE TABLE IF NOT EXISTS obchod_mrizky(
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `text` VARCHAR(255) NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE = InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

CREATE TABLE IF NOT EXISTS obchod_bunky(
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `typ` TINYINT(4) NOT NULL COMMENT '0-předmět, 1-stránka, 2-zpět, 3-shrnutí',
  `text` VARCHAR(255) NULL DEFAULT NULL,
  `barva` VARCHAR(255) NULL DEFAULT NULL,
  `cil_id` INT(11) NULL DEFAULT NULL COMMENT 'Id cílove mřížky nebo předmětu.',
  `mrizka_id` INT(11) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT obchod_bunky_fk_mrizky FOREIGN KEY (`mrizka_id`) REFERENCES `obchod_mrizky` (`id`)
) ENGINE = InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
SQL
);
