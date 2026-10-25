<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Vzhled administrace, který si uživatel vybral (klasický, nebo moderní). Kdo tu řádek nemá, ještě si
// nevybral a admin mu výběr nabídne. Cizí klíč nechá volbu převést při slučování uživatelů
// (UzivatelSlucovani hledá odkazující tabulky podle FK) a při smazání účtu ji smaže.
$this->q(<<<'SQL'
CREATE TABLE IF NOT EXISTS `uzivatele_vzhled_adminu` (
  `id_uzivatele` bigint(20) unsigned NOT NULL,
  `vzhled`       varchar(32) NOT NULL,
  `zmeneno_kdy`  timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_uzivatele`),
  CONSTRAINT `FK_uzivatele_vzhled_adminu_uzivatel` FOREIGN KEY (`id_uzivatele`)
    REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_czech_ci
SQL);
