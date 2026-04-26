<?php

$this->q(<<<SQL
CREATE TABLE `akce_tym` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `kod` int(6) UNSIGNED NOT NULL,
  `nazev` varchar(255) COLLATE utf8_czech_ci DEFAULT NULL,
  `limit` int(11) DEFAULT NULL COMMENT 'uživatelem (vedoucím týmu) nastavený limit kapacity menší roven team_max, ale větší roven team_min. ',
  `id_kapitan` bigint(20) UNSIGNED NOT NULL,
  `zalozen` datetime DEFAULT NULL COMMENT 'používané pro automatické uveřejnění',
  `verejny` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY (`kod`),
  FOREIGN KEY (`id_kapitan`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb3
  COLLATE=utf8mb3_czech_ci;
SQL,
);

$this->q(<<<SQL
CREATE TABLE `akce_tym_prihlaseni` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_uzivatele` bigint(20) UNSIGNED NOT NULL,
  `id_tymu` bigint(20) UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE (`id_uzivatele`, `id_tymu`),
  KEY (`id_uzivatele`),
  KEY (`id_tymu`),
  FOREIGN KEY (`id_tymu`) REFERENCES `akce_tym` (`id`),
  FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb3
  COLLATE=utf8mb3_czech_ci;
SQL,
);

