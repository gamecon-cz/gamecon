<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Jak se vzhledy administrace ujaly: kolik stránek kdo který den viděl v kterém vzhledu, a jestli ho měl
// zvolený, nebo výchozí, protože si ještě nevybral. Zapisuje VzhledAdminu::zaznamenejZobrazeniStranky(), čtou
// reporty vzhled-adminu-uzivatele a vzhled-adminu-dny. Cizí klíč jako u uzivatele_vzhled_adminu: UzivatelSlucovani
// řádky převede (mají-li oba sloučení uživatelé záznam ze stejného dne, zůstane ten uživatele, do kterého se
// slučuje) a smazání účtu je smaže.
$this->q(<<<'SQL'
CREATE TABLE IF NOT EXISTS `uzivatele_vzhled_adminu_pouziti` (
  `id_uzivatele`    bigint(20) unsigned NOT NULL,
  `den`             date NOT NULL,
  `vzhled`          varchar(32) NOT NULL,
  `zvoleny`         tinyint(1) NOT NULL DEFAULT 0,
  `pocet_zobrazeni` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_uzivatele`, `den`, `vzhled`),
  KEY `den` (`den`),
  CONSTRAINT `FK_uzivatele_vzhled_adminu_pouziti_uzivatel` FOREIGN KEY (`id_uzivatele`)
    REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_czech_ci
SQL);

$this->q(<<<'SQL'
INSERT INTO reporty (skript, nazev, format_xlsx, format_html, viditelny)
VALUES
    ('vzhled-adminu-uzivatele', 'Vzhled administrace: kdo jaký používá', 1, 1, 1),
    ('vzhled-adminu-dny', 'Vzhled administrace: po dnech', 1, 1, 1)
SQL);
