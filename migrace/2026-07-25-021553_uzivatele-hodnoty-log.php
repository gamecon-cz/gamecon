<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Audit změn osobních údajů uživatele (tabulka uzivatele_hodnoty).
// Zaznamenává diff po sloupcích: kdo (id_zmenil), u koho (id_uzivatele),
// který sloupec, stará → nová hodnota, odkud změna přišla (zdroj_zmeny), kdy.
$this->q("
CREATE TABLE IF NOT EXISTS `uzivatele_hodnoty_log` (
  `id_log` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_uzivatele` int(11) NOT NULL,
  `id_zmenil` int(11) DEFAULT NULL,
  `sloupec` varchar(64) COLLATE utf8_czech_ci NOT NULL,
  `stara_hodnota` text COLLATE utf8_czech_ci DEFAULT NULL,
  `nova_hodnota` text COLLATE utf8_czech_ci DEFAULT NULL,
  `zdroj_zmeny` varchar(128) COLLATE utf8_czech_ci DEFAULT NULL,
  `kdy` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_log`),
  KEY `id_uzivatele` (`id_uzivatele`),
  KEY `id_zmenil` (`id_zmenil`),
  KEY `kdy` (`kdy`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci
");
