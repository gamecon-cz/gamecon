<?php

// Vytvoři tabulku pro uživatelské slevy
$this->q("
CREATE TABLE `slevy` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `id_uzivatele` int(11) NOT NULL,
  `castka` decimal(6,2) NOT NULL,
  `rok` int NOT NULL,
  `provedeno` datetime NOT NULL,
  `provedl` int(11) NOT NULL,
  `poznamka` text NULL,
  FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON DELETE RESTRICT,
  FOREIGN KEY (`provedl`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`)
) ENGINE='InnoDB' COLLATE 'utf8_czech_ci';");
