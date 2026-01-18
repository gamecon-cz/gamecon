/*M!999999\- enable the sandbox mode */
-- MariaDB dump 10.19-11.6.2-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: 127.0.0.1    Database: gamecon_test_6782587d36bad4.57234391
-- ------------------------------------------------------
-- Server version	10.6.19-MariaDB-ubu2004

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;

--
-- Table structure for table `_vars`
--

DROP TABLE IF EXISTS `_vars`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `_vars` (
  `name` varchar(64) NOT NULL,
  `value` varchar(4096) DEFAULT NULL,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `_vars`
--

/*!40000 ALTER TABLE `_vars` DISABLE KEYS */;
/*!40000 ALTER TABLE `_vars` ENABLE KEYS */;

--
-- Table structure for table `akce_import`
--

DROP TABLE IF EXISTS `akce_import`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `akce_import` (
  `id_akce_import` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_uzivatele` int(11) NOT NULL,
  `google_sheet_id` varchar(128) NOT NULL,
  `cas` datetime NOT NULL,
  UNIQUE KEY `id_akce_import` (`id_akce_import`),
  KEY `google_sheet_id` (`google_sheet_id`),
  KEY `FK_akce_import_to_uzivatele_hodnoty` (`id_uzivatele`),
  CONSTRAINT `FK_akce_import_to_uzivatele_hodnoty` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON DELETE NO ACTION ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `akce_import`
--

/*!40000 ALTER TABLE `akce_import` DISABLE KEYS */;
/*!40000 ALTER TABLE `akce_import` ENABLE KEYS */;

--
-- Table structure for table `akce_instance`
--

DROP TABLE IF EXISTS `akce_instance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `akce_instance` (
  `id_instance` int(11) NOT NULL AUTO_INCREMENT,
  `id_hlavni_akce` int(11) NOT NULL,
  PRIMARY KEY (`id_instance`),
  KEY `FK_akce_instance_to_akce_seznam` (`id_hlavni_akce`),
  CONSTRAINT `FK_akce_instance_to_akce_seznam` FOREIGN KEY (`id_hlavni_akce`) REFERENCES `akce_seznam` (`id_akce`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `akce_instance`
--

/*!40000 ALTER TABLE `akce_instance` DISABLE KEYS */;
/*!40000 ALTER TABLE `akce_instance` ENABLE KEYS */;

--
-- Table structure for table `akce_lokace`
--

DROP TABLE IF EXISTS `akce_lokace`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `akce_lokace` (
  `id_lokace` int(11) NOT NULL AUTO_INCREMENT,
  `nazev` varchar(255) NOT NULL,
  `dvere` varchar(255) NOT NULL,
  `poznamka` text NOT NULL,
  `poradi` int(11) NOT NULL,
  `rok` int(11) DEFAULT 0,
  PRIMARY KEY (`id_lokace`),
  UNIQUE KEY `nazev_rok` (`nazev`,`rok`)
) ENGINE=InnoDB AUTO_INCREMENT=86 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `akce_lokace`
--

/*!40000 ALTER TABLE `akce_lokace` DISABLE KEYS */;
INSERT INTO `akce_lokace` VALUES
(1,'RPG 1','Budova C, dveře č. 1','Pokoj 3L',1,0),
(2,'RPG 2','Budova C, dveře č. 38','Pokoj 3L',2,0),
(3,'RPG 3 - 2L pokoj','Budova C, dveře č. 2','Pokoj 2L',3,0),
(4,'RPG 4','Budova C, dveře č. 37','Pokoj 3L',4,0),
(5,'RPG 5','Budova C, dveře č. 36','Pokoj 3L',5,0),
(6,'RPG 6','Budova C, dveře č. 35','Pokoj 3L',6,0),
(7,'RPG 7','Budova C, dveře č. 34','Pokoj 3L',7,0),
(8,'RPG 8','Budova C, dveře č. 33','Pokoj 3L',8,0),
(9,'RPG 9','Budova C, dveře č. 32','Pokoj 3L',9,0),
(10,'RPG 10','Budova C, dveře č. 30','Pokoj 3L',10,0),
(11,'RPG 11','Budova C, dveře č. 22','Pokoj 3L',11,0),
(12,'RPG 12','Budova C, dveře č. 21','Pokoj 3L',12,0),
(13,'RPG 13','Budova C, dveře č. 20','Pokoj 3L',13,0),
(14,'RPG 14','Budova C, dveře č. 19','Pokoj 3L',14,0),
(15,'RPG 15','Budova C, dveře č. 18','Pokoj 3L',15,0),
(16,'RPG 16','Budova C, dveře č. 17','Pokoj 3L',16,0),
(17,'RPG 17','Budova C, dveře č. 16','Pokoj 3L',17,0),
(18,'RPG 18','Budova C, dveře č. 15','Pokoj 3L',18,0),
(19,'RPG 19 - 2L pokoj','Budova C, dveře č. 101','Pokoj 2L',19,0),
(20,'LKD 1','Budova C, dveře č. 130','Pokoj 3L',20,0),
(21,'LKD 2','Budova C, dveře č. 129','Pokoj 3L',21,0),
(22,'LKD 3','Budova C, dveře č. 127','Pokoj 3L',22,0),
(23,'LKD 4','Budova C, dveře č. 126','Pokoj 3L',23,0),
(24,'LKD 5','Budova C, dveře č. 125','Pokoj 3L',24,0),
(25,'LKD 6','Budova C, dveře č. 123','Pokoj 3L',25,0),
(26,'LKD 7','Budova C, dveře č. 121','Pokoj 3L',26,0),
(27,'EPIC 1 - klubovna 0p','Budova C, dveře č. 11','Prosklená klubovna',27,0),
(28,'EPIC 2 - pokoj 0p','Budova C, dveře č. 12','Pokoj 3L',28,0),
(29,'EPIC 3 - TV místnost 0p','Budova C, dveře č. 13','TV Místnost na C',29,0),
(30,'EPIC 4 - klubovna 1p','Budova C, dveře č. 103','Prosklená klubovna',30,0),
(31,'EPIC 5 - klubovna 1p','Budova C, dveře č. 110','Prosklená klubovna',31,0),
(32,'EPIC 6 - TV místnost 1p','Budova C, dveře č. 111','TV Místnost na C',32,0),
(33,'EPIC 7 - pokoj 1p','Budova C, dveře č. 112','Pokoj 3L',33,0),
(34,'LARP 1 - dvoupokoj','Budova C, dveře č. 310+311','Dvojmístnost',38,0),
(35,'LARP 2 - snídárna','Budova B, dveře č. 27','Studentský klub',39,0),
(36,'LARP 3 - W. družina','Waldorf družina','Samostatná budova',40,0),
(37,'LARP 4 - W. altán','Waldorf altán','Zahrada družiny',41,0),
(38,'LARP 5 - DDM míčový sál','DDM, přízemí, dveře č. 74','Naproti DDM sálu',42,0),
(39,'LARP 6 - DDM 42','DDM 42, 1. patro','',43,0),
(40,'LARP 7 - DDM 36','DDM 36, 1. patro','Třída',44,0),
(41,'LARP 8 - DDM hudebna','DDM 12, 2. patro','Hudebna',45,0),
(42,'DESK 1 - předsálí kina','KD, 1. patro, předsálí up','',48,0),
(43,'DESK 2 - prosklený sál','KD, 1. patro, prosklený sál','',49,0),
(44,'DESK 3 - 2. malá vpravo','KD, 1. patro, druhá vpravo','',50,0),
(45,'DESK 4 - 1. malá vpravo','KD, 1. patro, první vpravo','',51,0),
(46,'DESK 5 - dlouhá vlevo','KD, 1. patro, vlevo','',52,0),
(47,'DESK 6 - foyer','KD, 1. patro, foyer','',53,0),
(48,'DESK 7 - sál','KD, 1. patro, taneční sál','',54,0),
(49,'DESK 8 - pódium','KD, 1. patro, pódium','',55,0),
(50,'BONUS 1 - zahrada C','Zahrada C','',56,0),
(51,'BONUS 2 - zahrada A/B','Zahrada A/B','',57,0),
(52,'BONUS 3 - venku na GC','Venku na GC','',58,0),
(53,'BONUS 4 - DDM sál','DDM Sál','Přízemí',59,0),
(54,'BONUS 5 - tělocvična','Tělocvična','Záložní tělocvična Waldorf',60,0),
(55,'mDrD 1','Budova C, dveře č. 100','Pokoj 3L',61,0),
(56,'mDrD 2','Budova C, dveře č. 135','Pokoj 3L',62,0),
(57,'mDrD 3','Budova C, dveře č. 134','Pokoj 3L',63,0),
(58,'mDrD 4','Budova C, dveře č. 102','Pokoj 3L',64,0),
(59,'mDrD 5','Budova C, dveře č. 133','Pokoj 3L',65,0),
(60,'mDrD 6','Budova C, dveře č. 132','Pokoj 3L',66,0),
(61,'mDrD 7','Budova C, dveře č. 131','Pokoj 3L',67,0),
(62,'Přednáškovka','Budova C, suterén, hudební klub','',68,0),
(63,'Prog 1 - kino','KD, 1. patro, kinosál','',69,0),
(64,'Prog 2 - předsálí DOWN','KD, přízemí, předsálí down','',70,0),
(65,'Prog 3 - mimo GC','Mimo GC','',71,0),
(66,'Prog 4 - bunkr I','Budova C, suterén, bunkr I','',72,0),
(67,'Prog 5 - bunkr B','Budova C, suterén, bunkr B','',73,0),
(68,'Prog 6 - bunkr C','Budova C, suterén, bunkr C','',74,0),
(69,'Prog 7 - bunkr D+E','Budova C, suterén, bunkr D+E','',75,0),
(70,'Prog 8 - sborovna','Budova A, dveře č. 18','',76,0),
(71,'Provoz 1 - infopult','Infopult','',81,0),
(72,'Provoz 2 - štáb','Budova C, dveře č. 28','',82,0),
(73,'Provoz 3 - sklad','Sklad','',83,0),
(74,'Provoz 4 - zahrada KD','Zahrada KD','',84,0),
(75,'Provoz 5 - ostatní','Ostatní','',85,0),
(76,'EPIC 8 - klubovna 2p','Budova C, dveře č. 203','Prosklená klubovna',34,0),
(77,'EPIC 9 - klubovna 2p','Budova C, dveře č. 210','Prosklená klubovna',35,0),
(78,'EPIC 10 - klubovna 3p','Budova C, dveře č. 303','Prosklená klubovna',36,0),
(79,'EPIC 11 - sál 0p','Budova C, vchod z jídelny','Tělocvična vedle jídelny',37,0),
(80,'Prog 9 - rezerva','','',77,0),
(81,'Prog 10 - rezerva','','',78,0),
(82,'Prog 11 - rezerva','','',79,0),
(83,'Prog 12 - rezerva','','',80,0),
(84,'LARP 9 - DM knihovna','Budova B, suterén','Po schodech dolů vpravo, dveře vpravo',46,0),
(85,'LARP 10 - 1L pokoj','Budova C, dveře č. 308','Pokoj 1L',47,0);
/*!40000 ALTER TABLE `akce_lokace` ENABLE KEYS */;

--
-- Table structure for table `akce_organizatori`
--

DROP TABLE IF EXISTS `akce_organizatori`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `akce_organizatori` (
  `id_akce` int(11) NOT NULL,
  `id_uzivatele` int(11) NOT NULL COMMENT 'organizátor',
  PRIMARY KEY (`id_akce`,`id_uzivatele`),
  KEY `id_uzivatele` (`id_uzivatele`),
  CONSTRAINT `FK_akce_organizatori_to_akce_seznam` FOREIGN KEY (`id_akce`) REFERENCES `akce_seznam` (`id_akce`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `FK_akce_organizatori_to_uzivatele_hodnoty` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `akce_organizatori_ibfk_3` FOREIGN KEY (`id_akce`) REFERENCES `akce_seznam` (`id_akce`),
  CONSTRAINT `akce_organizatori_ibfk_4` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`),
  CONSTRAINT `akce_organizatori_ibfk_5` FOREIGN KEY (`id_akce`) REFERENCES `akce_seznam` (`id_akce`),
  CONSTRAINT `akce_organizatori_ibfk_6` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `akce_organizatori`
--

/*!40000 ALTER TABLE `akce_organizatori` DISABLE KEYS */;
/*!40000 ALTER TABLE `akce_organizatori` ENABLE KEYS */;

--
-- Table structure for table `akce_prihlaseni`
--

DROP TABLE IF EXISTS `akce_prihlaseni`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `akce_prihlaseni` (
  `id_akce` int(11) NOT NULL,
  `id_uzivatele` int(11) NOT NULL,
  `id_stavu_prihlaseni` tinyint(4) NOT NULL,
  PRIMARY KEY (`id_akce`,`id_uzivatele`),
  KEY `id_uzivatele` (`id_uzivatele`),
  KEY `id_stavu_prihlaseni` (`id_stavu_prihlaseni`),
  CONSTRAINT `FK_akce_prihlaseni_to_akce_prihlaseni_stavy` FOREIGN KEY (`id_stavu_prihlaseni`) REFERENCES `akce_prihlaseni_stavy` (`id_stavu_prihlaseni`) ON UPDATE CASCADE,
  CONSTRAINT `FK_akce_prihlaseni_to_akce_seznam` FOREIGN KEY (`id_akce`) REFERENCES `akce_seznam` (`id_akce`) ON UPDATE CASCADE,
  CONSTRAINT `FK_akce_prihlaseni_to_uzivatele_hodnoty` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON UPDATE CASCADE,
  CONSTRAINT `akce_prihlaseni_ibfk_4` FOREIGN KEY (`id_akce`) REFERENCES `akce_seznam` (`id_akce`),
  CONSTRAINT `akce_prihlaseni_ibfk_5` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`),
  CONSTRAINT `akce_prihlaseni_ibfk_6` FOREIGN KEY (`id_stavu_prihlaseni`) REFERENCES `akce_prihlaseni_stavy` (`id_stavu_prihlaseni`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `akce_prihlaseni`
--

/*!40000 ALTER TABLE `akce_prihlaseni` DISABLE KEYS */;
/*!40000 ALTER TABLE `akce_prihlaseni` ENABLE KEYS */;

--
-- Table structure for table `akce_prihlaseni_log`
--

DROP TABLE IF EXISTS `akce_prihlaseni_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `akce_prihlaseni_log` (
  `id_log` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_akce` int(11) NOT NULL,
  `id_uzivatele` int(11) NOT NULL,
  `kdy` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `typ` varchar(64) DEFAULT NULL,
  `id_zmenil` int(11) DEFAULT NULL,
  `zdroj_zmeny` varchar(128) DEFAULT NULL,
  `rocnik` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id_log`),
  KEY `typ` (`typ`),
  KEY `id_zmenil` (`id_zmenil`),
  KEY `FK_akce_prihlaseni_log_to_akce_seznam` (`id_akce`),
  KEY `FK_akce_prihlaseni_log_to_uzivatele_hodnoty` (`id_uzivatele`),
  KEY `zdroj_zmeny` (`zdroj_zmeny`),
  CONSTRAINT `FK_akce_prihlaseni_log_to_akce_seznam` FOREIGN KEY (`id_akce`) REFERENCES `akce_seznam` (`id_akce`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `FK_akce_prihlaseni_log_to_uzivatele_hodnoty` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `akce_prihlaseni_log`
--

/*!40000 ALTER TABLE `akce_prihlaseni_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `akce_prihlaseni_log` ENABLE KEYS */;

--
-- Table structure for table `akce_prihlaseni_spec`
--

DROP TABLE IF EXISTS `akce_prihlaseni_spec`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `akce_prihlaseni_spec` (
  `id_akce` int(11) NOT NULL,
  `id_uzivatele` int(11) NOT NULL,
  `id_stavu_prihlaseni` tinyint(4) NOT NULL,
  PRIMARY KEY (`id_akce`,`id_uzivatele`),
  KEY `id_uzivatele` (`id_uzivatele`),
  KEY `id_stavu_prihlaseni` (`id_stavu_prihlaseni`),
  CONSTRAINT `FK_akce_prihlaseni_spec_to_akce_prihlaseni_stavy` FOREIGN KEY (`id_stavu_prihlaseni`) REFERENCES `akce_prihlaseni_stavy` (`id_stavu_prihlaseni`) ON UPDATE CASCADE,
  CONSTRAINT `FK_akce_prihlaseni_spec_to_akce_seznam` FOREIGN KEY (`id_akce`) REFERENCES `akce_seznam` (`id_akce`) ON UPDATE CASCADE,
  CONSTRAINT `FK_akce_prihlaseni_spec_to_uzivatele_hodnoty` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON UPDATE CASCADE,
  CONSTRAINT `akce_prihlaseni_spec_ibfk_10` FOREIGN KEY (`id_akce`) REFERENCES `akce_seznam` (`id_akce`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `akce_prihlaseni_spec_ibfk_5` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`),
  CONSTRAINT `akce_prihlaseni_spec_ibfk_6` FOREIGN KEY (`id_stavu_prihlaseni`) REFERENCES `akce_prihlaseni_stavy` (`id_stavu_prihlaseni`),
  CONSTRAINT `akce_prihlaseni_spec_ibfk_7` FOREIGN KEY (`id_akce`) REFERENCES `akce_seznam` (`id_akce`),
  CONSTRAINT `akce_prihlaseni_spec_ibfk_8` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`),
  CONSTRAINT `akce_prihlaseni_spec_ibfk_9` FOREIGN KEY (`id_stavu_prihlaseni`) REFERENCES `akce_prihlaseni_stavy` (`id_stavu_prihlaseni`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `akce_prihlaseni_spec`
--

/*!40000 ALTER TABLE `akce_prihlaseni_spec` DISABLE KEYS */;
/*!40000 ALTER TABLE `akce_prihlaseni_spec` ENABLE KEYS */;

--
-- Table structure for table `akce_prihlaseni_stavy`
--

DROP TABLE IF EXISTS `akce_prihlaseni_stavy`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `akce_prihlaseni_stavy` (
  `id_stavu_prihlaseni` tinyint(4) NOT NULL,
  `nazev` varchar(255) NOT NULL,
  `platba_procent` float NOT NULL DEFAULT 100,
  PRIMARY KEY (`id_stavu_prihlaseni`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `akce_prihlaseni_stavy`
--

/*!40000 ALTER TABLE `akce_prihlaseni_stavy` DISABLE KEYS */;
INSERT INTO `akce_prihlaseni_stavy` VALUES
(0,'přihlášen',100),
(1,'dorazil',100),
(2,'dorazil (náhradník)',100),
(3,'nedorazil',100),
(4,'pozdě zrušil',50),
(5,'náhradník (watchlist)',0);
/*!40000 ALTER TABLE `akce_prihlaseni_stavy` ENABLE KEYS */;

--
-- Table structure for table `akce_seznam`
--

DROP TABLE IF EXISTS `akce_seznam`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `akce_seznam` (
  `id_akce` int(11) NOT NULL AUTO_INCREMENT,
  `patri_pod` int(11) DEFAULT NULL,
  `nazev_akce` varchar(255) NOT NULL,
  `url_akce` varchar(64) DEFAULT NULL,
  `zacatek` datetime DEFAULT NULL,
  `konec` datetime DEFAULT NULL,
  `lokace` int(11) DEFAULT NULL,
  `kapacita` int(11) NOT NULL,
  `kapacita_f` int(11) NOT NULL,
  `kapacita_m` int(11) NOT NULL,
  `cena` int(11) NOT NULL,
  `bez_slevy` tinyint(1) NOT NULL COMMENT 'na aktivitu se neuplatňují slevy',
  `nedava_bonus` tinyint(1) NOT NULL COMMENT 'aktivita negeneruje organizátorovi bonus za vedení aktivity',
  `typ` int(11) NOT NULL,
  `dite` varchar(64) DEFAULT NULL COMMENT 'potomci oddělení čárkou',
  `rok` int(11) NOT NULL,
  `stav` int(11) NOT NULL DEFAULT 1,
  `teamova` tinyint(1) NOT NULL,
  `team_min` int(11) DEFAULT NULL COMMENT 'minimální velikost teamu',
  `team_max` int(11) DEFAULT NULL COMMENT 'maximální velikost teamu',
  `team_kapacita` int(11) DEFAULT NULL COMMENT 'max. počet týmů, pokud jde o další kolo týmové aktivity',
  `team_nazev` varchar(255) DEFAULT NULL,
  `zamcel` int(11) DEFAULT NULL COMMENT 'případně kdo zamčel aktivitu pro svůj team',
  `zamcel_cas` datetime DEFAULT NULL COMMENT 'případně kdy zamčel aktivitu',
  `popis` int(11) NOT NULL,
  `popis_kratky` varchar(255) NOT NULL,
  `vybaveni` text NOT NULL,
  `team_limit` int(11) DEFAULT NULL COMMENT 'uživatelem (vedoucím týmu) nastavený limit kapacity menší roven team_max, ale větší roven team_min. Prostřednictvím on update triggeru kontrolována tato vlastnost a je-li non-null, tak je tato kapacita nastavena do sloupce `kapacita`',
  PRIMARY KEY (`id_akce`),
  UNIQUE KEY `url_akce_rok_typ` (`url_akce`,`rok`,`typ`),
  KEY `rok` (`rok`),
  KEY `patri_pod` (`patri_pod`),
  KEY `lokace` (`lokace`),
  KEY `typ` (`typ`),
  KEY `stav` (`stav`),
  KEY `FK_akce_seznam_to_popis` (`popis`),
  CONSTRAINT `FK_akce_seznam_to_akce_instance` FOREIGN KEY (`patri_pod`) REFERENCES `akce_instance` (`id_instance`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `FK_akce_seznam_to_akce_stav` FOREIGN KEY (`stav`) REFERENCES `akce_stav` (`id_stav`) ON UPDATE CASCADE,
  CONSTRAINT `FK_akce_seznam_to_popis` FOREIGN KEY (`popis`) REFERENCES `texty` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `akce_seznam`
--

/*!40000 ALTER TABLE `akce_seznam` DISABLE KEYS */;
/*!40000 ALTER TABLE `akce_seznam` ENABLE KEYS */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=CURRENT_USER*/ /*!50003 trigger trigger_check_and_apply_team_limit
    before update
    on akce_seznam
    for each row
begin
    if new.team_limit is not null then
    if new.team_limit < new.team_min or new.team_limit > new.team_max then
        set new.team_limit = null;
    else
        set new.kapacita = new.team_limit;
    end if;
end if;
end */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `akce_sjednocene_tagy`
--

DROP TABLE IF EXISTS `akce_sjednocene_tagy`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `akce_sjednocene_tagy` (
  `id_akce` int(11) NOT NULL,
  `id_tagu` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id_akce`,`id_tagu`),
  KEY `FK_akce_sjednocene_tagy_to_sjednocene_tagy` (`id_tagu`),
  CONSTRAINT `FK_akce_sjednocene_tagy_to_sjednocene_tagy` FOREIGN KEY (`id_tagu`) REFERENCES `sjednocene_tagy` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `akce_sjednocene_tagy`
--

/*!40000 ALTER TABLE `akce_sjednocene_tagy` DISABLE KEYS */;
/*!40000 ALTER TABLE `akce_sjednocene_tagy` ENABLE KEYS */;

--
-- Table structure for table `akce_stav`
--

DROP TABLE IF EXISTS `akce_stav`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `akce_stav` (
  `id_stav` int(11) NOT NULL AUTO_INCREMENT,
  `nazev` varchar(128) NOT NULL,
  PRIMARY KEY (`nazev`),
  UNIQUE KEY `id_stav` (`id_stav`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `akce_stav`
--

/*!40000 ALTER TABLE `akce_stav` DISABLE KEYS */;
INSERT INTO `akce_stav` VALUES
(1,'nová'),
(2,'aktivovaná'),
(3,'uzavřená'),
(4,'systémová'),
(5,'publikovaná'),
(6,'připravená'),
(7,'zamčená');
/*!40000 ALTER TABLE `akce_stav` ENABLE KEYS */;

--
-- Table structure for table `akce_stavy_log`
--

DROP TABLE IF EXISTS `akce_stavy_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `akce_stavy_log` (
  `akce_stavy_log_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_akce` int(11) NOT NULL,
  `id_stav` int(11) NOT NULL,
  `kdy` timestamp NOT NULL DEFAULT current_timestamp(),
  UNIQUE KEY `akce_stavy_log_id` (`akce_stavy_log_id`),
  KEY `FK_akce_stavy_log_to_akce_seznam` (`id_akce`),
  KEY `FK_akce_stavy_log_to_akce_stav` (`id_stav`),
  CONSTRAINT `FK_akce_stavy_log_to_akce_seznam` FOREIGN KEY (`id_akce`) REFERENCES `akce_seznam` (`id_akce`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `FK_akce_stavy_log_to_akce_stav` FOREIGN KEY (`id_stav`) REFERENCES `akce_stav` (`id_stav`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `akce_stavy_log`
--

/*!40000 ALTER TABLE `akce_stavy_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `akce_stavy_log` ENABLE KEYS */;

--
-- Table structure for table `akce_typy`
--

DROP TABLE IF EXISTS `akce_typy`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `akce_typy` (
  `id_typu` int(11) NOT NULL,
  `typ_1p` varchar(32) NOT NULL,
  `typ_1pmn` varchar(32) NOT NULL,
  `url_typu_mn` varchar(32) NOT NULL,
  `stranka_o` int(11) NOT NULL COMMENT 'id stranky "O rpg na GC" apod.',
  `poradi` int(11) NOT NULL,
  `mail_neucast` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'poslat mail účastníkovi, pokud nedorazí',
  `popis_kratky` varchar(255) NOT NULL,
  `aktivni` tinyint(1) DEFAULT 1,
  `zobrazit_v_menu` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id_typu`),
  KEY `FK_akce_typy_to_stranka_o` (`stranka_o`),
  CONSTRAINT `FK_akce_typy_to_stranka_o` FOREIGN KEY (`stranka_o`) REFERENCES `stranky` (`id_stranky`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `akce_typy`
--

/*!40000 ALTER TABLE `akce_typy` DISABLE KEYS */;
INSERT INTO `akce_typy` VALUES
(0,'(bez typu – organizační)','(bez typu – organizační)','organizacni',79,-1,0,'',1,0),
(3,'Přednáška','Přednášky','prednasky',28,10,0,'',1,1),
(102,'brigádnická','brigádnické','brigadnicke',79,-3,0,'Placená výpomoc Gameconu',1,0);
/*!40000 ALTER TABLE `akce_typy` ENABLE KEYS */;

--
-- Table structure for table `google_api_user_tokens`
--

DROP TABLE IF EXISTS `google_api_user_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `google_api_user_tokens` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `google_client_id` varchar(128) NOT NULL,
  `tokens` text NOT NULL,
  PRIMARY KEY (`user_id`,`google_client_id`),
  UNIQUE KEY `id` (`id`),
  CONSTRAINT `FK_google_api_user_tokens_to_uzivatele_hodnoty` FOREIGN KEY (`user_id`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `google_api_user_tokens`
--

/*!40000 ALTER TABLE `google_api_user_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `google_api_user_tokens` ENABLE KEYS */;

--
-- Table structure for table `google_drive_dirs`
--

DROP TABLE IF EXISTS `google_drive_dirs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `google_drive_dirs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `dir_id` varchar(128) NOT NULL,
  `original_name` varchar(64) NOT NULL,
  `tag` varchar(128) NOT NULL DEFAULT '',
  PRIMARY KEY (`dir_id`),
  UNIQUE KEY `id` (`id`),
  UNIQUE KEY `user_and_name` (`user_id`,`original_name`),
  KEY `tag` (`tag`),
  CONSTRAINT `FK_google_drive_dirs_to_uzivatele_hodnoty` FOREIGN KEY (`user_id`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `google_drive_dirs`
--

/*!40000 ALTER TABLE `google_drive_dirs` DISABLE KEYS */;
/*!40000 ALTER TABLE `google_drive_dirs` ENABLE KEYS */;

--
-- Table structure for table `hromadne_akce_log`
--

DROP TABLE IF EXISTS `hromadne_akce_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hromadne_akce_log` (
  `id_logu` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `skupina` varchar(128) DEFAULT NULL,
  `akce` varchar(255) DEFAULT NULL,
  `vysledek` varchar(255) DEFAULT NULL,
  `provedl` int(11) DEFAULT NULL,
  `kdy` datetime NOT NULL DEFAULT current_timestamp(),
  UNIQUE KEY `id_logu` (`id_logu`),
  KEY `akce` (`akce`),
  KEY `FK_hromadne_akce_log_to_uzivatele_hodnoty` (`provedl`),
  CONSTRAINT `FK_hromadne_akce_log_to_uzivatele_hodnoty` FOREIGN KEY (`provedl`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hromadne_akce_log`
--

/*!40000 ALTER TABLE `hromadne_akce_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `hromadne_akce_log` ENABLE KEYS */;

--
-- Table structure for table `kategorie_sjednocenych_tagu`
--

DROP TABLE IF EXISTS `kategorie_sjednocenych_tagu`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kategorie_sjednocenych_tagu` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nazev` varchar(128) NOT NULL,
  `id_hlavni_kategorie` int(10) unsigned DEFAULT NULL,
  `poradi` int(10) unsigned NOT NULL,
  PRIMARY KEY (`nazev`),
  UNIQUE KEY `id` (`id`),
  KEY `FK_kategorie_sjednocenych_tagu_to_kategorie_sjednocenych_tagu` (`id_hlavni_kategorie`),
  CONSTRAINT `FK_kategorie_sjednocenych_tagu_to_kategorie_sjednocenych_tagu` FOREIGN KEY (`id_hlavni_kategorie`) REFERENCES `kategorie_sjednocenych_tagu` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kategorie_sjednocenych_tagu`
--

/*!40000 ALTER TABLE `kategorie_sjednocenych_tagu` DISABLE KEYS */;
INSERT INTO `kategorie_sjednocenych_tagu` VALUES
(8,'omezení',NULL,90),
(50,'omezení Věk',8,95),
(1,'Primary',NULL,1),
(4,'prostředí',NULL,30),
(29,'prostředí Akční',4,35),
(30,'prostředí Deskovky',4,36),
(28,'prostředí Larp',4,34),
(26,'prostředí LKD',4,32),
(27,'prostředí mDrD',4,33),
(32,'prostředí Přednášky',4,38),
(25,'prostředí RPG',4,31),
(31,'prostředí WG',4,37),
(7,'různé',NULL,80),
(49,'různé Partner',7,81),
(5,'styl',NULL,40),
(37,'styl Akční',5,45),
(38,'styl Deskovky',5,46),
(36,'styl Larp',5,44),
(34,'styl LKD',5,42),
(35,'styl mDrD',5,43),
(40,'styl Přednášky',5,48),
(33,'styl RPG',5,41),
(39,'styl WG',5,47),
(6,'systém',NULL,50),
(45,'systém Akční',6,55),
(46,'systém Deskovky',6,56),
(44,'systém Larp',6,54),
(42,'systém LKD',6,52),
(43,'systém mDrD',6,53),
(48,'systém Přednášky',6,58),
(41,'systém RPG',6,51),
(47,'systém WG',6,57),
(2,'typ',NULL,10),
(13,'typ Akční',2,15),
(14,'typ Deskovky',2,16),
(12,'typ Larp',2,14),
(10,'typ LKD',2,12),
(11,'typ mDrD',2,13),
(16,'typ Přednášky',2,18),
(9,'typ RPG',2,11),
(15,'typ WG',2,17),
(3,'žánr',NULL,20),
(21,'žánr Akční',3,25),
(22,'žánr Deskovky',3,26),
(20,'žánr Larp',3,24),
(18,'žánr LKD',3,22),
(19,'žánr mDrD',3,23),
(24,'žánr Přednášky',3,28),
(17,'žánr RPG',3,21),
(23,'žánr WG',3,27);
/*!40000 ALTER TABLE `kategorie_sjednocenych_tagu` ENABLE KEYS */;

--
-- Table structure for table `log_udalosti`
--

DROP TABLE IF EXISTS `log_udalosti`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `log_udalosti` (
  `id_udalosti` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_logujiciho` int(11) NOT NULL,
  `zprava` varchar(255) DEFAULT NULL,
  `metadata` varchar(255) DEFAULT NULL,
  `rok` int(10) unsigned NOT NULL,
  UNIQUE KEY `id_udalosti` (`id_udalosti`),
  KEY `metadata` (`metadata`),
  KEY `FK_log_udalosti_to_uzivatele_hodnoty` (`id_logujiciho`),
  CONSTRAINT `FK_log_udalosti_to_uzivatele_hodnoty` FOREIGN KEY (`id_logujiciho`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `log_udalosti`
--

/*!40000 ALTER TABLE `log_udalosti` DISABLE KEYS */;
/*!40000 ALTER TABLE `log_udalosti` ENABLE KEYS */;

--
-- Table structure for table `medailonky`
--

DROP TABLE IF EXISTS `medailonky`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `medailonky` (
  `id_uzivatele` int(11) NOT NULL,
  `o_sobe` mediumtext NOT NULL COMMENT 'markdown',
  `drd` mediumtext NOT NULL COMMENT 'markdown -- profil pro DrD',
  PRIMARY KEY (`id_uzivatele`),
  CONSTRAINT `FK_medailonky_to_uzivatele_hodnoty` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `medailonky`
--

/*!40000 ALTER TABLE `medailonky` DISABLE KEYS */;
/*!40000 ALTER TABLE `medailonky` ENABLE KEYS */;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `migration_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `migration_code` varchar(128) NOT NULL,
  `applied_at` datetime DEFAULT NULL,
  PRIMARY KEY (`migration_code`),
  UNIQUE KEY `migration_id` (`migration_id`)
) ENGINE=InnoDB AUTO_INCREMENT=231 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES
(1,'000',NULL),
(2,'001',NULL),
(3,'002',NULL),
(4,'003',NULL),
(5,'004',NULL),
(6,'005',NULL),
(7,'006',NULL),
(8,'007',NULL),
(9,'008',NULL),
(10,'009',NULL),
(11,'010',NULL),
(12,'011',NULL),
(13,'012',NULL),
(14,'013',NULL),
(15,'014',NULL),
(16,'015',NULL),
(17,'016',NULL),
(18,'017',NULL),
(19,'018',NULL),
(20,'019',NULL),
(21,'020',NULL),
(22,'021',NULL),
(23,'022',NULL),
(24,'023',NULL),
(25,'024',NULL),
(26,'025',NULL),
(27,'026',NULL),
(28,'027',NULL),
(29,'028',NULL),
(30,'029',NULL),
(31,'030',NULL),
(32,'031',NULL),
(33,'032',NULL),
(34,'033',NULL),
(35,'034',NULL),
(36,'035',NULL),
(37,'036',NULL),
(38,'037',NULL),
(39,'038',NULL),
(40,'039',NULL),
(41,'040',NULL),
(42,'041',NULL),
(43,'042',NULL),
(44,'043',NULL),
(45,'044',NULL),
(46,'045',NULL),
(47,'046',NULL),
(48,'047',NULL),
(49,'048',NULL),
(50,'049',NULL),
(51,'050',NULL),
(52,'051',NULL),
(53,'052',NULL),
(54,'053',NULL),
(55,'054',NULL),
(56,'055',NULL),
(57,'056',NULL),
(58,'057',NULL),
(59,'058',NULL),
(60,'059',NULL),
(61,'060',NULL),
(62,'061',NULL),
(63,'062',NULL),
(64,'063',NULL),
(65,'064',NULL),
(66,'065',NULL),
(67,'066',NULL),
(68,'067',NULL),
(69,'068',NULL),
(70,'069',NULL),
(71,'070',NULL),
(72,'071','2023-01-27 11:17:05'),
(73,'072',NULL),
(76,'2021-06-14_01-drop-old-db_migrations','2023-01-27 11:17:05'),
(77,'2021-07-01_01-potvrzeni-proti-covidu','2023-01-27 11:17:05'),
(78,'2021-07-01_01-sloupec-posadil-k-pravum','2023-01-27 11:17:06'),
(79,'2021-07-13_01-prejmenovat-celkovy-report-na-bfgr-report','2023-01-27 11:17:06'),
(80,'2021-07-15-samostatny-skript-pro-stravenky-bianco','2023-01-27 11:17:06'),
(81,'2021-07-21-lepsi-popis-pro-admin-zidli','2023-01-27 11:17:06'),
(82,'2021-11-20-pridat-unikatni-index-na-fio-id','2023-01-27 11:17:06'),
(83,'2021-12-17-prejmenovat-slevu-na-bonus','2023-01-27 11:17:06'),
(84,'2022-02-23-povolit-mazani-z-tabulky-instanci','2023-01-27 11:17:06'),
(85,'2022-04-28-stavy-v-logu-prihlaseni','2023-01-27 11:17:06'),
(86,'2022-04-29-pridat-serial-id-do-logu-prihlaseni','2023-01-27 11:17:07'),
(87,'2022-05-04-pravo-nastaveni','2023-01-27 11:17:07'),
(88,'2022-05-05_01-tabulka-systemove-nastaveni','2023-01-27 11:17:07'),
(89,'2022-05-05_02-tabulka-systemove-nastaveni-log','2023-01-27 11:17:07'),
(90,'2022-05-05_03-kurz-euro-do-systemoveho-nastaveni','2023-01-27 11:17:07'),
(91,'2022-05-05_04-utf8-pro-tabulky-systemoveho-nastaveni','2023-01-27 11:17:07'),
(92,'2022-05-09_01-poradi-a-skupina-do-systemoveho-nastaveni','2023-01-27 11:17:07'),
(93,'2022-05-09_02-bonusy-vypravecu-do-systemoveho-nastaveni','2023-01-27 11:17:07'),
(94,'2022-05-10_01-shop-predmety','2023-01-27 11:17:07'),
(96,'2022-05-11_01-shop-upravy','2023-01-27 11:17:07'),
(97,'2022-05-11_02-prepinani-rucni-a-automaticke-hodnoty-systemoveho-nastaveni','2023-01-27 11:17:08'),
(98,'2022-05-11_03-zacatek-a-konec-gameconu-do-systemoveho-nastaveni','2023-01-27 11:17:08'),
(95,'2022-05-11-jen-zakladni-bonus-vypravecu-v-systemovem-nastaveni','2023-01-27 11:17:07'),
(100,'2022-05-12_02-dalsi-casy-do-systemoveho-nastaveni','2023-01-27 11:17:08'),
(101,'2022-05-12_03-lidstejsi-nazvy-skupin-systemoveho-nastaveni','2023-01-27 11:17:08'),
(99,'2022-05-12-zvednout-cenu-kostek-na-25','2023-01-27 11:17:08'),
(102,'2022-05-13-prejmenovat-sloupec-s-timestamp-v-logu-prihlaseni','2023-01-27 11:17:08'),
(103,'2022-05-14_01-shop-3XL-tricka','2023-01-27 11:17:08'),
(104,'2022-05-15_1-mistnosti-2022','2023-01-27 11:17:08'),
(105,'2022-05-17_00-url-uzivatele','2023-01-27 11:17:08'),
(107,'2022-05-17_1-mistnosti-oprava-2022','2023-01-27 11:17:08'),
(106,'2022-05-17_1-Warhammer_40k_turnaj_1_kolo-otevren','2023-01-27 11:17:08'),
(108,'2022-05-18_01-import-mistnosti-2022-v3','2023-01-27 11:17:08'),
(110,'2022-05-20_01-report-infopult-ucastnici-balicky','2023-01-27 11:17:08'),
(111,'2022-05-20_01-zmena-poradi-menu-aktivit','2023-01-27 11:17:08'),
(112,'2022-05-20_02-infopult-poznamka-k-uzivateli','2023-01-27 11:17:08'),
(109,'2022-05-20-zvednout-cenu-obedu-a-veceri-na-120','2023-01-27 11:17:08'),
(113,'2022-05-22_01-zidle-neodhlasovat','2023-01-27 11:17:08'),
(114,'2022-05-22_02-neplatic-do-systemoveho-nastaveni','2023-01-27 11:17:08'),
(115,'2022-05-24_01-skryt-typ-aktivity-workshop','2023-01-27 11:17:08'),
(116,'2022-05-24_01-systemovejsi-skryvani-typu-aktivity-v-menu','2023-01-27 11:17:08'),
(117,'2022-05-26_01-reporty-v-xlsx','2023-01-27 11:17:08'),
(118,'2022-05-26_1-logovani-zmen-zidli','2023-01-27 11:17:08'),
(119,'2022-05-28_1-doplneni-logu-zmen-zidli','2023-01-27 11:17:08'),
(120,'2022-05-29_01-cas-posledni-zmeny-platby','2023-01-27 11:17:08'),
(121,'2022-05-29_01-zidle-herman','2023-01-27 11:17:08'),
(122,'2022-06-08_1-Oldest_Old_World-Tournament-1-kolo','2023-01-27 11:17:08'),
(123,'2022-06-11_01-aktivita_editovatelna_x_sekund_po_zavreni','2023-01-27 11:17:08'),
(124,'2022-06-11_02-aktivita_stavy_log','2023-01-27 11:17:08'),
(125,'2022-06-14-upravit-cizi-klic-aby-slo-smazat-aktivitu','2023-01-27 11:17:08'),
(126,'2022-06-16_01-automaticky_zamknout_aktivitu_po','2023-01-27 11:17:08'),
(127,'2022-06-16_01-upozornit_na_neuzamknutou_aktivitu_poi','2023-01-27 11:17:08'),
(128,'2022-06-22_01-shop-dalsi-xxl-tricka','2023-01-27 11:17:08'),
(129,'2022-06-23_01-upozornit_na_neuzamknutou_aktivitu_jen_pokud_ma_par_vypravecu','2023-01-27 11:17:08'),
(130,'2022-06-25_01-prejmenovat-skript-s-exportem-ubytovani','2023-01-27 11:17:08'),
(131,'2022-06-29_01-report-neplaticu','2023-01-27 11:17:08'),
(132,'2022-07-01-obchod_mrizka','2023-01-27 11:17:08'),
(133,'2022-07-02-prejmenovat-sekci-v-nastaveni','2023-01-27 11:17:08'),
(134,'2022-07-05-smazat-nepouzite-nastaveni-o-poctu-vypravecu','2023-01-27 11:17:08'),
(135,'2022-07-07_01-texy-clanku-do-utf8mb4','2023-01-27 11:17:09'),
(136,'2022-07-08_01-virtualni-uzivatel-system','2023-01-27 11:17:09'),
(137,'2022-07-09_01-uzavrit-nabidku-ubytovani-2022-11-7','2023-01-27 11:17:09'),
(138,'2022-07-10_01-text-na-sparovani-odchozich-plateb','2023-01-27 11:17:09'),
(139,'2022-07-11_01-datum-pro-ukonceni-prodeje-a-zmen-ubytovani','2023-01-27 11:17:09'),
(140,'2022-07-11_01-datum-pro-ukonceni-prodeje-a-zmen-vseho-mozneho','2023-01-27 11:17:09'),
(141,'2022-07-11_01-datum-pro-ukonceni-registrace-na-gamecon','2023-01-27 11:17:09'),
(142,'2022-07-12_01-posunout-cas-prodeje-jidla','2023-01-27 11:17:09'),
(143,'2022-07-14_01-prejmenovat-report-neplaticu','2023-01-27 11:17:09'),
(144,'2022-07-14_01-zmenit-editovatelnost-aktivity-na-pridavatelnost','2023-01-27 11:17:09'),
(145,'2022-07-21_01-logovat-kdo-zmenil-stav-aktivity','2023-01-27 11:17:09'),
(146,'2022-07-22_01-log-udalosti','2023-01-27 11:17:09'),
(147,'2022-07-22_01-organizacni-typ-aktivity','2023-01-27 11:17:09'),
(148,'2022-07-28_01-nastaveni-do-kdy-lze-pridavat-ucastniky','2023-01-27 11:17:09'),
(149,'2022-07-28_02-prejmenovat-stavy-aktivity-v-databazi','2023-01-27 11:17:09'),
(150,'2022-08-03_01-pridat-zidli-brigadnik','2023-01-27 11:17:09'),
(151,'2022-08-03_02-pridat-typ-aktivity-brigadnicka','2023-01-27 11:17:09'),
(152,'2022-11-14_01-sloucit-rozdeleny-report-starych-ucasti','2023-01-27 11:17:09'),
(153,'2022-11-30_01-foreign-keys-kaskady','2023-01-27 11:17:13'),
(154,'2022-12-05_01-vymenit-nulu-jako-id-stavu-aktivity','2023-01-27 11:17:13'),
(155,'2023-01-28_01-rocnik-platnosti-zidle','2023-05-03 22:08:04'),
(156,'2023-01-29_01-cizi-klice-pro-zidle','2023-05-03 22:08:05'),
(157,'2023-01-30_01-kody-zidli','2023-05-03 22:08:07'),
(158,'2023-01-31_01-sql-pohledy-s-platnymi-rolemi','2023-05-03 22:08:07'),
(159,'2023-02-01_01-idcka-roli-na-novy-format','2023-05-03 22:08:07'),
(160,'2023-02-01_02-typy-roli','2023-05-03 22:08:08'),
(161,'2023-02-02_01-vyznam-roli','2023-05-03 22:08:08'),
(162,'2023-02-06_01-sjednotit-nazvy-konstant-s-hromadnym-odhlasovanim','2023-05-03 22:08:08'),
(163,'2023-02-06_02-cas-tretiho-hromadneho-odhlasovani','2023-05-03 22:08:08'),
(164,'2023-02-10_01-zidle-na-role','2023-05-03 22:08:12'),
(165,'2023-02-13_01-nrahradit-zidli-za-roli-v-textu','2023-05-03 22:08:12'),
(166,'2023-02-13_02-admin-na-prezencni-admin','2023-05-03 22:08:12'),
(167,'2023-02-15_01-odstranit-rok-z-kodu-trvalych-roli','2023-05-03 22:08:12'),
(168,'2023-02-15_02-skryt-vypravecskou-skupinu','2023-05-03 22:08:12'),
(169,'2023-02-16_01-prejmenovat-role-s-bonusy','2023-05-03 22:08:12'),
(170,'2023-02-16_02-aktualizovat-nazvy-prav','2023-05-03 22:08:12'),
(171,'2023-02-16_03-dalsi-prava-na-noci-zdarma','2023-05-03 22:08:12'),
(172,'2023-02-16_04-rozdelit-admin-finance-na-penize','2023-05-03 22:08:12'),
(173,'2023-02-16_05-clen-rady-sef-infopultu','2023-05-03 22:08:12'),
(174,'2023-02-17_01-pouze-clen-rady-ma-pravo-menit-prava','2023-05-03 22:08:12'),
(175,'2023-02-23_01-clenove-rady-2023','2023-05-03 22:08:12'),
(176,'2023-02-23_02-kategorie-role','2023-05-03 22:08:12'),
(180,'2023-02-25_01-dalsi-vlny-aktivit','2023-06-21 21:58:01'),
(177,'2023-02-25_01-prejmenovat-cfo','2023-05-03 22:08:12'),
(181,'2023-03-02_01-log-hromadnych-akci','2023-06-21 21:58:02'),
(182,'2023-03-02_01-priznak-aktivni-prejmenovat-na-vlastni','2023-06-21 21:58:02'),
(183,'2023-03-02_02-uprava-nazvu-nastaveni','2023-06-21 21:58:02'),
(204,'2023-03-06_01-zruseni-konstant-pro-hromadne-odhlasovani','2025-01-11 12:39:41'),
(184,'2023-03-08_01-platby-mohou-byt-bez-sparovaneho-uzivatele','2023-06-21 21:58:02'),
(185,'2023-03-10_01-zrusene-objednavky','2023-06-21 21:58:03'),
(186,'2023-03-13_01-duvod-zmeny-aktivity','2023-06-21 21:58:03'),
(178,'2023-03-24_01-kategorie-predmetu','2023-05-03 22:08:13'),
(187,'2023-03-24_01-oprava-preklepu-v-popisu-nastaveni','2023-06-21 21:58:03'),
(179,'2023-04-06_01-import-mistnosti-2023','2023-05-03 22:08:13'),
(188,'2023-04-13_01-rocnikove-nastaveni','2023-06-21 21:58:04'),
(189,'2023-04-14_01-rocnikove-vstupne','2023-06-21 21:58:04'),
(190,'2023-05-03_01-obnoveni-konstant-pro-hromadne-odhlasovani','2023-06-21 21:58:04'),
(191,'2023-05-04_01-prejmenovat-report-prihlaseni-a-ubytovani','2023-06-21 21:58:04'),
(192,'2023-05-05_01-pravo-na-prihlasovani-na-dosud-neotevrene-aktivity','2023-06-21 21:58:04'),
(193,'2023-05-09_01-report-eshopu','2023-06-21 21:58:04'),
(194,'2023-05-09_02-unikatni-kombinace-eshopu-nazev-rok','2023-06-21 21:58:04'),
(195,'2023-05-11_01-doklady-totoznosti','2023-06-21 21:58:04'),
(196,'2023-05-11_02-statni-obcanstvi','2023-06-21 21:58:04'),
(205,'2023-05-12_01-popis-prav-kostka-a-placka-zdarma','2025-01-11 12:39:41'),
(197,'2023-05-13_01-moznost-vypnout-mail-o-uvolnenem-ubytovani','2023-06-21 21:58:04'),
(198,'2023-05-16_01-podpora-plateb-deset-tisic-a-vice','2023-06-21 21:58:05'),
(199,'2023-05-17_01-moznost-omezit-format-quick-reportu','2023-06-21 21:58:05'),
(200,'2023-05-23_01-ujasnit-nazev-reportu-se-zustatky','2023-06-21 21:58:05'),
(201,'2023-05-24_01-odhlaseni-pet-minut-po-prihlaseni-bez-pokuty','2023-06-21 21:58:06'),
(202,'2023-05-26_01-platne_role_vcetne_historicke_ucasti','2023-06-21 21:58:06'),
(203,'2023-06-13_01-fix-platnost-noych-systemovych-nastaveni','2023-06-21 21:58:06'),
(206,'2023-06-26_01-oprava-cizich-klicu-logu-roli','2025-01-11 12:39:41'),
(207,'2023-06-28_01-prazdne-staty-uctu-na-cekou-republiku','2025-01-11 12:39:41'),
(208,'2023-07-06_01-priznak-uctu-z-rychloregistrace','2025-01-11 12:39:41'),
(209,'2023-07-11_01-nedava-slevu-na-nedava-bonus','2025-01-11 12:39:41'),
(210,'2023-07-11_01-report-balicku-bez-xlsx','2025-01-11 12:39:41'),
(211,'2023-07-12_01-skryt-report-tricek-negrouped','2025-01-11 12:39:41'),
(212,'2023-07-21_01-objednatel-do-nakupu','2025-01-11 12:39:41'),
(213,'2023-10-18_01-prejmenovat-report-aktivit-bez-slev','2025-01-11 12:39:41'),
(214,'2023-10-22_01-texty-roli-podle-uzivatel','2025-01-11 12:39:41'),
(215,'2023-11-19_01-ujasnit-nazev-prav-pro-veci-zdarma','2025-01-11 12:39:41'),
(216,'2024-04-05_01-import-mistnosti-2024','2025-01-11 12:39:41'),
(217,'2024-05-05_01-import-mistnosti-2024-vcetne-budovy-c','2025-01-11 12:39:41'),
(218,'2024-05-05_02-kod-predmetu-a-typu-predmetu','2025-01-11 12:39:42'),
(219,'2024-05-06_01-fix-kod-predmetu-na-neunikatni','2025-01-11 12:39:42'),
(220,'2024-05-15_01-skryt-bfgr-report-z-reportu','2025-01-11 12:39:42'),
(221,'2024-05-17_opravit-popis-parovaciho-textu-pro-vs-odchozi-platby','2025-01-11 12:39:42'),
(222,'2024-06-11_01-serial-pro-log-roli','2025-01-11 12:39:42'),
(223,'2024-06-11_02-sql-funkce-pro-konec-gc','2025-01-11 12:39:42'),
(224,'2024-06-11_03-uzivatele-role-podle-rocniku','2025-01-11 12:39:42'),
(225,'2024-06-13_01-report-role-podle-rocniku','2025-01-11 12:39:42'),
(226,'2024-06-16-opravit-tymove-limity-kapacity','2025-01-11 12:39:42'),
(227,'2024-06-22_01-ukladat-skrytou-poznamku-k-platbam','2025-01-11 12:39:42'),
(228,'2024-07-01_01-oprava-zaznamu-logu-hromadnych-akci','2025-01-11 12:39:42'),
(229,'2024-07-06_nastavitelna-sleva-na-jidlo-orgu','2025-01-11 12:39:42'),
(230,'2024-07-08_nahravani-potvrzeni-rodicu','2025-01-11 12:39:42');
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;

--
-- Table structure for table `mutex`
--

DROP TABLE IF EXISTS `mutex`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mutex` (
  `id_mutex` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `akce` varchar(128) NOT NULL,
  `klic` varchar(128) NOT NULL,
  `zamknul` int(11) DEFAULT NULL,
  `od` datetime NOT NULL,
  `do` datetime DEFAULT NULL,
  PRIMARY KEY (`akce`),
  UNIQUE KEY `id_mutex` (`id_mutex`),
  UNIQUE KEY `klic` (`klic`),
  KEY `FK_mutex_to_uzivatele_hodnoty` (`zamknul`),
  CONSTRAINT `FK_mutex_to_uzivatele_hodnoty` FOREIGN KEY (`zamknul`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mutex`
--

/*!40000 ALTER TABLE `mutex` DISABLE KEYS */;
/*!40000 ALTER TABLE `mutex` ENABLE KEYS */;

--
-- Table structure for table `novinky`
--

DROP TABLE IF EXISTS `novinky`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `novinky` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `typ` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1-novinka 2-blog',
  `vydat` datetime DEFAULT NULL,
  `url` varchar(100) NOT NULL,
  `nazev` varchar(200) NOT NULL,
  `autor` varchar(100) DEFAULT NULL,
  `text` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `url` (`url`),
  KEY `FK_novinky_to_texty` (`text`),
  CONSTRAINT `FK_novinky_to_texty` FOREIGN KEY (`text`) REFERENCES `texty` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `novinky`
--

/*!40000 ALTER TABLE `novinky` DISABLE KEYS */;
/*!40000 ALTER TABLE `novinky` ENABLE KEYS */;

--
-- Table structure for table `obchod_bunky`
--

DROP TABLE IF EXISTS `obchod_bunky`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `obchod_bunky` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `typ` tinyint(4) NOT NULL COMMENT '0-předmět, 1-stránka, 2-zpět, 3-shrnutí',
  `text` varchar(255) DEFAULT NULL,
  `barva` varchar(255) DEFAULT NULL,
  `cil_id` int(11) DEFAULT NULL COMMENT 'Id cílove mřížky nebo předmětu.',
  `mrizka_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `FK_obchod_bunky_to_obchod_mrizky` (`mrizka_id`),
  CONSTRAINT `FK_obchod_bunky_to_obchod_mrizky` FOREIGN KEY (`mrizka_id`) REFERENCES `obchod_mrizky` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `obchod_bunky`
--

/*!40000 ALTER TABLE `obchod_bunky` DISABLE KEYS */;
/*!40000 ALTER TABLE `obchod_bunky` ENABLE KEYS */;

--
-- Table structure for table `obchod_mrizky`
--

DROP TABLE IF EXISTS `obchod_mrizky`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `obchod_mrizky` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `text` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `obchod_mrizky`
--

/*!40000 ALTER TABLE `obchod_mrizky` DISABLE KEYS */;
/*!40000 ALTER TABLE `obchod_mrizky` ENABLE KEYS */;

--
-- Table structure for table `platby`
--

DROP TABLE IF EXISTS `platby`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `platby` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'kvůli indexu a vícenásobným platbám',
  `id_uzivatele` int(11) DEFAULT NULL,
  `fio_id` bigint(20) DEFAULT NULL,
  `castka` decimal(10,2) NOT NULL,
  `rok` smallint(6) NOT NULL,
  `provedeno` timestamp NOT NULL DEFAULT current_timestamp(),
  `provedl` int(11) NOT NULL,
  `poznamka` text DEFAULT NULL,
  `skryta_poznamka` text DEFAULT NULL,
  `pripsano_na_ucet_banky` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fio_id` (`fio_id`),
  KEY `id_uzivatele_rok` (`id_uzivatele`,`rok`),
  KEY `provedl` (`provedl`),
  CONSTRAINT `FK_platby_to_uzivatele_hodnoty` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON UPDATE CASCADE,
  CONSTRAINT `platby_ibfk_2` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`),
  CONSTRAINT `platby_ibfk_3` FOREIGN KEY (`provedl`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `platby`
--

/*!40000 ALTER TABLE `platby` DISABLE KEYS */;
/*!40000 ALTER TABLE `platby` ENABLE KEYS */;

--
-- Temporary table structure for view `platne_role`
--

DROP TABLE IF EXISTS `platne_role`;
/*!50001 DROP VIEW IF EXISTS `platne_role`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `platne_role` AS SELECT
 1 AS `id_role`,
  1 AS `kod_role`,
  1 AS `nazev_role`,
  1 AS `popis_role`,
  1 AS `rocnik_role`,
  1 AS `typ_role`,
  1 AS `vyznam_role`,
  1 AS `skryta`,
  1 AS `kategorie_role` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `platne_role_uzivatelu`
--

DROP TABLE IF EXISTS `platne_role_uzivatelu`;
/*!50001 DROP VIEW IF EXISTS `platne_role_uzivatelu`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `platne_role_uzivatelu` AS SELECT
 1 AS `id_uzivatele`,
  1 AS `id_role`,
  1 AS `posazen`,
  1 AS `posadil` */;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `prava_role`
--

DROP TABLE IF EXISTS `prava_role`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `prava_role` (
  `id_role` int(11) NOT NULL,
  `id_prava` int(11) NOT NULL,
  PRIMARY KEY (`id_role`,`id_prava`),
  KEY `id_prava` (`id_prava`),
  CONSTRAINT `FK_prava_role_to_r_prava_soupis` FOREIGN KEY (`id_prava`) REFERENCES `r_prava_soupis` (`id_prava`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `FK_prava_role_to_role_seznam` FOREIGN KEY (`id_role`) REFERENCES `role_seznam` (`id_role`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `prava_role`
--

/*!40000 ALTER TABLE `prava_role` DISABLE KEYS */;
INSERT INTO `prava_role` VALUES
(-202400023,1016),
(-202400019,1018),
(-202400018,1015),
(-202300023,1016),
(-202300019,1018),
(-202300018,1015),
(-2202,-2202),
(-2201,-2201),
(-2102,-2102),
(-2101,-2101),
(-2002,-2002),
(-2001,-2001),
(-1902,-1902),
(-1901,-1901),
(-1802,-1802),
(-1801,-1801),
(-1702,-1702),
(-1701,-1701),
(-1602,-1602),
(-1601,-1601),
(-1502,-1502),
(-1501,-1501),
(-1402,-1402),
(-1401,-1401),
(-1302,-1302),
(-1301,-1301),
(-1202,-1202),
(-1201,-1201),
(-1102,-1102),
(-1101,-1101),
(-1002,-1002),
(-1001,-1001),
(-902,-902),
(-901,-901),
(20,111),
(23,1032),
(23,1033),
(25,9);
/*!40000 ALTER TABLE `prava_role` ENABLE KEYS */;

--
-- Table structure for table `r_prava_soupis`
--

DROP TABLE IF EXISTS `r_prava_soupis`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `r_prava_soupis` (
  `id_prava` int(11) NOT NULL AUTO_INCREMENT,
  `jmeno_prava` varchar(255) NOT NULL,
  `popis_prava` text NOT NULL,
  PRIMARY KEY (`id_prava`)
) ENGINE=InnoDB AUTO_INCREMENT=1034 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `r_prava_soupis`
--

/*!40000 ALTER TABLE `r_prava_soupis` DISABLE KEYS */;
INSERT INTO `r_prava_soupis` VALUES
(-2202,'GC2022 přítomen',''),
(-2201,'GC2022 přihlášen',''),
(-2102,'GC2021 přítomen',''),
(-2101,'GC2021 přihlášen',''),
(-2002,'GC2020 přítomen',''),
(-2001,'GC2020 přihlášen',''),
(-1902,'GC2019 přítomen',''),
(-1901,'GC2019 přihlášen',''),
(-1802,'GC2018 přítomen',''),
(-1801,'GC2018 přihlášen',''),
(-1702,'GC2017 přítomen',''),
(-1701,'GC2017 přihlášen',''),
(-1602,'GC2016 přítomen',''),
(-1601,'GC2016 přihlášen',''),
(-1502,'GC2015 přítomen',''),
(-1501,'GC2015 přihlášen',''),
(-1402,'GC2014 přítomen',''),
(-1401,'GC2014 přihlášen',''),
(-1302,'GC2013 přítomen',''),
(-1301,'GC2013 přihlášen',''),
(-1202,'GC2012 přítomen',''),
(-1201,'GC2012 přihlášen',''),
(-1102,'GC2011 přítomen',''),
(-1101,'GC2011 přihlášen',''),
(-1002,'GC2010 přítomen',''),
(-1001,'GC2010 přihlášen',''),
(-902,'GC2009 přítomen',''),
(-901,'GC2009 přihlášen',''),
(9,'Přhlašování na dosud neotevřené aktivity','Může přihlašovat a odhlašovat lidi z aktivit, které ještě nejsou Připravené (jsou teprve Publikované)'),
(110,'Administrace - panel Nastavení','Systémové hodnoty pro Gamecon'),
(111,'Administrace - panel Peníze','Koutek pro šéfa financí GC'),
(1012,'Modré tričko za dosaženou slevu %MODRE_TRICKO_ZDARMA_OD%',''),
(1015,'Středeční noc zdarma',''),
(1016,'Nerušit automaticky objednávky','Uživateli se při nezaplacení včas nebudou automaticky rušit objednávky'),
(1018,'Nedělní noc zdarma',''),
(1019,'Sleva na aktivity','Sleva 40% na aktivity'),
(1020,'Dvě jakákoli trička zdarma',''),
(1021,'Právo na modré tričko','Může si objednávat modrá trička'),
(1022,'Právo na červené tričko','Může si objednávat červená trička'),
(1023,'Plná sleva na aktivity','Sleva 100% na aktivity'),
(1024,'Statistiky - tabulka účasti','V adminu v sekci statistiky v tabulce vlevo nahoře se tato role vypisuje'),
(1025,'Reporty - zahrnout do reportu \"Nepřihlášení a neubytovaní\"','V reportu Nepřihlášení a neubytovaní vypravěči se lidé na této židli vypisují'),
(1026,'Titul „organizátor“','V různých výpisech se označuje jako organizátor'),
(1027,'Unikátní židle','Uživatel může mít jen jednu židli s tímto právem'),
(1028,'Bez bonusu za vedení aktivit','Nedostává bonus za vedení aktivit ani za účast na technických aktivitách'),
(1029,'Čtvrteční noc zdarma',''),
(1030,'Páteční noc zdarma',''),
(1031,'Sobotní noc zdarma',''),
(1032,'Hromadná aktivace aktivit','Může použít \"Aktivovat hromadně\" v aktivitách'),
(1033,'Změna práv','Může měnit práva rolím a měnit role uživatelům');
/*!40000 ALTER TABLE `r_prava_soupis` ENABLE KEYS */;

--
-- Table structure for table `reporty`
--

DROP TABLE IF EXISTS `reporty`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reporty` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `skript` varchar(100) NOT NULL,
  `nazev` varchar(200) DEFAULT NULL,
  `format_xlsx` tinyint(1) DEFAULT 1,
  `format_html` tinyint(1) DEFAULT 1,
  `viditelny` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`skript`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reporty`
--

/*!40000 ALTER TABLE `reporty` DISABLE KEYS */;
INSERT INTO `reporty` VALUES
(1,'aktivity','Historie přihlášení na aktivity',1,1,1),
(32,'bfgr-report','<span id=\"bfgr\" class=\"hinted\">BFGR (celkový report) {ROK}<span class=\"hint\"><em>Big f**king Gandalf report</em> určený pro Gandalfovu Excelentní magii</span></span>',1,1,0),
(9,'duplicity','Duplicitní uživatelé',0,1,1),
(18,'finance-aktivity-bez-slev','Finance: Aktivity bez slev',1,1,1),
(17,'finance-lide-v-databazi-a-zustatky','Finance: Lidé v databázi + zůstatky z předchozího ročníku',1,1,1),
(19,'finance-prijmy-a-vydaje-infopultaka','Finance: Příjmy a výdaje infopulťáka',1,1,1),
(36,'finance-report-eshop','Finance: E-shop',1,1,1),
(35,'finance-report-neplaticu','Finance: Neplatiči k odhlášení',1,1,1),
(33,'finance-report-ubytovani','Ubytování',1,1,0),
(6,'grafy-ankety','Grafy k anketě',0,1,1),
(38,'infopult-report-nezkontrolovane-potvrzeni-rodicu','Infopult: Nezkontrolované potvrzení rodičů',1,1,1),
(15,'maily-dle-data-ucasti','Maily - nedávní účastníci',1,0,1),
(13,'maily-neprihlaseni','Maily – nepřihlášení na GC',1,1,1),
(12,'maily-prihlaseni','Maily – přihlášení na GC (vč. unsubscribed)',1,1,1),
(14,'maily-vypraveci','Maily – vypravěči (vč. unsubscribed)',1,1,1),
(8,'neprihlaseni-vypraveci','Nepřihlášení a neubytovaní vypravěči + další',1,1,1),
(5,'parovani-ankety','Párování ankety a údajů uživatelů',0,1,1),
(2,'pocty-her','Účastníci a počty jejich aktivit',1,0,1),
(3,'pocty-her-graf','Graf rozložení rozmanitosti her',0,1,1),
(34,'report-infopult-ucastnici-balicky','Infopult: Balíčky účastníků',0,1,1),
(37,'role-podle-rocniku','Počty rolí platných v ročnících',1,1,1),
(4,'rozesilani-ankety','Rozesílání ankety s tokenem',0,1,1),
(10,'stravenky','Stravenky uživatelů',0,1,1),
(11,'stravenky-bianco','Stravenky (bianco)',0,1,1),
(7,'update-zustatku','UPDATE příkaz zůstatků pro letošní GC',0,1,1),
(26,'zazemi-a-program-aktivity-pro-dotaznik-dle-linii','Zázemí & Program: Aktivity pro dotazník dle linií',1,1,1),
(28,'zazemi-a-program-casy-a-umisteni-aktivit','Zázemí & Program: Časy a umístění aktivit',1,1,1),
(20,'zazemi-a-program-drd-historie-ucasti','Zázemí & Program: DrD: Historie účasti',1,1,1),
(21,'zazemi-a-program-drd-seznam-prihlasenych-pro-aktualni-rok','Zázemí & Program: DrD: Seznam přihlášených pro aktuální rok',1,1,1),
(25,'zazemi-a-program-emaily-na-ucastniky-dle-linii','Zázemí & Program: Emaily na účastníky dle linií',1,1,1),
(24,'zazemi-a-program-emaily-na-vypravece-dle-linii','Zázemí & Program: Emaily na vypravěče dle linií',1,1,1),
(23,'zazemi-a-program-pocet-sledujicich-pro-aktualni-rok','Zázemí & Program: Počet sledujících pro aktuální rok',1,1,1),
(27,'zazemi-a-program-potvrzeni-pro-navstevniky-mladsi-patnacti-let','Zázemí & Program: Potvrzení pro návštěvníky mladší patnácti let',1,1,1),
(29,'zazemi-a-program-prehled-mistnosti','Zázemí & Program: Přehled místností',1,1,1),
(30,'zazemi-a-program-seznam-ucastniku-a-tricek','Zázemí & Program: Seznam účastníků a triček',1,1,0),
(31,'zazemi-a-program-seznam-ucastniku-a-tricek-grouped','Zázemí & Program: Seznam účastníků a triček (grouped)',1,1,1),
(22,'zazemi-a-program-zarizeni-mistnosti','Zázemí & Program: Zařízení místností',1,1,1);
/*!40000 ALTER TABLE `reporty` ENABLE KEYS */;

--
-- Table structure for table `reporty_log_pouziti`
--

DROP TABLE IF EXISTS `reporty_log_pouziti`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reporty_log_pouziti` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_reportu` int(10) unsigned NOT NULL,
  `id_uzivatele` int(11) NOT NULL,
  `format` varchar(10) NOT NULL,
  `cas_pouziti` datetime DEFAULT NULL,
  `casova_zona` varchar(100) DEFAULT NULL,
  UNIQUE KEY `id` (`id`),
  KEY `report_uzivatel` (`id_reportu`,`id_uzivatele`),
  KEY `FK_reporty_log_pouziti_to_uzivatele_hodnoty` (`id_uzivatele`),
  CONSTRAINT `FK_reporty_log_pouziti_to_reporty` FOREIGN KEY (`id_reportu`) REFERENCES `reporty` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `FK_reporty_log_pouziti_to_uzivatele_hodnoty` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `id_reportu` FOREIGN KEY (`id_reportu`) REFERENCES `reporty` (`id`) ON DELETE NO ACTION ON UPDATE CASCADE,
  CONSTRAINT `id_uzivatele` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON DELETE NO ACTION ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reporty_log_pouziti`
--

/*!40000 ALTER TABLE `reporty_log_pouziti` DISABLE KEYS */;
/*!40000 ALTER TABLE `reporty_log_pouziti` ENABLE KEYS */;

--
-- Table structure for table `reporty_quick`
--

DROP TABLE IF EXISTS `reporty_quick`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reporty_quick` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nazev` varchar(100) NOT NULL,
  `dotaz` text NOT NULL,
  `format_xlsx` tinyint(1) DEFAULT 1,
  `format_html` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reporty_quick`
--

/*!40000 ALTER TABLE `reporty_quick` DISABLE KEYS */;
INSERT INTO `reporty_quick` VALUES
(1,'Potvrzení pro návštěvníky mladší patnácti let','SELECT *\nFROM uzivatele_hodnoty\nWHERE (YEAR(\'{gcBeziOd}\') - YEAR(datum_narozeni) -\n       IF(DATE_FORMAT(\'{gcBeziOd}\', \'%m%d\') < DATE_FORMAT(datum_narozeni, \'%m%d\'), 1, 0)) < 15\nORDER BY COALESCE(potvrzeni_zakonneho_zastupce, \'0001-01-01\') ASC,\n         registrovan DESC;',1,1);
/*!40000 ALTER TABLE `reporty_quick` ENABLE KEYS */;

--
-- Table structure for table `role_seznam`
--

DROP TABLE IF EXISTS `role_seznam`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_seznam` (
  `id_role` int(11) NOT NULL AUTO_INCREMENT,
  `kod_role` varchar(36) NOT NULL,
  `nazev_role` varchar(255) NOT NULL,
  `popis_role` text NOT NULL,
  `rocnik_role` int(11) NOT NULL,
  `typ_role` varchar(24) NOT NULL,
  `vyznam_role` varchar(48) NOT NULL,
  `skryta` tinyint(1) DEFAULT 0,
  `kategorie_role` tinyint(3) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_role`),
  UNIQUE KEY `nazev_role` (`nazev_role`),
  UNIQUE KEY `kod_role` (`kod_role`),
  KEY `typ_zidle` (`typ_role`),
  KEY `vyznam` (`vyznam_role`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_seznam`
--

/*!40000 ALTER TABLE `role_seznam` DISABLE KEYS */;
INSERT INTO `role_seznam` VALUES
(-202400029,'GC2024_ZKONTROLOVANE_UDAJE','Zkontrolované údaje','Zkontrolované údaje',2024,'rocnikova','ZKONTROLOVANE_UDAJE',1,0),
(-202400028,'GC2024_SOBOTNI_NOC_ZDARMA','Sobotní noc zdarma','',2024,'rocnikova','SOBOTNI_NOC_ZDARMA',0,1),
(-202400025,'GC2024_BRIGADNIK','Brigádník','Zase práce?',2024,'rocnikova','BRIGADNIK',0,0),
(-202400024,'GC2024_HERMAN','Herman','Živoucí návod deskových her sloužící ve jménu Gameconu',2024,'rocnikova','HERMAN',0,1),
(-202400023,'GC2024_NEODHLASOVAT','Neodhlašovat','Může zaplatit až na místě. Je chráněn před odhlašováním neplatičů a nezaplacených objednávek.',2024,'rocnikova','NEODHLASOVAT',0,1),
(-202400019,'GC2024_NEDELNI_NOC_ZDARMA','Nedělní noc zdarma','',2024,'rocnikova','NEDELNI_NOC_ZDARMA',0,1),
(-202400018,'GC2024_STREDECNI_NOC_ZDARMA','Středeční noc zdarma','',2024,'rocnikova','STREDECNI_NOC_ZDARMA',0,1),
(-202400017,'GC2024_DOBROVOLNIK_SENIOR','Dobrovolník senior','Dobrovolník senior',2024,'rocnikova','DOBROVOLNIK_SENIOR',0,1),
(-202400013,'GC2024_PARTNER','Partner','Partner',2024,'rocnikova','PARTNER',0,1),
(-202400008,'GC2024_INFOPULT','Infopult','Infopult',2024,'rocnikova','INFOPULT',0,1),
(-202400007,'GC2024_ZAZEMI','Zázemí','Zázemí',2024,'rocnikova','ZAZEMI',0,0),
(-202400006,'GC2024_VYPRAVEC','Vypravěč','Vypravěč',2024,'rocnikova','VYPRAVEC',0,1),
(-202300028,'GC2023_SOBOTNI_NOC_ZDARMA','GC2023 Sobotní noc zdarma','',2023,'rocnikova','SOBOTNI_NOC_ZDARMA',0,1),
(-202300025,'GC2023_BRIGADNIK','GC2023 Brigádník','Zase práce?',2023,'rocnikova','BRIGADNIK',0,1),
(-202300024,'GC2023_HERMAN','GC-1 Herman','Živoucí návod deskových her sloužící ve jménu Gameconu',-1,'rocnikova','HERMAN',0,1),
(-202300023,'GC2023_NEODHLASOVAT','GC-1 Neodhlašovat','Může zaplatit až na místě. Je chráněn před odhlašováním neplatičů a nezaplacených objednávek.',-1,'rocnikova','NEODHLASOVAT',0,1),
(-202300019,'GC2023_NEDELNI_NOC_ZDARMA','GC2023 Nedělní noc zdarma','',2023,'rocnikova','NEDELNI_NOC_ZDARMA',0,1),
(-202300018,'GC2023_STREDECNI_NOC_ZDARMA','GC2023 Středeční noc zdarma','',2023,'rocnikova','STREDECNI_NOC_ZDARMA',0,1),
(-202300017,'GC2023_DOBROVOLNIK_SENIOR','GC2023 Dobrovolník senior','Dobrovolník senior',2023,'rocnikova','DOBROVOLNIK_SENIOR',0,1),
(-202300013,'GC2023_PARTNER','GC2023 Partner','Partner',2023,'rocnikova','PARTNER',0,1),
(-202300008,'GC2023_INFOPULT','GC2023 Infopult','Infopult',2023,'rocnikova','INFOPULT',0,1),
(-202300007,'GC2023_ZAZEMI','GC2023 Zázemí','Zázemí',2023,'rocnikova','ZAZEMI',0,0),
(-202300006,'GC2023_VYPRAVEC','GC2023 Vypravěč','Vypravěč',2023,'rocnikova','VYPRAVEC',0,1),
(-2403,'GC2024_ODJEL','GC2024 odjel','GC2024 odjel',2024,'ucast','ODJEL',0,0),
(-2402,'GC2024_PRITOMEN','GC2024 přítomen','GC2024 přítomen',2024,'ucast','PRITOMEN',0,0),
(-2401,'GC2024_PRIHLASEN','GC2024 přihlášen','GC2024 přihlášen',2024,'ucast','PRIHLASEN',0,0),
(-2303,'GC2023_ODJEL','GC2023 odjel','GC2023 odjel',2023,'ucast','ODJEL',0,0),
(-2302,'GC2023_PRITOMEN','GC2023 přítomen','GC2023 přítomen',2023,'ucast','PRITOMEN',0,0),
(-2301,'GC2023_PRIHLASEN','GC2023 přihlášen','GC2023 přihlášen',2023,'ucast','PRIHLASEN',0,0),
(-2203,'GC2022_ODJEL','GC2022 odjel','',2022,'ucast','ODJEL',0,0),
(-2202,'GC2022_PRITOMEN','GC2022 přítomen','',2022,'ucast','PRITOMEN',0,0),
(-2201,'GC2022_PRIHLASEN','GC2022 přihlášen','',2022,'ucast','PRIHLASEN',0,0),
(-2103,'GC2021_ODJEL','GC2021 odjel','',2021,'ucast','ODJEL',0,0),
(-2102,'GC2021_PRITOMEN','GC2021 přítomen','',2021,'ucast','PRITOMEN',0,0),
(-2101,'GC2021_PRIHLASEN','GC2021 přihlášen','',2021,'ucast','PRIHLASEN',0,0),
(-2003,'GC2020_ODJEL','GC2020 odjel','',2020,'ucast','ODJEL',0,0),
(-2002,'GC2020_PRITOMEN','GC2020 přítomen','',2020,'ucast','PRITOMEN',0,0),
(-2001,'GC2020_PRIHLASEN','GC2020 přihlášen','',2020,'ucast','PRIHLASEN',0,0),
(-1903,'GC2019_ODJEL','GC2019 odjel','',2019,'ucast','ODJEL',0,0),
(-1902,'GC2019_PRITOMEN','GC2019 přítomen','',2019,'ucast','PRITOMEN',0,0),
(-1901,'GC2019_PRIHLASEN','GC2019 přihlášen','',2019,'ucast','PRIHLASEN',0,0),
(-1803,'GC2018_ODJEL','GC2018 odjel','',2018,'ucast','ODJEL',0,0),
(-1802,'GC2018_PRITOMEN','GC2018 přítomen','',2018,'ucast','PRITOMEN',0,0),
(-1801,'GC2018_PRIHLASEN','GC2018 přihlášen','',2018,'ucast','PRIHLASEN',0,0),
(-1703,'GC2017_ODJEL','GC2017 odjel','',2017,'ucast','ODJEL',0,0),
(-1702,'GC2017_PRITOMEN','GC2017 přítomen','',2017,'ucast','PRITOMEN',0,0),
(-1701,'GC2017_PRIHLASEN','GC2017 přihlášen','',2017,'ucast','PRIHLASEN',0,0),
(-1603,'GC2016_ODJEL','GC2016 odjel','',2016,'ucast','ODJEL',0,0),
(-1602,'GC2016_PRITOMEN','GC2016 přítomen','',2016,'ucast','PRITOMEN',0,0),
(-1601,'GC2016_PRIHLASEN','GC2016 přihlášen','',2016,'ucast','PRIHLASEN',0,0),
(-1503,'GC2015_ODJEL','GC2015 odjel','',2015,'ucast','ODJEL',0,0),
(-1502,'GC2015_PRITOMEN','GC2015 přítomen','',2015,'ucast','PRITOMEN',0,0),
(-1501,'GC2015_PRIHLASEN','GC2015 přihlášen','',2015,'ucast','PRIHLASEN',0,0),
(-1403,'GC2014_ODJEL','GC2014 odjel','',2014,'ucast','ODJEL',0,0),
(-1402,'GC2014_PRITOMEN','GC2014 přítomen','',2014,'ucast','PRITOMEN',0,0),
(-1401,'GC2014_PRIHLASEN','GC2014 přihlášen','',2014,'ucast','PRIHLASEN',0,0),
(-1302,'GC2013_PRITOMEN','GC2013 přítomen','',2013,'ucast','PRITOMEN',0,0),
(-1301,'GC2013_PRIHLASEN','GC2013 přihlášen','',2013,'ucast','PRIHLASEN',0,0),
(-1202,'GC2012_PRITOMEN','GC2012 přítomen','',2012,'ucast','PRITOMEN',0,0),
(-1201,'GC2012_PRIHLASEN','GC2012 přihlášen','',2012,'ucast','PRIHLASEN',0,0),
(-1102,'GC2011_PRITOMEN','GC2011 přítomen','',2011,'ucast','PRITOMEN',0,0),
(-1101,'GC2011_PRIHLASEN','GC2011 přihlášen','',2011,'ucast','PRIHLASEN',0,0),
(-1002,'GC2010_PRITOMEN','GC2010 přítomen','',2010,'ucast','PRITOMEN',0,0),
(-1001,'GC2010_PRIHLASEN','GC2010 přihlášen','',2010,'ucast','PRIHLASEN',0,0),
(-902,'GC2009_PRITOMEN','GC2009 přítomen','',2009,'ucast','PRITOMEN',0,0),
(-901,'GC2009_PRIHLASEN','GC2009 přihlášen','',2009,'ucast','PRIHLASEN',0,0),
(20,'CFO','CFO','Organizátor, který může nakládat s financemi GC',-1,'trvala','CFO',0,0),
(21,'PUL_ORG_UBYTKO','Půl-org s ubytkem','Krom jiného ubytování zdarma',-1,'trvala','PUL_ORG_UBYTKO',0,0),
(22,'PUL_ORG_TRICKO','Půl-org s tričkem','Krom jiného trička zdarma',-1,'trvala','PUL_ORG_TRICKO',0,0),
(23,'CLEN_RADY','Člen rady','Členové rady mají zvláštní zodpovědnost a pravomoce',-1,'trvala','CLEN_RADY',0,0),
(24,'SEF_INFOPULTU','Šéf infopultu','S pravomocemi dělat větší zásahy u přhlášených',-1,'trvala','SEF_INFOPULTU',0,0),
(25,'SEF_PROGRAMU','Šéf programu','Všeobecné \"vedení\" programu - obecná dramaturgie, rozvoj sekcí, finance programu',-1,'trvala','SEF_PROGRAMU',0,0);
/*!40000 ALTER TABLE `role_seznam` ENABLE KEYS */;

--
-- Table structure for table `role_texty_podle_uzivatele`
--

DROP TABLE IF EXISTS `role_texty_podle_uzivatele`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_texty_podle_uzivatele` (
  `vyznam_role` varchar(48) NOT NULL,
  `id_uzivatele` int(11) NOT NULL,
  `popis_role` text DEFAULT NULL,
  PRIMARY KEY (`vyznam_role`,`id_uzivatele`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_texty_podle_uzivatele`
--

/*!40000 ALTER TABLE `role_texty_podle_uzivatele` DISABLE KEYS */;
/*!40000 ALTER TABLE `role_texty_podle_uzivatele` ENABLE KEYS */;

--
-- Table structure for table `shop_nakupy`
--

DROP TABLE IF EXISTS `shop_nakupy`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shop_nakupy` (
  `id_nakupu` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_uzivatele` int(11) NOT NULL,
  `id_objednatele` int(11) DEFAULT NULL,
  `id_predmetu` int(11) NOT NULL,
  `rok` smallint(6) NOT NULL,
  `cena_nakupni` decimal(6,2) NOT NULL COMMENT 'aktuální cena v okamžiku nákupu (bez slev)',
  `datum` timestamp NOT NULL DEFAULT current_timestamp(),
  UNIQUE KEY `id_nakupu` (`id_nakupu`),
  KEY `rok_id_uzivatele` (`rok`,`id_uzivatele`),
  KEY `id_predmetu` (`id_predmetu`),
  KEY `id_uzivatele` (`id_uzivatele`),
  KEY `id_objednatele` (`id_objednatele`),
  CONSTRAINT `FK_shop_nakupy_to_shop_predmety` FOREIGN KEY (`id_predmetu`) REFERENCES `shop_predmety` (`id_predmetu`) ON UPDATE CASCADE,
  CONSTRAINT `FK_shop_nakupy_to_uzivatele_hodnoty` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON UPDATE CASCADE,
  CONSTRAINT `shop_nakupy_ibfk_3` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`),
  CONSTRAINT `shop_nakupy_ibfk_4` FOREIGN KEY (`id_predmetu`) REFERENCES `shop_predmety` (`id_predmetu`),
  CONSTRAINT `shop_nakupy_ibfk_5` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`),
  CONSTRAINT `shop_nakupy_ibfk_6` FOREIGN KEY (`id_predmetu`) REFERENCES `shop_predmety` (`id_predmetu`),
  CONSTRAINT `shop_nakupy_ibfk_7` FOREIGN KEY (`id_objednatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `shop_nakupy`
--

/*!40000 ALTER TABLE `shop_nakupy` DISABLE KEYS */;
/*!40000 ALTER TABLE `shop_nakupy` ENABLE KEYS */;

--
-- Table structure for table `shop_nakupy_zrusene`
--

DROP TABLE IF EXISTS `shop_nakupy_zrusene`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shop_nakupy_zrusene` (
  `id_nakupu` bigint(20) unsigned NOT NULL,
  `id_uzivatele` int(11) NOT NULL,
  `id_predmetu` int(11) NOT NULL,
  `rocnik` smallint(6) NOT NULL,
  `cena_nakupni` decimal(6,2) NOT NULL COMMENT 'aktuální cena v okamžiku nákupu (bez slev)',
  `datum_nakupu` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `datum_zruseni` timestamp NOT NULL DEFAULT current_timestamp(),
  `zdroj_zruseni` varchar(255) DEFAULT NULL,
  KEY `FK_zrusene_objednavky_to_shop_predmety` (`id_predmetu`),
  KEY `FK_zrusene_objednavky_to_uzivatele_hodnoty` (`id_uzivatele`),
  KEY `datum_zruseni` (`datum_zruseni`),
  KEY `zdroj_zruseni` (`zdroj_zruseni`),
  CONSTRAINT `FK_zrusene_objednavky_to_shop_predmety` FOREIGN KEY (`id_predmetu`) REFERENCES `shop_predmety` (`id_predmetu`) ON UPDATE CASCADE,
  CONSTRAINT `FK_zrusene_objednavky_to_uzivatele_hodnoty` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `shop_nakupy_zrusene`
--

/*!40000 ALTER TABLE `shop_nakupy_zrusene` DISABLE KEYS */;
/*!40000 ALTER TABLE `shop_nakupy_zrusene` ENABLE KEYS */;

--
-- Table structure for table `shop_predmety`
--

DROP TABLE IF EXISTS `shop_predmety`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shop_predmety` (
  `id_predmetu` int(11) NOT NULL AUTO_INCREMENT,
  `nazev` varchar(255) NOT NULL,
  `kod_predmetu` varchar(255) NOT NULL,
  `model_rok` smallint(6) NOT NULL,
  `cena_aktualni` decimal(6,2) NOT NULL,
  `stav` tinyint(4) NOT NULL COMMENT '0-mimo, 1-veřejný, 2-podpultový, 3-pozastavený',
  `auto` tinyint(4) NOT NULL COMMENT 'automaticky objednané',
  `nabizet_do` datetime DEFAULT NULL COMMENT 'automatizovaná náhrada za stav 3',
  `kusu_vyrobeno` smallint(6) DEFAULT NULL,
  `typ` tinyint(4) NOT NULL COMMENT '1-předmět, 2-ubytování, 3-tričko, 4-jídlo, 5-vstupné, 6-parcon, 7-vyplaceni',
  `ubytovani_den` tinyint(4) DEFAULT NULL COMMENT 'změněn význam na "obecný atribut den"',
  `popis` varchar(2000) NOT NULL,
  `kategorie_predmetu` int(10) unsigned DEFAULT NULL,
  `se_slevou` tinyint(3) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_predmetu`),
  UNIQUE KEY `UNIQ_nazev_model_rok` (`nazev`,`model_rok`),
  UNIQUE KEY `kod_predmetu_model_rok` (`kod_predmetu`,`model_rok`),
  KEY `kategorie_predmetu` (`kategorie_predmetu`)
) ENGINE=InnoDB AUTO_INCREMENT=88 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `shop_predmety`
--

/*!40000 ALTER TABLE `shop_predmety` DISABLE KEYS */;
INSERT INTO `shop_predmety` VALUES
(1,'Dobrovolné vstupné','dobrovolne_vstupne',2015,0.00,2,0,'2015-06-30 23:59:59',NULL,5,NULL,'',NULL,0),
(2,'Dobrovolné vstupné (pozdě)','dobrovolne_vstupne_pozde',2015,0.00,3,0,NULL,NULL,5,NULL,'',NULL,0),
(3,'Parcon','parcon',2017,150.00,0,0,NULL,NULL,6,NULL,'',NULL,0),
(4,'Proplacení bonusu','proplaceni_bonusu',2019,0.00,1,0,NULL,NULL,7,NULL,'Pro vyplacení bonusů za vedení aktivit',NULL,0),
(5,'Duna kostka','duna_kostka',2022,25.00,1,0,'2022-07-13 23:59:00',500,1,NULL,'Duna',2,0),
(6,'Placka','placka',2022,25.00,1,0,'2022-07-13 23:59:00',450,1,NULL,'',1,0),
(7,'Ponožky (vel. 42-45)','ponozky_vel_4245',2022,100.00,1,0,'2022-07-13 23:59:00',70,1,NULL,'',7,0),
(8,'Ponožky (vel. 38-39)','ponozky_vel_3839',2022,100.00,1,0,'2022-07-13 23:59:00',100,1,NULL,'',7,0),
(9,'Nicknack','nicknack',2022,60.00,1,0,'2022-07-13 23:59:00',500,1,NULL,'',6,0),
(10,'Blok','blok',2022,50.00,1,0,'2022-07-13 23:59:00',100,1,NULL,'',5,0),
(11,'Taška','taska',2022,150.00,1,0,'2022-07-13 23:59:00',300,1,NULL,'',NULL,0),
(12,'Spacák neděle','spacak_nedele',2022,100.00,1,0,'2022-07-10 23:59:59',50,2,4,'',NULL,0),
(13,'Spacák sobota','spacak_sobota',2022,100.00,1,0,'2022-07-10 23:59:59',50,2,3,'',NULL,0),
(14,'Spacák pátek','spacak_patek',2022,100.00,1,0,'2022-07-10 23:59:59',50,2,2,'',NULL,0),
(15,'Spacák čtvrtek','spacak_ctvrtek',2022,100.00,1,0,'2022-07-10 23:59:59',50,2,1,'',NULL,0),
(16,'Spacák středa','spacak_streda',2022,100.00,1,0,'2022-07-10 23:59:59',50,2,0,'',NULL,0),
(17,'Trojlůžák neděle','trojluzak_nedele',2022,250.00,1,0,'2022-07-10 23:59:59',200,2,4,'',NULL,0),
(18,'Trojlůžák sobota','trojluzak_sobota',2022,250.00,1,0,'2022-07-10 23:59:59',200,2,3,'',NULL,0),
(19,'Trojlůžák pátek','trojluzak_patek',2022,250.00,1,0,'2022-07-10 23:59:59',200,2,2,'',NULL,0),
(20,'Trojlůžák čtvrtek','trojluzak_ctvrtek',2022,250.00,1,0,'2022-07-10 23:59:59',200,2,1,'',NULL,0),
(21,'Trojlůžák středa','trojluzak_streda',2022,250.00,1,0,'2022-07-10 23:59:59',200,2,0,'',NULL,0),
(22,'Dvojlůžák neděle','dvojluzak_nedele',2022,300.00,1,0,'2022-07-10 23:59:59',245,2,4,'',NULL,0),
(23,'Dvojlůžák sobota','dvojluzak_sobota',2022,300.00,1,0,'2022-07-10 23:59:59',245,2,3,'',NULL,0),
(24,'Dvojlůžák pátek','dvojluzak_patek',2022,300.00,1,0,'2022-07-10 23:59:59',245,2,2,'',NULL,0),
(25,'Dvojlůžák čtvrtek','dvojluzak_ctvrtek',2022,300.00,1,0,'2022-07-10 23:59:59',245,2,1,'',NULL,0),
(26,'Dvojlůžák středa','dvojluzak_streda',2022,300.00,1,0,'2022-07-10 23:59:59',245,2,0,'',NULL,0),
(27,'Jednolůžák neděle','jednoluzak_nedele',2022,400.00,1,0,'2022-07-10 23:59:59',11,2,4,'',NULL,0),
(28,'Jednolůžák sobota','jednoluzak_sobota',2022,400.00,1,0,'2022-07-10 23:59:59',11,2,3,'',NULL,0),
(29,'Jednolůžák pátek','jednoluzak_patek',2022,400.00,1,0,'2022-07-10 23:59:59',11,2,2,'',NULL,0),
(30,'Jednolůžák čtvrtek','jednoluzak_ctvrtek',2022,400.00,1,0,'2022-07-10 23:59:59',11,2,1,'',NULL,0),
(31,'Jednolůžák středa','jednoluzak_streda',2022,400.00,1,0,'2022-07-10 23:59:59',11,2,0,'',NULL,0),
(32,'Tričko modré pánské XXL','tricko_modre_panske_xxl',2022,200.00,2,0,'2022-06-30 23:59:00',NULL,3,NULL,'',3,1),
(33,'Tričko modré pánské XL','tricko_modre_panske_xl',2022,200.00,2,0,'2022-06-30 23:59:00',NULL,3,NULL,'',3,1),
(34,'Tričko modré pánské S','tricko_modre_panske_s',2022,200.00,2,0,'2022-06-30 23:59:00',NULL,3,NULL,'',3,1),
(35,'Tričko modré pánské M','tricko_modre_panske_m',2022,200.00,2,0,'2022-06-30 23:59:00',NULL,3,NULL,'',3,1),
(36,'Tričko modré pánské L','tricko_modre_panske_l',2022,200.00,2,0,'2022-06-30 23:59:00',NULL,3,NULL,'',3,1),
(37,'Tričko červené pánské XXL','tricko_cervene_panske_xxl',2022,200.00,2,0,'2022-06-30 23:59:00',NULL,3,NULL,'',3,1),
(38,'Tričko červené pánské XL','tricko_cervene_panske_xl',2022,200.00,2,0,'2022-06-30 23:59:00',NULL,3,NULL,'',3,1),
(39,'Tričko červené pánské S','tricko_cervene_panske_s',2022,200.00,2,0,'2022-06-30 23:59:00',NULL,3,NULL,'',3,1),
(40,'Tričko červené pánské M','tricko_cervene_panske_m',2022,200.00,2,0,'2022-06-30 23:59:00',NULL,3,NULL,'',3,1),
(41,'Tričko červené pánské L','tricko_cervene_panske_l',2022,200.00,2,0,'2022-06-30 23:59:00',NULL,3,NULL,'',3,1),
(42,'Tričko účastnické pánské XXL','tricko_ucastnicke_panske_xxl',2022,250.00,1,0,'2022-06-30 23:59:00',NULL,3,NULL,'',3,0),
(43,'Tričko účastnické pánské XL','tricko_ucastnicke_panske_xl',2022,250.00,1,0,'2022-06-30 23:59:00',NULL,3,NULL,'',3,0),
(44,'Tričko účastnické pánské S','tricko_ucastnicke_panske_s',2022,250.00,1,0,'2022-06-30 23:59:00',NULL,3,NULL,'',3,0),
(45,'Tričko účastnické pánské M','tricko_ucastnicke_panske_m',2022,250.00,1,0,'2022-06-30 23:59:00',NULL,3,NULL,'',3,0),
(46,'Tričko účastnické pánské L','tricko_ucastnicke_panske_l',2022,250.00,1,0,'2022-06-30 23:59:00',NULL,3,NULL,'',3,0),
(47,'Tílko modré dámské S','tilko_modre_damske_s',2022,200.00,2,0,'2022-06-30 23:59:00',NULL,3,NULL,'',4,1),
(48,'Tílko modré dámské M','tilko_modre_damske_m',2022,200.00,2,0,'2022-06-30 23:59:00',NULL,3,NULL,'',4,1),
(49,'Tílko modré dámské L','tilko_modre_damske_l',2022,200.00,2,0,'2022-06-30 23:59:00',NULL,3,NULL,'',4,1),
(50,'Tílko modré dámské XL','tilko_modre_damske_xl',2022,200.00,2,0,'2022-06-30 23:59:00',NULL,3,NULL,'',4,1),
(51,'Tílko červené dámské S','tilko_cervene_damske_s',2022,200.00,2,0,'2022-06-30 23:59:00',NULL,3,NULL,'',4,1),
(52,'Tílko červené dámské M','tilko_cervene_damske_m',2022,200.00,2,0,'2022-06-30 23:59:00',NULL,3,NULL,'',4,1),
(53,'Tílko červené dámské L','tilko_cervene_damske_l',2022,200.00,2,0,'2022-06-30 23:59:00',NULL,3,NULL,'',4,1),
(54,'Tílko červené dámské XL','tilko_cervene_damske_xl',2022,200.00,2,0,'2022-06-30 23:59:00',NULL,3,NULL,'',4,1),
(55,'Tílko účastnické dámské S','tilko_ucastnicke_damske_s',2022,250.00,1,0,'2022-06-30 23:59:00',NULL,3,NULL,'',4,0),
(56,'Tílko účastnické dámské M','tilko_ucastnicke_damske_m',2022,250.00,1,0,'2022-06-30 23:59:00',NULL,3,NULL,'',4,0),
(57,'Tílko účastnické dámské L','tilko_ucastnicke_damske_l',2022,250.00,1,0,'2022-06-30 23:59:00',NULL,3,NULL,'',4,0),
(58,'Tílko účastnické dámské XL','tilko_ucastnicke_damske_xl',2022,250.00,1,0,'2022-06-30 23:59:00',NULL,3,NULL,'',4,0),
(59,'Večeře neděle levnější','vecere_nedele_levnejsi',2022,100.00,0,0,'2023-01-27 11:17:08',NULL,4,4,'',NULL,0),
(60,'Oběd neděle levnější','obed_nedele_levnejsi',2022,100.00,0,0,'2023-01-27 11:17:08',NULL,4,4,'',NULL,0),
(61,' Snídaně neděle','snidane_nedele',2022,45.00,1,0,'2022-07-18 00:00:00',NULL,4,4,'',NULL,0),
(62,'Večeře sobota levnější','vecere_sobota_levnejsi',2022,100.00,0,0,'2023-01-27 11:17:08',NULL,4,3,'',NULL,0),
(63,'Oběd sobota levnější','obed_sobota_levnejsi',2022,100.00,0,0,'2023-01-27 11:17:08',NULL,4,3,'',NULL,0),
(64,' Snídaně sobota','snidane_sobota',2022,45.00,1,0,'2022-07-18 00:00:00',NULL,4,3,'',NULL,0),
(65,'Večeře pátek levnější','vecere_patek_levnejsi',2022,100.00,0,0,'2023-01-27 11:17:08',NULL,4,2,'',NULL,0),
(66,'Oběd pátek levnější','obed_patek_levnejsi',2022,100.00,0,0,'2023-01-27 11:17:08',NULL,4,2,'',NULL,0),
(67,' Snídaně pátek','snidane_patek',2022,45.00,1,0,'2022-07-18 00:00:00',NULL,4,2,'',NULL,0),
(68,'Večeře čtvrtek levnější','vecere_ctvrtek_levnejsi',2022,100.00,0,0,'2023-01-27 11:17:08',NULL,4,1,'',NULL,0),
(69,'Oběd čtvrtek levnější','obed_ctvrtek_levnejsi',2022,100.00,0,0,'2023-01-27 11:17:08',NULL,4,1,'',NULL,0),
(70,'Dobrovolné vstupné (pozdě)','dobrovolne_vstupne_pozde',2022,0.00,3,0,NULL,NULL,5,NULL,'',NULL,0),
(71,'Kostka kruhy','kostka_kruhy',2022,25.00,2,0,'2022-07-13 23:59:00',NULL,5,NULL,'',2,0),
(72,'Proplacení bonusu','proplaceni_bonusu',2022,0.00,1,0,NULL,NULL,7,NULL,'Pro vyplacení bonusů za vedení aktivit',NULL,0),
(73,'COVID test','covid_test',2022,250.00,2,0,'2022-07-11 00:00:00',NULL,1,NULL,'',8,0),
(74,'Tričko modré pánské XXXL','tricko_modre_panske_xxxl',2022,200.00,2,0,'2022-06-30 23:59:00',NULL,3,NULL,'',3,1),
(75,'Tričko účastnické pánské XXXL','tricko_ucastnicke_panske_xxxl',2022,250.00,1,0,'2022-06-30 23:59:00',NULL,3,NULL,'',3,0),
(76,'Večeře neděle','vecere_nedele',2022,120.00,1,0,'2022-07-18 00:00:00',NULL,4,4,'',NULL,0),
(77,'Oběd neděle','obed_nedele',2022,120.00,1,0,'2022-07-18 00:00:00',NULL,4,4,'',NULL,0),
(78,'Večeře sobota','vecere_sobota',2022,120.00,1,0,'2022-07-18 00:00:00',NULL,4,3,'',NULL,0),
(79,'Oběd sobota','obed_sobota',2022,120.00,1,0,'2022-07-18 00:00:00',NULL,4,3,'',NULL,0),
(80,'Večeře pátek','vecere_patek',2022,120.00,1,0,'2022-07-18 00:00:00',NULL,4,2,'',NULL,0),
(81,'Oběd pátek','obed_patek',2022,120.00,1,0,'2022-07-18 00:00:00',NULL,4,2,'',NULL,0),
(82,'Večeře čtvrtek','vecere_ctvrtek',2022,120.00,1,0,'2022-07-18 00:00:00',NULL,4,1,'',NULL,0),
(83,'Oběd čtvrtek','obed_ctvrtek',2022,120.00,1,0,'2022-07-18 00:00:00',NULL,4,1,'',NULL,0),
(84,'Tričko červené pánské XXXL','tricko_cervene_panske_xxxl',2022,200.00,2,0,'2022-06-30 23:59:00',NULL,3,NULL,'',3,1),
(85,'Tílko modré dámské XXL','tilko_modre_damske_xxl',2022,200.00,2,0,'2022-06-30 23:59:00',NULL,3,NULL,'',4,1),
(86,'Tílko červené dámské XXL','tilko_cervene_damske_xxl',2022,200.00,2,0,'2022-06-30 23:59:00',NULL,3,NULL,'',4,1),
(87,'Tílko účastnické dámské XXL','tilko_ucastnicke_damske_xxl',2022,250.00,1,0,'2022-06-30 23:59:00',NULL,3,NULL,'',4,0);
/*!40000 ALTER TABLE `shop_predmety` ENABLE KEYS */;

--
-- Table structure for table `sjednocene_tagy`
--

DROP TABLE IF EXISTS `sjednocene_tagy`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sjednocene_tagy` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_kategorie_tagu` int(10) unsigned NOT NULL,
  `nazev` varchar(128) NOT NULL,
  `poznamka` text NOT NULL,
  PRIMARY KEY (`nazev`),
  UNIQUE KEY `id` (`id`),
  KEY `FK_sjednocene_tagy_to_kategorie_sjednocenych_tagu` (`id_kategorie_tagu`),
  CONSTRAINT `FK_sjednocene_tagy_to_kategorie_sjednocenych_tagu` FOREIGN KEY (`id_kategorie_tagu`) REFERENCES `kategorie_sjednocenych_tagu` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12367 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sjednocene_tagy`
--

/*!40000 ALTER TABLE `sjednocene_tagy` DISABLE KEYS */;
INSERT INTO `sjednocene_tagy` VALUES
(45,41,'13th Age',''),
(2309,14,'Abstraktní',''),
(109,3,'Akční',''),
(769,4,'Alternativní historie',''),
(4944,26,'Amazonie','Pravděpodobně ročníkově specifický štítek prostředí LKD.'),
(66,14,'Ameritrash',''),
(99,41,'Apocalypse World',''),
(277,36,'Argumentační',''),
(8282,4,'Asterion',''),
(3665,5,'Atmosférická','; Atmosférická'),
(776,41,'Aye Dark Overlord',''),
(919,37,'Běhací',''),
(232,41,'Bez systému',''),
(7991,33,'Bez vypravěče',''),
(2615,41,'Blades in the Dark',''),
(87,26,'Blázinec','Pravděpodobně ročníkově specifický štítek prostředí LKD.'),
(2790,41,'Bliss Stage',''),
(2784,41,'Capes RPG',''),
(12347,49,'CGE',''),
(12348,41,'City of Mist',''),
(2182,4,'Cthulhu Mythos',''),
(227,3,'Cyberpunk',''),
(9246,4,'Časová smyčka',''),
(768,41,'Časy se mění',''),
(12366,26,'Čechy','Letošní prostředí LKD'),
(84,26,'Čína','Pravděpodobně ročníkově specifický štítek LKD'),
(8161,41,'Dakara',''),
(2648,4,'Dark Fantasy',''),
(1837,16,'Debata',''),
(97,14,'Deckbuilding',''),
(72,1,'Deluxe',''),
(10284,38,'Demohraní',''),
(9107,49,'Deskofobie',''),
(88,3,'Detektivka',''),
(12,41,'DnD 4e',''),
(50,41,'DnD 5e',''),
(9342,41,'DnD 5e klon',''),
(2598,3,'Dobrodružná',''),
(13,41,'Dogs in the Vineyard',''),
(60,41,'Dračí doupě','Kvůli přednáškám.'),
(2,41,'Dračí kutloch',''),
(49,41,'DrD 1.0',''),
(5,41,'DrD 1.6','; Štítek by měl být specifický jako všechny ostatní, přemazal bych nejpravděpodobnější možností.'),
(18,41,'DrD II',''),
(9333,41,'DrD klon',''),
(38,41,'DrD+',''),
(7,41,'Dread',''),
(9008,41,'Dungeon World',''),
(8109,33,'Dungeoncrawl',''),
(920,5,'Edukativní',''),
(5078,38,'Ekonomická',''),
(12349,36,'Emoční',''),
(5302,41,'End of the World',''),
(62,1,'Eng Only',''),
(16,41,'Engel',''),
(68,14,'Euro',''),
(10,41,'Exalted',''),
(33,41,'FAE',''),
(2181,41,'Fall of Delta Green',''),
(1110,4,'Fantasy',''),
(1,41,'Fate','Tady prosim nechat Fate bez oznaceni, pouzivam v pripadech, kdy neni jasne, kterou verzi Fate vypravec pouzije'),
(32,41,'Fate 4e','; CHYBA - má být \"Fate 4e\"'),
(12350,41,'Feng Shui 2',''),
(41,41,'Fiasco',''),
(299,4,'Forgotten Realms',''),
(1372,26,'Francie','Letošní prostředí LKD.'),
(5283,7,'Gamebook',''),
(2142,41,'GUMSHOE',''),
(17,41,'GURPS',''),
(300,4,'High Fantasy',''),
(351,4,'Historie',''),
(371,3,'Horor',''),
(11102,41,'Hunter: the Vigil',''),
(2416,41,'Chuubo',''),
(104,1,'I pro nováčky',''),
(11764,5,'Improvizační',''),
(83,26,'Itálie','Letošní prostředí LKD.'),
(2633,12,'Jeepforma',''),
(12365,41,'k6 core',''),
(85,26,'Kensington','Pravděpodobně ročníkově specifický štítek prostředí LKD.'),
(1109,47,'Kings of War',''),
(78,3,'Komedie',''),
(36,25,'Končina',''),
(94,26,'Konstantinopol','Pravděpodobně ročníkově specifický štítek prostředí LKD.'),
(67,14,'Kooperativní',''),
(355,36,'Kostýmová',''),
(8074,41,'Labyrinth Lord',''),
(1855,26,'Londýn','Pravděpodobně ročníkově specifický štítek prostředí LKD.'),
(1672,4,'Low Fantasy',''),
(31,41,'Mage',''),
(92,26,'Malajsie','Pravděpodobně ročníkově specifický štítek LKD.'),
(2220,41,'Malifaux',''),
(5875,26,'Mexiko','Letošní prostředí LKD.; Letošní prostředí LKD. Psal bych to česky.'),
(2308,41,'Microscope',''),
(12352,49,'Mindok',''),
(21,41,'Mouse Guard',''),
(11785,5,'Muzikál','Nový, ale dává smysl.'),
(2652,41,'My Little Pony',''),
(2432,3,'Mysteriózní',''),
(12353,3,'Mystická','Go to Gate 6'),
(93,26,'Nanking','Pravděpodobně ročníkově specifický štítek prostředí LKD.'),
(12354,26,'Německo','Letošní prostředí LKD'),
(2614,3,'New Weird',''),
(2141,41,'Night\'s Black Agents',''),
(1464,3,'Noir',''),
(1859,26,'Norsko','Pravděpodobně ročníkově specifický štítek prostředí LKD.'),
(34,41,'Numenéra',''),
(223,5,'Odlehčená',''),
(1054,37,'Odpočinková',''),
(52,41,'Og',''),
(3768,33,'Oldschool',''),
(1853,4,'Pán prstenů',''),
(230,41,'Paranoia XP',''),
(75,14,'Párty hra',''),
(46,41,'Pathfinder',''),
(15,41,'Pendragon',''),
(8241,41,'Penny for My Thoughts',''),
(1569,3,'Piráti',''),
(10865,1,'Playtest',''),
(7989,41,'Polaris',''),
(1468,3,'Post-apo',''),
(96,41,'Powered by the Apocalypse',''),
(1863,26,'Praha','Letošní prostředí LKD.'),
(25,41,'Primetime Adventures',''),
(69,1,'Pro pokročilé',''),
(1046,37,'Přemýšlecí',''),
(9,41,'Příběhy Impéria',''),
(8242,5,'Psychologická',''),
(2599,3,'Pulp',''),
(40,41,'Renaissance Deluxe',''),
(6049,41,'Risus',''),
(4454,3,'Romance',''),
(5717,26,'Rovníková Afrika','Pravděpodobně ročníkově specifický štítek prostředí LKD.'),
(12355,5,'Rozhodovací','larp Speciální Jednotka'),
(3659,2,'RPG','Hodí se pro přednášky a momentálně i wargaming.'),
(1132,47,'Saga',''),
(231,3,'Sci-fi',''),
(1469,3,'Science-fantasy',''),
(63,14,'Semi-kooperativní',''),
(101,41,'Shadow of the Demon Lord',''),
(29,41,'Shadowrun 2e',''),
(6,41,'Shadows',''),
(58,41,'Shadows of Esteren',''),
(1488,41,'Schwarze Auge 5e',''),
(4947,26,'Sibiř','Pravděpodobně ročníkově specifický štítek LKD'),
(82,26,'Singapur','Pravděpodobně ročníkově specifický štítek LKD'),
(315,12,'Skriptovaná',''),
(86,26,'Sny','Pravděpodobně ročníkově specifický štítek prostředí LKD.'),
(11765,36,'Sociální drama',''),
(8,41,'Solar',''),
(89,4,'Současnost','; Pravděpodobně.'),
(1463,3,'Space opera',''),
(2126,4,'Star Wars',''),
(98,41,'Star Wars Saga Edition',''),
(2221,3,'Steampunk',''),
(64,38,'Strategická',''),
(4,41,'Střepy snů',''),
(2785,3,'Superhrdinové','Neschválený, ale dává smysl.'),
(10994,41,'Symbaroum',''),
(2651,41,'Tails of Equestria',''),
(12317,13,'Taneční','Nový, ale dává smysl.'),
(2368,25,'Taria',''),
(5807,26,'Tasmánie','Pravděpodobně ročníkově specifický štítek LKD'),
(1377,26,'Taškent','Pravděpodobně ročníkově specifický štítek prostředí LKD.'),
(12356,4,'Tauril',''),
(1598,41,'Ten Candles',''),
(1852,41,'The One Ring',''),
(226,41,'The Sprawl',''),
(2219,41,'Through the Breach',''),
(5869,26,'Tibet','Pravděpodobně ročníkově specifický štítek prostředí LKD.'),
(7990,3,'Tragédie',''),
(4026,26,'Tropy','Ročníkově specifický štítek prostředí.'),
(73,1,'Turnaj','; Bude lepší použít štítky “Turnaj” a “Párty hra”'),
(1047,1,'Týmová',''),
(12357,26,'Uhry','Letošní prostředí LKD'),
(2143,3,'Urbanfantasy',''),
(274,5,'Vážná',''),
(5074,50,'Věk: 10+',''),
(4120,50,'Věk: 12+',''),
(4055,50,'Věk: 14+',''),
(4067,50,'Věk: 15+','; U věku bych nakonec udělal výjimku a kategorii psal, je to tak srozumitelnější a asi to i vypadá lépe.'),
(12360,50,'Věk: 16+',''),
(4464,50,'Věk: 18+',''),
(12345,50,'Věk: 6+','Není schválený, ale možná bych nechal rozvolněné.'),
(12361,50,'Věk: 7+',''),
(4941,50,'Věk: 8+',''),
(8759,50,'Věk: 9+','Není schválený, ale možná bych nechal rozvolněné.'),
(12362,50,'Věk: do 15','akční hra Turnaj na ostrově příšerek JUNIOR'),
(76,37,'Venkovní',''),
(4455,4,'Vesmír','Ročníkově specifický štítek prostředí. U turnaje v Marsu Teraformci. Jako asi ok.'),
(12358,33,'Vícepostavová',''),
(2586,4,'Viktoriánská doba',''),
(65,5,'Vyjednávací',''),
(71,5,'Vyprávěcí',''),
(77,33,'Vyprávění dle karet',''),
(12359,8,'Výška: 120+','Lasergame, asi dobré využití štítku'),
(276,36,'Vztahová',''),
(70,14,'Wargame',''),
(11855,2,'Wargaming','Přijde mi poněkud zbytečné, aby měl veškerý wargaming štítek wargaming. Maximálně tak, kdyby o něm byla přednáška. Asi smazat.'),
(12363,4,'Warhammer (svět)','Prostředí, jako obdoba Warhammer 40k. Právě jsem doplnil k Chaosu.'),
(1246,41,'Warhammer 40k (RPG)',''),
(54,47,'Warhammer 40k (WG)',''),
(19,41,'Warhammer 40k: Dark Heresy',''),
(56,41,'Warhammer 40k: Rogue Trader',''),
(1129,47,'Warhammer Fantasy Battle','; dlouhý...; Vytvořený omylem, Dreadfleet je deskovka na pomezí WG z daného prostředí.'),
(51,41,'Warhammer Fantasy Roleplay','dlouhý...'),
(35,41,'Warhammer Fantasy Roleplay 2e','WHFR 2e...'),
(28,41,'Warhammer Fantasy Roleplay 3e','WHFR 3e...'),
(1223,47,'Warzone',''),
(4955,4,'Western',''),
(4278,14,'Worker Placement',''),
(690,37,'Workshop',''),
(103,41,'World of Darkness 4e',''),
(11,41,'World of Darkness 5e','; Tohle byl Sirienův loňský v páté edici, tak bych nechal.'),
(12364,41,'Zaklínač RPG',''),
(79,36,'Žánrovka','');
/*!40000 ALTER TABLE `sjednocene_tagy` ENABLE KEYS */;

--
-- Table structure for table `slevy`
--

DROP TABLE IF EXISTS `slevy`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `slevy` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_uzivatele` int(11) NOT NULL,
  `castka` decimal(10,2) NOT NULL,
  `rok` int(11) NOT NULL,
  `provedeno` timestamp NOT NULL DEFAULT current_timestamp(),
  `provedl` int(11) DEFAULT NULL,
  `poznamka` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `FK_slevy_to_uzivatele_hodnoty` (`id_uzivatele`),
  KEY `FK_slevy_provedl_to_uzivatele_hodnoty` (`provedl`),
  CONSTRAINT `FK_slevy_provedl_to_uzivatele_hodnoty` FOREIGN KEY (`provedl`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `FK_slevy_to_uzivatele_hodnoty` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `slevy`
--

/*!40000 ALTER TABLE `slevy` DISABLE KEYS */;
/*!40000 ALTER TABLE `slevy` ENABLE KEYS */;

--
-- Table structure for table `stranky`
--

DROP TABLE IF EXISTS `stranky`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stranky` (
  `id_stranky` int(11) NOT NULL AUTO_INCREMENT,
  `url_stranky` varchar(64) NOT NULL,
  `obsah` longtext NOT NULL COMMENT 'markdown',
  `poradi` tinyint(4) NOT NULL,
  PRIMARY KEY (`id_stranky`),
  UNIQUE KEY `url_stranky` (`url_stranky`)
) ENGINE=InnoDB AUTO_INCREMENT=80 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stranky`
--

/*!40000 ALTER TABLE `stranky` DISABLE KEYS */;
INSERT INTO `stranky` VALUES
(28,'o-prednaskach-na-gc','#Přednášky\r\n\r\nTěšte se na přednášky, workshopy a panelové diskuze se známými i méně známými promotery.\r\n\r\n**Uvedené časy přednášek jsou orientační**. Upřesnění naleznete v anotaci.\r\n\r\n**Přednášky jsou bezplatné.**',0),
(78,'info-po-gc','#Info po GC a zpětná vazba',0),
(79,'o-aktivite-bez-typu','Každá aktivita by měla mít typ - to že má tento je špatně. Toto je pseudo typ existující jen proto, aby se systém nehroutil. Pokud nevíš tak zřejmě hledáš typ \"technická\"',0);
/*!40000 ALTER TABLE `stranky` ENABLE KEYS */;

--
-- Table structure for table `systemove_nastaveni`
--

DROP TABLE IF EXISTS `systemove_nastaveni`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `systemove_nastaveni` (
  `id_nastaveni` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `klic` varchar(128) NOT NULL,
  `hodnota` varchar(255) NOT NULL DEFAULT '',
  `vlastni` tinyint(1) DEFAULT 0,
  `datovy_typ` varchar(24) NOT NULL DEFAULT 'string',
  `nazev` varchar(255) NOT NULL,
  `popis` varchar(1028) NOT NULL DEFAULT '',
  `zmena_kdy` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `skupina` varchar(128) DEFAULT NULL,
  `poradi` int(10) unsigned DEFAULT NULL,
  `pouze_pro_cteni` tinyint(1) DEFAULT 0,
  `rocnik_nastaveni` int(11) NOT NULL DEFAULT -1,
  PRIMARY KEY (`klic`,`rocnik_nastaveni`),
  UNIQUE KEY `id_nastaveni` (`id_nastaveni`),
  UNIQUE KEY `nazev` (`nazev`,`rocnik_nastaveni`),
  KEY `skupina` (`skupina`)
) ENGINE=InnoDB AUTO_INCREMENT=50 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `systemove_nastaveni`
--

/*!40000 ALTER TABLE `systemove_nastaveni` DISABLE KEYS */;
INSERT INTO `systemove_nastaveni` VALUES
(18,'AKTIVITA_EDITOVATELNA_X_MINUT_PRED_JEJIM_ZACATKEM','20',1,'integer','Kolik minut před začátkem lze už aktivitu editovat','Kolik minut před začátkem aktivity už může vypravěč editovat přihlášené','2023-06-21 19:58:03','Aktivita',NULL,0,-1),
(21,'AUTOMATICKY_UZAMKNOUT_AKTIVITU_X_MINUT_PO_ZACATKU','45',1,'integer','Po kolika minutách se aktivita sama zamkne','Po jaké době běžící aktivitu uzamkne automat, pokud to někdo neudělá ručně - může to být se zpožděním, automat se pouští jen jednou za hodinu','2023-06-21 19:58:03','Aktivita',NULL,0,-1),
(4,'BONUS_ZA_STANDARDNI_3H_AZ_5H_AKTIVITU','280',1,'integer','Bonus za vedení 3-5h aktivity','Kolik dostane vypravěč standardní aktivity, která trvala tři až pět hodin','2023-06-21 19:58:03','Finance',4,0,-1),
(33,'DRUHA_VLNA_KDY','',0,'datetime','Začátek druhé vlny aktivit','Kdy se podruhé hromadně změní aktivity Připravené k aktivaci na Aktivované','2023-06-21 19:58:03','Časy',21,0,-1),
(10,'GC_BEZI_DO','',0,'datetime','Konec Gameconu','Datum a čas, kdy končí Gamecon','2023-06-21 19:58:03','Časy',6,0,-1),
(9,'GC_BEZI_OD','',0,'datetime','Začátek Gameconu','Datum a čas, kdy začíná Gamecon','2023-06-21 19:58:03','Časy',5,0,-1),
(26,'JIDLO_LZE_OBJEDNAT_A_MENIT_DO_DNE','',0,'date','Ukončení prodeje jídla na konci dne','Datum, do kdy ještě (včetně) lze v přihlášce měnit jídlo, než se zamkne','2023-06-21 19:58:03','Časy',14,0,-1),
(46,'KOLIK_MINUT_JE_ODHLASENI_AKTIVITY_BEZ_POKUTY','5',1,'integer','Kolik minut je odhlášení aktivity bez pokuty','Když se účastník přihlásí na aktivitu a do několika minut se zase odhlásí, tak mu nebudeme počítat storno ani pár hodin před jejím začátkem','2023-06-21 19:58:06','Aktivita',24,0,-1),
(1,'KURZ_EURO','24',1,'number','Kurz Eura','Kolik kč je pro nás letos jedno €','2023-06-21 19:58:03','Finance',1,0,-1),
(16,'NEPLATIC_CASTKA_POSLAL_DOST','1000',1,'number','Už dost velká částka proti odhlášení','Kolik kč musí letos účastník poslat, abychom ho nezařadili do neplatičů','2023-06-21 19:58:03','Neplatič',NULL,0,-1),
(15,'NEPLATIC_CASTKA_VELKY_DLUH','200',1,'number','Ještě příliš velký dluh neplatiče','Kolik kč je pro nás stále tak velký dluh, že mu hrozí odhlášení jako neplatiči','2023-06-21 19:58:03','Neplatič',NULL,0,-1),
(17,'NEPLATIC_POCET_DNU_PRED_VLNOU_KDY_JE_CHRANEN','7',1,'integer','Počet dní od registrace před hromadným odhlašováním kdy je chráněn','Kolik nejvýše dní od registrace do odhlašovací vlny neplatičů je nový účastník ještě chráněn, aby nebyl brán jako neplatič','2023-06-21 19:58:03','Neplatič',NULL,0,-1),
(45,'POSILAT_MAIL_O_ODHLASENI_A_UVOLNENEM_UBYTOVANI','0',1,'boolean','Poslat nám e-mail o uvolněném ubytování','Když se účastník odhlásí z GC a měl objednané ubytování, tak nám o tom přijde email na info@gamecon.cz','2023-06-21 19:58:04','Notifikace',23,0,-1),
(27,'PREDMETY_BEZ_TRICEK_LZE_OBJEDNAT_A_MENIT_DO_DNE','',0,'date','Ukončení prodeje předmětů (vyjma oblečení) na konci dne','Datum, do kdy ještě (včetně) lze v přihlášce měnit předměty, než se zamknou','2023-06-21 19:58:03','Časy',15,0,-1),
(20,'PRIHLASENI_NA_POSLEDNI_CHVILI_X_MINUT_PRED_ZACATKEM_AKTIVITY','10',1,'integer','Kolik minut před začátkem aktivity je \"na poslední chvíli\"','Nejvíce před kolika minutami před začátkem aktivity se účastník přihlásí, aby Moje aktivity ukázaly varování, že je nejspíš na cestě a ať na něj počkají','2023-06-21 19:58:03','Aktivita',NULL,0,-1),
(35,'PRUMERNE_LONSKE_VSTUPNE','0.0',1,'number','Průměrné loňské vstupné','Abychom mohli zobrazit kostku na posuvníku dobrovolného vstupného','2023-06-21 19:58:04','Finance',22,1,2016),
(36,'PRUMERNE_LONSKE_VSTUPNE','0.0',1,'number','Průměrné loňské vstupné','Abychom mohli zobrazit kostku na posuvníku dobrovolného vstupného','2023-06-21 19:58:04','Finance',22,1,2017),
(37,'PRUMERNE_LONSKE_VSTUPNE','0.0',1,'number','Průměrné loňské vstupné','Abychom mohli zobrazit kostku na posuvníku dobrovolného vstupného','2023-06-21 19:58:04','Finance',22,1,2018),
(38,'PRUMERNE_LONSKE_VSTUPNE','0.0',1,'number','Průměrné loňské vstupné','Abychom mohli zobrazit kostku na posuvníku dobrovolného vstupného','2023-06-21 19:58:04','Finance',22,1,2019),
(39,'PRUMERNE_LONSKE_VSTUPNE','0.0',1,'number','Průměrné loňské vstupné','Abychom mohli zobrazit kostku na posuvníku dobrovolného vstupného','2023-06-21 19:58:04','Finance',22,1,2020),
(40,'PRUMERNE_LONSKE_VSTUPNE','0.0',1,'number','Průměrné loňské vstupné','Abychom mohli zobrazit kostku na posuvníku dobrovolného vstupného','2023-06-21 19:58:04','Finance',22,1,2021),
(41,'PRUMERNE_LONSKE_VSTUPNE','0.0',1,'number','Průměrné loňské vstupné','Abychom mohli zobrazit kostku na posuvníku dobrovolného vstupného','2023-06-21 19:58:04','Finance',22,1,2022),
(47,'PRUMERNE_LONSKE_VSTUPNE','0',1,'number','Průměrné loňské vstupné','Abychom mohli zobrazit kostku na posuvníku dobrovolného vstupného<hr><i>výchozí hodnota</i>: <i>&gt;&gt;&gt;není&lt;&lt;&lt;</i>','2023-06-21 19:58:04','Finance',22,1,2023),
(49,'PRUMERNE_LONSKE_VSTUPNE','0',1,'number','Průměrné loňské vstupné','Abychom mohli zobrazit kostku na posuvníku dobrovolného vstupného<hr><i>výchozí hodnota</i>: <i>&gt;&gt;&gt;není&lt;&lt;&lt;</i><hr><i>výchozí hodnota</i>: <i>&gt;&gt;&gt;není&lt;&lt;&lt;</i>','2023-06-21 19:58:04','Finance',22,1,2024),
(12,'PRVNI_VLNA_KDY','',1,'datetime','Začátek první vlny aktivit','Kdy se poprvé hromadně změní aktivity Připravené k aktivaci na Aktivované','2023-06-21 19:58:03','Časy',20,0,-1),
(29,'REG_GC_DO','',0,'datetime','Ukončení registrací přes web','Do kdy se lze registrovat na Gamecon přes přihlášlu na webu','2023-06-21 19:58:03','Časy',17,0,-1),
(11,'REG_GC_OD','',0,'datetime','Začátek registrací účastníků','Od kdy se mohou začít účastníci registrovat na Gamecon','2023-06-21 19:58:03','Časy',7,0,-1),
(31,'ROCNIK','2024',1,'integer','Ročník','Který ročník GC je aktivní','2025-01-11 11:39:41','Časy',19,1,-1),
(48,'SLEVA_ORGU_NA_JIDLO_CASTKA','25',1,'integer','Jakou slevu mají mít orgové na jídlo','Jakou slevu na jídlo mají dostat všichni s rolí \"Jídlo se slevou\"','2025-01-11 11:39:42','Finance',25,0,-1),
(24,'TEXT_PRO_SPAROVANI_ODCHOZI_PLATBY','Vrácení zůstatku účastníka ID',1,'text','Text pro rozpoznání odchozí GC platby','Přesné znění textu v \"Poznámka\", za kterým následuje ID účastníka GC (jemuž odesíláme z banky peníze) abychom podle tohoto textu spárovali odchozí platbu (poradí si s různou velikostí písmen i chybějící diakritikou)','2025-01-11 11:39:42','Finance',12,0,-1),
(34,'TRETI_VLNA_KDY','',0,'datetime','Začátek třetí vlny aktivit','Kdy se potřetí hromadně změní aktivity Připravené k aktivaci na Aktivované','2023-06-21 19:58:03','Časy',22,0,-1),
(28,'TRICKA_LZE_OBJEDNAT_A_MENIT_DO_DNE','',0,'date','Ukončení prodeje potištěných triček a tílek na konci dne','Datum, do kdy ještě (včetně) lze v přihlášce měnit trička a tílka, než se zamknou','2023-06-21 19:58:03','Časy',16,0,-1),
(25,'UBYTOVANI_LZE_OBJEDNAT_A_MENIT_DO_DNE','2022-07-10',1,'date','Ukončení prodeje bytování na konci dne','Datum, do kdy ještě (včetně) lze v přihlášce měnit ubytování, než se zamkne','2023-06-21 19:58:03','Časy',13,0,-1),
(30,'UCASTNIKY_LZE_PRIDAVAT_X_DNI_PO_GC_U_NEUZAVRENE_PREZENCE','30',1,'integer','Do kolika dní po GC lze přidat účastníka','Kolik dní po konci GC lze ještě přidávat účastníky na Neuzavřenou aktivitu','2023-06-21 19:58:03','Časy',18,0,-1),
(19,'UCASTNIKY_LZE_PRIDAVAT_X_MINUT_PO_KONCI_AKTIVITY','60',1,'integer','Kolik minut po konci aktivity lze potvrzovat účastníky','Kolik minut může ještě vypravěč zpětně přidávat účastníky a potvrzovat jejich účast od okamžiku jejího skončení. Neplatí pro odebírání účastníků.','2023-06-21 19:58:03','Aktivita',NULL,0,-1),
(22,'UPOZORNIT_NA_NEUZAMKNUTOU_AKTIVITU_X_MINUT_PO_KONCI','60',1,'integer','Kdy vypravěče upozorníme že nezavřel','Po jaké době od konce aktivity odešleme vypravěčům mail, že aktivitu neuzavřeli - může to být se zpožděním, automat se pouští jen jednou za hodinu','2023-06-21 19:58:03','Aktivita',NULL,0,-1);
/*!40000 ALTER TABLE `systemove_nastaveni` ENABLE KEYS */;

--
-- Table structure for table `systemove_nastaveni_log`
--

DROP TABLE IF EXISTS `systemove_nastaveni_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `systemove_nastaveni_log` (
  `id_nastaveni_log` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_uzivatele` int(11) DEFAULT NULL,
  `id_nastaveni` bigint(20) unsigned NOT NULL,
  `hodnota` varchar(256) DEFAULT NULL,
  `vlastni` tinyint(1) DEFAULT NULL,
  `kdy` timestamp NOT NULL DEFAULT current_timestamp(),
  UNIQUE KEY `id_nastaveni_log` (`id_nastaveni_log`),
  KEY `FK_systemove_nastaveni_log_to_systemove_nastaveni` (`id_nastaveni`),
  KEY `FK_systemove_nastaveni_log_to_uzivatele_hodnoty` (`id_uzivatele`),
  CONSTRAINT `FK_systemove_nastaveni_log_to_systemove_nastaveni` FOREIGN KEY (`id_nastaveni`) REFERENCES `systemove_nastaveni` (`id_nastaveni`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `FK_systemove_nastaveni_log_to_uzivatele_hodnoty` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `systemove_nastaveni_log`
--

/*!40000 ALTER TABLE `systemove_nastaveni_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `systemove_nastaveni_log` ENABLE KEYS */;

--
-- Table structure for table `texty`
--

DROP TABLE IF EXISTS `texty`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `texty` (
  `id` int(11) NOT NULL COMMENT 'hash',
  `text` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `texty`
--

/*!40000 ALTER TABLE `texty` DISABLE KEYS */;
INSERT INTO `texty` VALUES
(0,'');
/*!40000 ALTER TABLE `texty` ENABLE KEYS */;

--
-- Table structure for table `ubytovani`
--

DROP TABLE IF EXISTS `ubytovani`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ubytovani` (
  `id_uzivatele` int(11) NOT NULL,
  `den` tinyint(4) NOT NULL,
  `pokoj` varchar(255) NOT NULL,
  `rok` smallint(6) NOT NULL,
  PRIMARY KEY (`rok`,`id_uzivatele`,`den`),
  KEY `id_uzivatele` (`id_uzivatele`),
  CONSTRAINT `FK_ubytovani_to_uzivatele_hodnoty` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `ubytovani_ibfk_2` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`),
  CONSTRAINT `ubytovani_ibfk_3` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ubytovani`
--

/*!40000 ALTER TABLE `ubytovani` DISABLE KEYS */;
/*!40000 ALTER TABLE `ubytovani` ENABLE KEYS */;

--
-- Table structure for table `uzivatele_hodnoty`
--

DROP TABLE IF EXISTS `uzivatele_hodnoty`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `uzivatele_hodnoty` (
  `id_uzivatele` int(11) NOT NULL AUTO_INCREMENT,
  `login_uzivatele` varchar(255) NOT NULL,
  `jmeno_uzivatele` varchar(100) NOT NULL,
  `prijmeni_uzivatele` varchar(100) NOT NULL,
  `ulice_a_cp_uzivatele` varchar(255) NOT NULL,
  `mesto_uzivatele` varchar(100) NOT NULL,
  `stat_uzivatele` int(11) NOT NULL,
  `psc_uzivatele` varchar(20) NOT NULL,
  `telefon_uzivatele` varchar(100) NOT NULL,
  `datum_narozeni` date NOT NULL,
  `heslo_md5` varchar(255) CHARACTER SET ucs2 COLLATE ucs2_czech_ci NOT NULL COMMENT 'přechází se na password_hash',
  `funkce_uzivatele` tinyint(4) NOT NULL,
  `email1_uzivatele` varchar(255) NOT NULL,
  `email2_uzivatele` varchar(255) NOT NULL,
  `jine_uzivatele` text NOT NULL,
  `mrtvy_mail` tinyint(4) NOT NULL,
  `forum_razeni` varchar(1) NOT NULL,
  `random` varchar(20) NOT NULL,
  `zustatek` int(11) NOT NULL COMMENT 'zbytek z minulého roku',
  `pohlavi` enum('m','f') NOT NULL,
  `registrovan` datetime NOT NULL,
  `ubytovan_s` varchar(255) DEFAULT NULL,
  `skola` varchar(255) DEFAULT NULL,
  `pomoc_typ` varchar(64) NOT NULL,
  `pomoc_vice` text NOT NULL,
  `op` varchar(4096) NOT NULL COMMENT 'zašifrované číslo OP',
  `nechce_maily` datetime DEFAULT NULL COMMENT 'kdy se odhlásil z odebírání mail(er)u',
  `poznamka` varchar(4096) NOT NULL,
  `potvrzeni_zakonneho_zastupce` date DEFAULT NULL,
  `infopult_poznamka` varchar(128) NOT NULL DEFAULT '',
  `typ_dokladu_totoznosti` varchar(16) NOT NULL DEFAULT '',
  `statni_obcanstvi` varchar(64) DEFAULT NULL,
  `z_rychloregistrace` tinyint(1) DEFAULT 0,
  `potvrzeni_zakonneho_zastupce_soubor` datetime DEFAULT NULL,
  PRIMARY KEY (`id_uzivatele`),
  UNIQUE KEY `login_uzivatele` (`login_uzivatele`),
  UNIQUE KEY `email1_uzivatele` (`email1_uzivatele`),
  KEY `infopult_poznamka` (`infopult_poznamka`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `uzivatele_hodnoty`
--

/*!40000 ALTER TABLE `uzivatele_hodnoty` DISABLE KEYS */;
INSERT INTO `uzivatele_hodnoty` VALUES
(1,'SYSTEM','SYSTEM','SYSTEM','SYSTEM','SYSTEM',1,'SYSTEM','SYSTEM','2023-01-27','',0,'system@gamecon.cz','system@gamecon.cz','',1,'','2e3012801cdebf6db162',0,'m','2023-01-27 00:00:00',NULL,NULL,'','','','2023-01-27 00:00:00','',NULL,NULL,NULL,'','','ČR',0,NULL);
/*!40000 ALTER TABLE `uzivatele_hodnoty` ENABLE KEYS */;

--
-- Table structure for table `uzivatele_role`
--

DROP TABLE IF EXISTS `uzivatele_role`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `uzivatele_role` (
  `id_uzivatele` int(11) NOT NULL,
  `id_role` int(11) NOT NULL,
  `posazen` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `posadil` int(11) DEFAULT NULL,
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id_uzivatele`,`id_role`),
  UNIQUE KEY `id` (`id`),
  KEY `posadil` (`posadil`),
  KEY `FK_uzivatele_role_role_seznam` (`id_role`),
  CONSTRAINT `FK_uzivatele_role_role_seznam` FOREIGN KEY (`id_role`) REFERENCES `role_seznam` (`id_role`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `FK_uzivatele_role_uzivatele_hodnoty` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `uzivatele_role`
--

/*!40000 ALTER TABLE `uzivatele_role` DISABLE KEYS */;
/*!40000 ALTER TABLE `uzivatele_role` ENABLE KEYS */;

--
-- Table structure for table `uzivatele_role_log`
--

DROP TABLE IF EXISTS `uzivatele_role_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `uzivatele_role_log` (
  `id_uzivatele` int(11) NOT NULL,
  `id_role` int(11) NOT NULL,
  `id_zmenil` int(11) DEFAULT NULL,
  `zmena` varchar(128) NOT NULL,
  `kdy` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  UNIQUE KEY `id` (`id`),
  KEY `FK_r_uzivatele_zidle_log_to_uzivatele_hodnoty` (`id_uzivatele`),
  KEY `FK_r_uzivatele_zidle_log_to_r_zidle_soupis` (`id_role`),
  KEY `FK_r_uzivatele_zidle_log_zmenil_to_uzivatele_hodnoty` (`id_zmenil`),
  CONSTRAINT `FK_uzivatele_role_log_to_role_seznam` FOREIGN KEY (`id_role`) REFERENCES `role_seznam` (`id_role`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `uzivatele_role_log`
--

/*!40000 ALTER TABLE `uzivatele_role_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `uzivatele_role_log` ENABLE KEYS */;

--
-- Table structure for table `uzivatele_role_podle_rocniku`
--

DROP TABLE IF EXISTS `uzivatele_role_podle_rocniku`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `uzivatele_role_podle_rocniku` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_uzivatele` int(11) NOT NULL,
  `id_role` int(11) NOT NULL,
  `od_kdy` datetime NOT NULL,
  `rocnik` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_uzivatele` (`id_uzivatele`,`id_role`,`rocnik`),
  KEY `id_role` (`id_role`),
  KEY `rocnik` (`rocnik`),
  CONSTRAINT `uzivatele_role_podle_rocniku_ibfk_1` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `uzivatele_role_podle_rocniku_ibfk_2` FOREIGN KEY (`id_role`) REFERENCES `role_seznam` (`id_role`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `uzivatele_role_podle_rocniku`
--

/*!40000 ALTER TABLE `uzivatele_role_podle_rocniku` DISABLE KEYS */;
/*!40000 ALTER TABLE `uzivatele_role_podle_rocniku` ENABLE KEYS */;

--
-- Table structure for table `uzivatele_url`
--

DROP TABLE IF EXISTS `uzivatele_url`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `uzivatele_url` (
  `id_url_uzivatele` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_uzivatele` int(11) NOT NULL,
  `url` varchar(255) NOT NULL,
  PRIMARY KEY (`url`),
  UNIQUE KEY `id_url_uzivatele` (`id_url_uzivatele`),
  KEY `FK_uzivatele_url_to_uzivatele_hodnoty` (`id_uzivatele`),
  CONSTRAINT `FK_uzivatele_url_to_uzivatele_hodnoty` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `uzivatele_url`
--

/*!40000 ALTER TABLE `uzivatele_url` DISABLE KEYS */;
/*!40000 ALTER TABLE `uzivatele_url` ENABLE KEYS */;

--
-- Dumping routines for database 'gamecon_test_6782587d36bad4.57234391'
--
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `konec_gc` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=CURRENT_USER FUNCTION `konec_gc`(datum TEXT) RETURNS date
    DETERMINISTIC
BEGIN
    SET datum = IF(LENGTH(datum) = 4, CONCAT(datum, '-01-01'), datum);

-- První den v červenci
    SET @first_day_of_july = DATE(CONCAT(YEAR(datum), '-07-01'));

-- První pondělí v červenci
SET @first_monday_of_july = DATE_ADD(
        @first_day_of_july,
        INTERVAL (IF(
                DAYOFWEEK(@first_day_of_july) <= 2, -- neděle nebo pondělí
                2 - DAYOFWEEK(@first_day_of_july), -- následující pondělí v tomto týdnu
                9 - DAYOFWEEK(@first_day_of_july)) -- pondělí v příštím týdnu
            ) DAY
                            );

-- Začátek třetího celého týdne v červenci
SET @third_week_monday = DATE_ADD(@first_monday_of_july, INTERVAL 14 DAY);

-- Neděle ve třetím celém týdnu = konec GC
RETURN DATE_ADD(@third_week_monday, INTERVAL 6 DAY);
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Final view structure for view `platne_role`
--

/*!50001 DROP VIEW IF EXISTS `platne_role`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb3 */;
/*!50001 SET character_set_results     = utf8mb3 */;
/*!50001 SET collation_connection      = utf8mb3_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=CURRENT_USER SQL SECURITY INVOKER */
/*!50001 VIEW `platne_role` AS select `role_seznam`.`id_role` AS `id_role`,`role_seznam`.`kod_role` AS `kod_role`,`role_seznam`.`nazev_role` AS `nazev_role`,`role_seznam`.`popis_role` AS `popis_role`,`role_seznam`.`rocnik_role` AS `rocnik_role`,`role_seznam`.`typ_role` AS `typ_role`,`role_seznam`.`vyznam_role` AS `vyznam_role`,`role_seznam`.`skryta` AS `skryta`,`role_seznam`.`kategorie_role` AS `kategorie_role` from `role_seznam` where `role_seznam`.`rocnik_role` in ((select `systemove_nastaveni`.`hodnota` from `systemove_nastaveni` where `systemove_nastaveni`.`klic` = 'ROCNIK' limit 1),-1) or `role_seznam`.`typ_role` = 'ucast' */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `platne_role_uzivatelu`
--

/*!50001 DROP VIEW IF EXISTS `platne_role_uzivatelu`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb3 */;
/*!50001 SET character_set_results     = utf8mb3 */;
/*!50001 SET collation_connection      = utf8mb3_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=CURRENT_USER SQL SECURITY INVOKER */
/*!50001 VIEW `platne_role_uzivatelu` AS select `uzivatele_role`.`id_uzivatele` AS `id_uzivatele`,`uzivatele_role`.`id_role` AS `id_role`,`uzivatele_role`.`posazen` AS `posazen`,`uzivatele_role`.`posadil` AS `posadil` from (`uzivatele_role` join `platne_role` on(`uzivatele_role`.`id_role` = `platne_role`.`id_role`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

-- Dump completed on 2025-01-11 12:40:19
