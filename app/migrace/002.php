<?php

// Přidání tabulky pro medailonky vypravěčů

$this->q("

DROP TABLE IF EXISTS medailonky;

CREATE TABLE `medailonky` (
  `id` int NOT NULL COMMENT 'id uživatele',
  `o_sobe` mediumtext NOT NULL COMMENT 'markdown',
  `drd` mediumtext NOT NULL COMMENT 'markdown -- profil pro DrD'
);

ALTER TABLE `medailonky`
ADD PRIMARY KEY `id` (`id`);

ALTER TABLE uzivatele_hodnoty ENGINE = innodb;

ALTER TABLE `medailonky`
ADD FOREIGN KEY (`id`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON DELETE RESTRICT ON UPDATE CASCADE;

");

if(!is_dir(WWW.'/soubory/systemove/fotky')) mkdir(WWW.'/soubory/systemove/fotky');
