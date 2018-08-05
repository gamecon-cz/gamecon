<?php

/**
 * Skript definující původní stav (prázdné) databáze.
 *
 * Aplikací migrací se dostane DB do aktuálního stavu (aktuálního schématu
 * plus aktuálních fixních dat).
 */

$this->q(
<<<EOT

-- ------- --
-- Tabulky --
-- ------- --

SET NAMES utf8;
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

CREATE TABLE `akce_lokace` (
  `id_lokace` int(11) NOT NULL AUTO_INCREMENT,
  `nazev` varchar(255) COLLATE utf8_czech_ci NOT NULL,
  `nazev_interni` varchar(255) COLLATE utf8_czech_ci NOT NULL,
  `dvere` varchar(255) COLLATE utf8_czech_ci NOT NULL,
  `poznamka` text COLLATE utf8_czech_ci NOT NULL,
  `poradi` int(11) NOT NULL,
  `rok` int(11) NOT NULL,
  PRIMARY KEY (`id_lokace`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

CREATE TABLE `akce_organizatori` (
  `id_akce` int(11) NOT NULL,
  `id_uzivatele` int(11) NOT NULL COMMENT 'organizátor',
  PRIMARY KEY (`id_akce`,`id_uzivatele`),
  KEY `id_uzivatele` (`id_uzivatele`),
  CONSTRAINT `akce_organizatori_ibfk_1` FOREIGN KEY (`id_akce`) REFERENCES `akce_seznam` (`id_akce`),
  CONSTRAINT `akce_organizatori_ibfk_2` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

CREATE TABLE `akce_prihlaseni_log` (
  `id_akce` int(11) NOT NULL,
  `id_uzivatele` int(11) NOT NULL,
  `cas` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `typ` enum('prihlaseni','odhlaseni','nedostaveni_se','odhlaseni_hromadne','prihlaseni_nahradnik','prihlaseni_watchlist','odhlaseni_watchlist') COLLATE utf8_czech_ci NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

CREATE TABLE `akce_prihlaseni_stavy` (
  `id_stavu_prihlaseni` tinyint(4) NOT NULL,
  `nazev` varchar(255) COLLATE utf8_czech_ci NOT NULL,
  `platba_procent` float NOT NULL DEFAULT '100',
  PRIMARY KEY (`id_stavu_prihlaseni`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

CREATE TABLE `akce_seznam` (
  `id_akce` int(11) NOT NULL AUTO_INCREMENT,
  `patri_pod` int(11) NOT NULL,
  `nazev_akce` varchar(255) COLLATE utf8_czech_ci NOT NULL,
  `url_akce` varchar(64) COLLATE utf8_czech_ci DEFAULT NULL,
  `zacatek` datetime DEFAULT NULL,
  `konec` datetime DEFAULT NULL,
  `lokace` int(11) NOT NULL,
  `kapacita` int(11) NOT NULL,
  `kapacita_f` int(11) NOT NULL,
  `kapacita_m` int(11) NOT NULL,
  `cena` int(11) NOT NULL,
  `bez_slevy` tinyint(1) NOT NULL COMMENT 'na aktivitu se neuplatňují slevy',
  `nedava_slevu` tinyint(1) NOT NULL COMMENT 'aktivita negeneruje organizátorovi slevu',
  `typ` int(11) NOT NULL,
  `dite` varchar(64) COLLATE utf8_czech_ci DEFAULT NULL COMMENT 'potomci oddělení čárkou',
  `rok` int(11) NOT NULL,
  `stav` tinyint(1) NOT NULL COMMENT '0-v přípravě 1-aktivní 2-proběhnuté 3-systémové(deprec) 4-viditelné,nepřihlašovatelné 5-připravené k aktivaci',
  `teamova` tinyint(1) NOT NULL,
  `team_min` int(11) DEFAULT NULL COMMENT 'minimální velikost teamu',
  `team_max` int(11) DEFAULT NULL COMMENT 'maximální velikost teamu',
  `zamcel` int(11) DEFAULT NULL COMMENT 'případně kdo zamčel aktivitu pro svůj team',
  `popis` varchar(10) NOT NULL,
  PRIMARY KEY (`id_akce`),
  UNIQUE KEY `url_akce_rok_typ` (`url_akce`,`rok`,`typ`),
  KEY `rok` (`rok`),
  KEY `patri_pod` (`patri_pod`),
  KEY `lokace` (`lokace`),
  KEY `typ` (`typ`),
  KEY `stav` (`stav`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

CREATE TABLE `akce_prihlaseni` (
  `id_akce` int(11) NOT NULL,
  `id_uzivatele` int(11) NOT NULL,
  `id_stavu_prihlaseni` tinyint(4) NOT NULL,
  PRIMARY KEY (`id_akce`,`id_uzivatele`),
  KEY `id_uzivatele` (`id_uzivatele`),
  KEY `id_stavu_prihlaseni` (`id_stavu_prihlaseni`),
  CONSTRAINT `akce_prihlaseni_ibfk_1` FOREIGN KEY (`id_akce`) REFERENCES `akce_seznam` (`id_akce`),
  CONSTRAINT `akce_prihlaseni_ibfk_2` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`),
  CONSTRAINT `akce_prihlaseni_ibfk_3` FOREIGN KEY (`id_stavu_prihlaseni`) REFERENCES `akce_prihlaseni_stavy` (`id_stavu_prihlaseni`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

CREATE TABLE `akce_prihlaseni_spec` (
  `id_akce` int(11) NOT NULL,
  `id_uzivatele` int(11) NOT NULL,
  `id_stavu_prihlaseni` tinyint(4) NOT NULL,
  PRIMARY KEY (`id_akce`,`id_uzivatele`),
  KEY `id_uzivatele` (`id_uzivatele`),
  KEY `id_stavu_prihlaseni` (`id_stavu_prihlaseni`),
  CONSTRAINT `akce_prihlaseni_spec_ibfk_1` FOREIGN KEY (`id_akce`) REFERENCES `akce_seznam` (`id_akce`),
  CONSTRAINT `akce_prihlaseni_spec_ibfk_2` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`),
  CONSTRAINT `akce_prihlaseni_spec_ibfk_3` FOREIGN KEY (`id_stavu_prihlaseni`) REFERENCES `akce_prihlaseni_stavy` (`id_stavu_prihlaseni`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

CREATE TABLE `akce_typy` (
  `id_typu` int(11) NOT NULL,
  `typ_1p` varchar(32) COLLATE utf8_czech_ci NOT NULL,
  `typ_1pmn` varchar(32) COLLATE utf8_czech_ci NOT NULL,
  `typ_2pmn` varchar(32) COLLATE utf8_czech_ci NOT NULL,
  `typ_6p` varchar(32) COLLATE utf8_czech_ci NOT NULL,
  `url_typu` varchar(32) COLLATE utf8_czech_ci NOT NULL,
  `url_typu_mn` varchar(32) COLLATE utf8_czech_ci NOT NULL,
  `stranka_o` int(11) NOT NULL COMMENT 'id stranky "O rpg na GC" apod.',
  `titul_orga` varchar(32) COLLATE utf8_czech_ci NOT NULL,
  `poradi` int(11) NOT NULL,
  PRIMARY KEY (`id_typu`),
  KEY `stranka_o` (`stranka_o`),
  CONSTRAINT `akce_typy_ibfk_1` FOREIGN KEY (`stranka_o`) REFERENCES `stranky` (`id_stranky`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

CREATE TABLE `platby` (
  `id_platby` int(11) NOT NULL AUTO_INCREMENT COMMENT 'kvůli indexu a vícenásobným platbám',
  `id_uzivatele` int(11) NOT NULL,
  `castka` decimal(6,2) NOT NULL,
  `rok` smallint(6) NOT NULL,
  `provedeno` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `provedl` int(11) NOT NULL,
  `poznamka` text COLLATE utf8_czech_ci,
  PRIMARY KEY (`id_platby`),
  KEY `id_platby` (`id_platby`),
  CONSTRAINT `platby_ibfk_1` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

CREATE TABLE novinky_obsah (
  id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  autor int,
  stav char(1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

CREATE TABLE `r_prava_soupis` (
  `id_prava` int(11) NOT NULL AUTO_INCREMENT,
  `jmeno_prava` varchar(255) COLLATE utf8_czech_ci NOT NULL,
  `popis_prava` text COLLATE utf8_czech_ci NOT NULL,
  PRIMARY KEY (`id_prava`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

CREATE TABLE `r_prava_zidle` (
  `id_zidle` int(11) NOT NULL,
  `id_prava` int(11) NOT NULL,
  PRIMARY KEY (`id_zidle`,`id_prava`),
  KEY `id_prava` (`id_prava`),
  CONSTRAINT `r_prava_zidle_ibfk_1` FOREIGN KEY (`id_prava`) REFERENCES `r_prava_soupis` (`id_prava`),
  CONSTRAINT `r_prava_zidle_ibfk_2` FOREIGN KEY (`id_zidle`) REFERENCES `r_zidle_soupis` (`id_zidle`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

CREATE TABLE `r_uzivatele_zidle` (
  `id_uzivatele` int(11) NOT NULL,
  `id_zidle` int(11) NOT NULL,
  `posazen` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_uzivatele`,`id_zidle`),
  KEY `id_zidle` (`id_zidle`),
  CONSTRAINT `r_uzivatele_zidle_ibfk_1` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`),
  CONSTRAINT `r_uzivatele_zidle_ibfk_2` FOREIGN KEY (`id_zidle`) REFERENCES `r_zidle_soupis` (`id_zidle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

CREATE TABLE `r_zidle_soupis` (
  `id_zidle` int(11) NOT NULL AUTO_INCREMENT,
  `jmeno_zidle` varchar(255) COLLATE utf8_czech_ci NOT NULL,
  `popis_zidle` text COLLATE utf8_czech_ci NOT NULL,
  PRIMARY KEY (`id_zidle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

CREATE TABLE `shop_predmety` (
  `id_predmetu` int(11) NOT NULL AUTO_INCREMENT,
  `nazev` varchar(255) COLLATE utf8_czech_ci NOT NULL,
  `model_rok` smallint(6) NOT NULL,
  `cena_aktualni` decimal(6,2) NOT NULL,
  `stav` tinyint(4) NOT NULL COMMENT '0-mimo, 1-veřejný, 2-podpultový, 3-pozastavený',
  `auto` tinyint(4) NOT NULL COMMENT 'automaticky objednané',
  `kusu_vyrobeno` smallint(6) DEFAULT NULL,
  `typ` tinyint(4) NOT NULL COMMENT '1-předmět, 2-ubytování, 3-tričko, 4-jídlo, 5-vstupné',
  `ubytovani_den` tinyint(4) DEFAULT NULL COMMENT 'změněn význam na "obecný atribut den"',
  `popis` varchar(2000) COLLATE utf8_czech_ci NOT NULL,
  PRIMARY KEY (`id_predmetu`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

CREATE TABLE `shop_nakupy` (
  `id_uzivatele` int(11) NOT NULL,
  `id_predmetu` int(11) NOT NULL,
  `rok` smallint(6) NOT NULL,
  `cena_nakupni` decimal(6,2) NOT NULL COMMENT 'aktuální cena v okamžiku nákupu (bez slev)',
  `datum` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `rok_id_uzivatele` (`rok`,`id_uzivatele`),
  KEY `id_predmetu` (`id_predmetu`),
  KEY `id_uzivatele` (`id_uzivatele`),
  CONSTRAINT `shop_nakupy_ibfk_1` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`),
  CONSTRAINT `shop_nakupy_ibfk_2` FOREIGN KEY (`id_predmetu`) REFERENCES `shop_predmety` (`id_predmetu`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

CREATE TABLE `stranky` (
  `id_stranky` int(11) NOT NULL AUTO_INCREMENT,
  `url_stranky` varchar(64) COLLATE utf8_czech_ci NOT NULL,
  `obsah` longtext COLLATE utf8_czech_ci NOT NULL COMMENT 'markdown',
  PRIMARY KEY (`id_stranky`),
  UNIQUE KEY `url_stranky` (`url_stranky`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

CREATE TABLE `ubytovani` (
  `id_uzivatele` int(11) NOT NULL,
  `den` tinyint(4) NOT NULL,
  `pokoj` varchar(255) COLLATE utf8_czech_ci NOT NULL,
  `rok` smallint(6) NOT NULL,
  PRIMARY KEY (`rok`,`id_uzivatele`,`den`),
  KEY `id_uzivatele` (`id_uzivatele`),
  CONSTRAINT `ubytovani_ibfk_1` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

CREATE TABLE `uzivatele_hodnoty` (
  `id_uzivatele` int(11) NOT NULL AUTO_INCREMENT,
  `login_uzivatele` varchar(255) COLLATE utf8_czech_ci NOT NULL,
  `jmeno_uzivatele` varchar(100) COLLATE utf8_czech_ci NOT NULL,
  `prijmeni_uzivatele` varchar(100) COLLATE utf8_czech_ci NOT NULL,
  `ulice_a_cp_uzivatele` varchar(255) COLLATE utf8_czech_ci NOT NULL,
  `mesto_uzivatele` varchar(100) COLLATE utf8_czech_ci NOT NULL,
  `stat_uzivatele` int(11) NOT NULL,
  `psc_uzivatele` varchar(20) COLLATE utf8_czech_ci NOT NULL,
  `telefon_uzivatele` varchar(100) COLLATE utf8_czech_ci NOT NULL,
  `datum_narozeni` date NOT NULL,
  `heslo_md5` varchar(255) CHARACTER SET ucs2 COLLATE ucs2_czech_ci NOT NULL COMMENT 'přechází se na password_hash',
  `funkce_uzivatele` tinyint(4) NOT NULL,
  `email1_uzivatele` varchar(255) COLLATE utf8_czech_ci NOT NULL,
  `email2_uzivatele` varchar(255) COLLATE utf8_czech_ci NOT NULL,
  `jine_uzivatele` text COLLATE utf8_czech_ci NOT NULL,
  `mrtvy_mail` tinyint(4) NOT NULL,
  `forum_razeni` varchar(1) COLLATE utf8_czech_ci NOT NULL,
  `random` varchar(20) COLLATE utf8_czech_ci NOT NULL,
  `zustatek` int(11) NOT NULL COMMENT 'zbytek z minulého roku',
  `pohlavi` enum('m','f') COLLATE utf8_czech_ci NOT NULL,
  `registrovan` datetime NOT NULL,
  `ubytovan_s` varchar(255) COLLATE utf8_czech_ci DEFAULT NULL,
  `skola` varchar(255) COLLATE utf8_czech_ci DEFAULT NULL,
  `poznamka` varchar(4096) COLLATE utf8_czech_ci NOT NULL,
  `pomoc_typ` varchar(64) COLLATE utf8_czech_ci NOT NULL,
  `pomoc_vice` text COLLATE utf8_czech_ci NOT NULL,
  souhlas_maily tinyint NOT NULL,
  guru int,
  PRIMARY KEY (`id_uzivatele`),
  UNIQUE KEY `login_uzivatele` (`login_uzivatele`),
  UNIQUE KEY `email1_uzivatele` (`email1_uzivatele`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

SET foreign_key_checks = 1;

-- ----------------- --
-- Data (relevantní) --
-- ----------------- --

-- TODO

-- ------------------------------------------ --
-- Data (nerelevantní, ale nutné pro migrace) --
-- ------------------------------------------ --

INSERT INTO stranky (id_stranky) VALUES (77);

EOT
);
