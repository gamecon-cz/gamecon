<?php

// Přidání tabulky pro medailonky vypravěčů

$this->q("

CREATE TABLE `reporty` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `nazev` varchar(100) NOT NULL,
  `dotaz` text NOT NULL
);

");
