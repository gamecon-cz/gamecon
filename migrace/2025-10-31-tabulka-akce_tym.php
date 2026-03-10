<?php

$this->q(<<<SQL
CREATE TABLE `akce_tym` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_akce` bigint(20) UNSIGNED NOT NULL,
  `id_uzivatele` bigint(20) UNSIGNED NOT NULL,
  `kod_tymu` int(6) UNSIGNED NOT NULL,
  `team_nazev` varchar(255) COLLATE utf8_czech_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE (`id_akce`, `id_uzivatele`),
  KEY (`id_akce`),
  KEY (`id_uzivatele`),
  KEY (`kod_tymu`),
  FOREIGN KEY (`id_akce`) REFERENCES `akce_seznam` (`id_akce`),
  FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb3
  COLLATE=utf8mb3_czech_ci;
SQL,
);

