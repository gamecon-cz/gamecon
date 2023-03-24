<?php

/**
 * Skript definující původní stav (prázdné) databáze.
 *
 * Aplikací migrací se dostane DB do aktuálního stavu (aktuálního schématu
 * plus aktuálních fixních dat).
 */

$this->q(<<<SQL

CREATE TABLE IF NOT EXISTS `_vars`
(
    `name` varchar(64) NOT NULL
        PRIMARY KEY,
    `value` varchar(4096) NULL
)
    COLLATE=utf8_czech_ci;

CREATE TABLE IF NOT EXISTS `db_migrations`
(
    `name` varchar(200) NOT NULL
        PRIMARY KEY,
    `value` varchar(5000) NULL
)
    COLLATE=utf8_czech_ci;

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

INSERT INTO `r_prava_soupis` (`id_prava`, `jmeno_prava`, `popis_prava`) VALUES
(-2202, 'GC2022 přítomen',  ''),
(-2201, 'GC2022 přihlášen', ''),
(-2102, 'GC2021 přítomen',  ''),
(-2101, 'GC2021 přihlášen', ''),
(-2002, 'GC2020 přítomen',  ''),
(-2001, 'GC2020 přihlášen', ''),
(-1902, 'GC2019 přítomen',  ''),
(-1901, 'GC2019 přihlášen', ''),
(-1802, 'GC2018 přítomen',  ''),
(-1801, 'GC2018 přihlášen', ''),
(-1702, 'GC2017 přítomen',  ''),
(-1701, 'GC2017 přihlášen', ''),
(-1602, 'GC2016 přítomen',  ''),
(-1601, 'GC2016 přihlášen', ''),
(-1502, 'GC2015 přítomen',  ''),
(-1501, 'GC2015 přihlášen', ''),
(-1402, 'GC2014 přítomen',  ''),
(-1401, 'GC2014 přihlášen', ''),
(-1302, 'GC2013 přítomen',  ''),
(-1301, 'GC2013 přihlášen', ''),
(-1202, 'GC2012 přítomen',  ''),
(-1201, 'GC2012 přihlášen', ''),
(-1102, 'GC2011 přítomen',  ''),
(-1101, 'GC2011 přihlášen', ''),
(-1002, 'GC2010 přítomen',  ''),
(-1001, 'GC2010 přihlášen', ''),
(-902,  'GC2009 přítomen',  ''),
(-901,  'GC2009 přihlášen', '');

INSERT INTO `r_zidle_soupis` (`id_zidle`, `jmeno_zidle`, `popis_zidle`) VALUES
(-2202, 'GC2022 přítomen',  ''),
(-2201, 'GC2022 přihlášen', ''),
(-2102, 'GC2021 přítomen',  ''),
(-2101, 'GC2021 přihlášen', ''),
(-2002, 'GC2020 přítomen',  ''),
(-2001, 'GC2020 přihlášen', ''),
(-1902, 'GC2019 přítomen',  ''),
(-1901, 'GC2019 přihlášen', ''),
(-1802, 'GC2018 přítomen',  ''),
(-1801, 'GC2018 přihlášen', ''),
(-1702, 'GC2017 přítomen',  ''),
(-1701, 'GC2017 přihlášen', ''),
(-1602, 'GC2016 přítomen',  ''),
(-1601, 'GC2016 přihlášen', ''),
(-1502, 'GC2015 přítomen',  ''),
(-1501, 'GC2015 přihlášen', ''),
(-1403, 'Odjel',  ''),
(-1402, 'GC2014 přítomen',  ''),
(-1401, 'GC2014 přihlášen', ''),
(-1302, 'GC2013 přítomen',  ''),
(-1301, 'GC2013 přihlášen', ''),
(-1202, 'GC2012 přítomen',  ''),
(-1201, 'GC2012 přihlášen', ''),
(-1102, 'GC2011 přítomen',  ''),
(-1101, 'GC2011 přihlášen', ''),
(-1002, 'GC2010 přítomen',  ''),
(-1001, 'GC2010 přihlášen', ''),
(-902,  'GC2009 přítomen',  ''),
(-901,  'GC2009 přihlášen', '');


INSERT INTO `r_prava_zidle` (`id_zidle`, `id_prava`) VALUES
(-2202, -2202),
(-2201, -2201),
(-2102, -2102),
(-2101, -2101),
(-2002, -2002),
(-2001, -2001),
(-1902, -1902),
(-1901, -1901),
(-1802, -1802),
(-1801, -1801),
(-1702, -1702),
(-1701, -1701),
(-1602, -1602),
(-1601, -1601),
(-1502, -1502),
(-1501, -1501),
(-1402, -1402),
(-1401, -1401),
(-1302, -1302),
(-1301, -1301),
(-1202, -1202),
(-1201, -1201),
(-1102, -1102),
(-1101, -1101),
(-1002, -1002),
(-1001, -1001),
(-902,  -902),
(-901,  -901);

INSERT INTO `akce_prihlaseni_stavy` (`id_stavu_prihlaseni`, `nazev`, `platba_procent`) VALUES
(0, 'přihlášen',  100),
(1, 'dorazil',  100),
(2, 'dorazil (náhradník)',  100),
(3, 'nedorazil',  100),
(4, 'pozdě zrušil', 50);

-- ------------------------------------------ --
-- Data (nerelevantní, ale nutné pro migrace) --
-- ------------------------------------------ --

INSERT INTO stranky (id_stranky) VALUES (77);

SQL
);
