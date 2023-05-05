-- MariaDB dump 10.19  Distrib 10.6.12-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: 127.0.0.1    Database: gamecon_test_6452bf16c3ad54.42221657
-- ------------------------------------------------------
-- Server version	10.3.27-MariaDB-1:10.3.27+maria~focal

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `_vars`
--

DROP TABLE IF EXISTS `_vars`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `_vars` (
  `name` varchar(64) COLLATE utf8_czech_ci NOT NULL,
  `value` varchar(4096) COLLATE utf8_czech_ci DEFAULT NULL,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `_vars`
--

LOCK TABLES `_vars` WRITE;
/*!40000 ALTER TABLE `_vars` DISABLE KEYS */;
/*!40000 ALTER TABLE `_vars` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `akce_import`
--

DROP TABLE IF EXISTS `akce_import`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `akce_import` (
  `id_akce_import` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_uzivatele` int(11) NOT NULL,
  `google_sheet_id` varchar(128) COLLATE utf8_czech_ci NOT NULL,
  `cas` datetime NOT NULL,
  UNIQUE KEY `id_akce_import` (`id_akce_import`),
  KEY `google_sheet_id` (`google_sheet_id`),
  KEY `FK_akce_import_to_uzivatele_hodnoty` (`id_uzivatele`),
  CONSTRAINT `FK_akce_import_to_uzivatele_hodnoty` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON DELETE NO ACTION ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `akce_import`
--

LOCK TABLES `akce_import` WRITE;
/*!40000 ALTER TABLE `akce_import` DISABLE KEYS */;
/*!40000 ALTER TABLE `akce_import` ENABLE KEYS */;
UNLOCK TABLES;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `akce_instance`
--

LOCK TABLES `akce_instance` WRITE;
/*!40000 ALTER TABLE `akce_instance` DISABLE KEYS */;
/*!40000 ALTER TABLE `akce_instance` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `akce_lokace`
--

DROP TABLE IF EXISTS `akce_lokace`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `akce_lokace` (
  `id_lokace` int(11) NOT NULL AUTO_INCREMENT,
  `nazev` varchar(255) COLLATE utf8_czech_ci NOT NULL,
  `dvere` varchar(255) COLLATE utf8_czech_ci NOT NULL,
  `poznamka` text COLLATE utf8_czech_ci NOT NULL,
  `poradi` int(11) NOT NULL,
  `rok` int(11) NOT NULL,
  PRIMARY KEY (`id_lokace`),
  UNIQUE KEY `nazev_rok` (`nazev`,`rok`)
) ENGINE=InnoDB AUTO_INCREMENT=225 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `akce_lokace`
--

LOCK TABLES `akce_lokace` WRITE;
/*!40000 ALTER TABLE `akce_lokace` DISABLE KEYS */;
INSERT INTO `akce_lokace` VALUES (1,'RPG 1 – bunkr','Budova C, suterén, bunkr C','Bunkr, dveře vzadu vpravo, repráky',1,0),(2,'RPG 2 - repráky','Budova C, dveře č. 1','Pokoj 3L, repráky',2,0),(3,'RPG 3 - 2L pokoj','Budova C, dveře č. 38','Pokoj 2L',3,0),(4,'RPG 4 – repráky','Budova C, dveře č. 2','Pokoj 3L, repráky',4,0),(5,'RPG 5 ','Budova C, dveře č. 37','Pokoj 3L',5,0),(6,'RPG 6','Budova C, dveře č. 36','Pokoj 3L',6,0),(7,'RPG 7','Budova C, dveře č. 35','Pokoj 3L',7,0),(8,'RPG 8','Budova C, dveře č. 34','Pokoj 3L',8,0),(9,'RPG 9','Budova C, dveře č. 33','Pokoj 3L',9,0),(10,'RPG 10','Budova C, dveře č. 32','Pokoj 3L',10,0),(11,'RPG 11','Budova C, dveře č. 22','Pokoj 3L',11,0),(12,'RPG 12','Budova C, dveře č. 21','Pokoj 3L',12,0),(13,'RPG 13','Budova C, dveře č. 20 ','Pokoj 3L',13,0),(14,'RPG 14','Budova C, dveře č. 19','Pokoj 3L',14,0),(15,'RPG 15','Budova C, dveře č. 18','Pokoj 3L',15,0),(16,'RPG 16','Budova C, dveře č. 17','Pokoj 3L',16,0),(17,'LKD 1 ','Budova C, dveře č. 100','Pokoj 3L',17,0),(18,'LKD 2','Budova C, dveře č. 135','Pokoj 3L',18,0),(19,'LKD 5','Budova C, dveře č. 133','Pokoj 3L',21,0),(20,'LKD 6','Budova C, dveře č. 132','Pokoj 3L',22,0),(21,'mDrD 1','Waldorf ','Třída Waldorf',25,0),(22,'mDrD 2','Waldorf ','Třída Waldorf',26,0),(23,'mDrD 3','Waldorf ','Třída Waldorf',27,0),(24,'mDrD 4','Waldorf ','Třída Waldorf',28,0),(25,'mDrD 5','Waldorf ','Třída Waldorf',29,0),(26,'mDrD 6','Waldorf ','Třída Waldorf',30,0),(27,'mDrD 7','Waldorf ','Třída Waldorf',31,0),(28,'mDrD 8','Waldorf ','Třída Waldorf',32,0),(29,'mDrD 9','Waldorf ','Třída Waldorf',33,0),(30,'mDrD 10','Waldorf ','Třída Waldorf',34,0),(31,'EPIC 1 - prosklená 0p','Budova C, dveře č. 11','Prosklená klubovna',35,0),(32,'EPIC 2 - pokoj 0p','Budova C, dveře č. 12','Pokoj 3L',36,0),(33,'EPIC 3 - tv místnost 0p','Budova C, dveře č. 13','TV místnost na C',37,0),(34,'EPIC 4 – pokoj 0p','Budova C, dveře č. 15','Pokoj 3L',38,0),(35,'EPIC 5 – pokoj 0p','Budova C, dveře č. 16','Pokoj 3L',39,0),(36,'WarG 1 - C1','Budova C, dveře č. 203','Velká klubovna na C',64,0),(37,'EPIC 7 – pokoj 1p','Budova C, dveře č. 120','Pokoj 3L',41,0),(38,'WarG 2 - C2','Budova C, dveře č. 303','Velká klubovna na C',65,0),(39,'Larp 1 - 1L pokoj 3p.','Budova C, dveře č. 308','Pokoj 1L',45,0),(40,'Larp 2 - dvojpokoj 3p.','Budova C, dveře č. 310+311','Dvojmístnost',46,0),(41,'Přednáškovka - Klub','Budova C, suterén, hudební klub','',71,0),(42,'Larp 3 - bunkr B','Budova C, suterén, bunkr B','Dveře vzadu vlevo',47,0),(43,'Larp 4 - DDM sál','DDM, přízemí, velký sál','',48,0),(44,'Larp 6 - DDM 42, malá','DDM, 1. patro, dveře č. 42','',50,0),(45,'Larp 7 - DDM 36, třída','DDM, 1. patro, dveře č. 36','',51,0),(46,'Larp 8 - DDM, hudebna','DDM, 2. patro, dveře č. 12','',52,0),(47,'Larp 9 - Sborovna','Budova A, dveře č. 18','Sborovna na A',53,0),(48,'Larp 10 - knihovna','Budova B, suterén','Po schodech dolů vpravo, dveře vpravo',54,0),(49,'Larp 11 - W. družina','Waldorf, družina','Samostatná budova',55,0),(50,'Larp 12 - W. zahrada','','Zahrada Waldorf družiny',56,0),(51,'Desk 5','KD, 1. patro vlevo','',61,0),(52,'Bonus 1 - klubovna','Budova C, dveře č. 3','Velká klubovna na C',66,0),(53,'Bonus 2 - bunkr I','Budova C, suterén, bunkr I','Tři propojené kumbály, napravo',67,0),(54,'Záz 6 - Zahrada A','Budova A, zahrada','Nějaké stromy atp., 2022 - blokováno',84,0),(55,'Záz 7 - Zahrada B','Budova B, zahrada','Volnější prostor, blíž bráně, venkovní snídaně',85,0),(56,'Bonus 3 - zahrada C','Budova C, zahrada','Hřiště',68,0),(57,'Bonus 4 - venku na GC','','',69,0),(58,'Bonus 5 - mimo GC','','',70,0),(59,'Desk 6','KD, 1. patro, taneční sál','',62,0),(60,'Desk 7','KD, 1. patro, pódium v sále','',63,0),(61,'Desk 2','KD, 1. patro, prosklený sál','Prosklený sál na konci chodby',58,0),(62,'Desk 3 ','KD, 1. patro, druhá vpravo','',59,0),(63,'LKD 8','Budova C, dveře č. 103','Klubovna',24,0),(64,'Záz 8 - Zahrada KD','Atrium za KD, vchod kolem infopultu','',86,0),(65,'Prog 1 - Kino','','',72,0),(66,'Desk 1','KD, 1. patro, předsálí','',57,0),(67,'Prog 2 - předsálí down','KD, přízemí, předsálí','',73,0),(68,'Prog 3 - Bunkr D+E','Budova C, suterén','Vstup přes bunkr C',74,0),(69,'Desk 4','KD, 1. patro, první vpravo','',60,0),(70,'Prog 4 - rezerva 1','Budova C, dveře č. 130','Pokoj 3L',75,0),(71,'Prog 5 – rezerva 2','Budova C, dveře č. 129','Pokoj 3L',76,0),(72,'Prog 6 - jídelna','Budova C mezipatro pod přízemím vzadu','',77,0),(73,'Prog 7 - mimo GC','','',78,0),(74,'Záz 1 - infopult','KD, přízemí u šaten','',79,0),(75,'Záz 2 - štáb','Budova C, přízemí, dveře 28','',80,0),(76,'Záz 3 - sklad IT','Budova C, dveře č. 30','Pokoj 3L',81,0),(77,'Záz 4 - snídárna','Budova B, dveře č. 27','Snídárna na B',82,0),(78,'Záz 5 - ostatní','','',83,0),(79,'F – KDD FH3','','',107,0),(80,'LKD 4','Budova C, dveře č. 102','Pokoj 3L',20,0),(81,'LKD 3','Budova C, dveře č. 134','Pokoj 3L',19,0),(82,'EPIC 9 – tv mistnost 1p','Budova C, dveře č. 111','TV místnost na C',43,0),(83,'EPIC 8 - prosklená 1p','Budova C, dveře č. 110','Prosklená klubovna',42,0),(84,'EPIC 6 – pokoj 1p','Budova C, dveře č. 121','Pokoj 3L',40,0),(85,'F – KDD FH1','','',105,0),(86,'F - KDD Vstup','','',88,0),(87,'F - KDD DH-vstup','','',89,0),(88,'F - KDD DH-bar','','',90,0),(89,'F - KDD L1','','',91,0),(90,'F - KDD L2','','',92,0),(91,'F - KDD L3','','',93,0),(92,'F - KDD L4','','',94,0),(93,'F - KDD L5','','',95,0),(94,'F - KDD P1','','',96,0),(95,'F - KDD P2','','',97,0),(96,'F - KDD P3','','',98,0),(97,'F - KDD P4','','',99,0),(98,'F - KDD DH-A','','',100,0),(99,'F – KDD SM 1','','',101,0),(100,'F – KDD SM 2','','',102,0),(101,'F – KDD SM 3','','',103,0),(102,'F – KDD SM 4','','',104,0),(103,'EPIC 10 – prosklená 2p','Budova C, dveře č. 210','Prosklená klubovna',44,0),(114,'LKD 7','Budova C, dveře č. 131','Pokoj 3L',23,0),(115,'Larp 5 - DDM knihovna','','',49,0),(117,'F – KDD FH2','','',106,0),(118,'F – KDD F1','','',108,0);
/*!40000 ALTER TABLE `akce_lokace` ENABLE KEYS */;
UNLOCK TABLES;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `akce_organizatori`
--

LOCK TABLES `akce_organizatori` WRITE;
/*!40000 ALTER TABLE `akce_organizatori` DISABLE KEYS */;
/*!40000 ALTER TABLE `akce_organizatori` ENABLE KEYS */;
UNLOCK TABLES;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `akce_prihlaseni`
--

LOCK TABLES `akce_prihlaseni` WRITE;
/*!40000 ALTER TABLE `akce_prihlaseni` DISABLE KEYS */;
/*!40000 ALTER TABLE `akce_prihlaseni` ENABLE KEYS */;
UNLOCK TABLES;

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
  `typ` varchar(64) COLLATE utf8_czech_ci DEFAULT NULL,
  `id_zmenil` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_log`),
  KEY `typ` (`typ`),
  KEY `id_zmenil` (`id_zmenil`),
  KEY `FK_akce_prihlaseni_log_to_akce_seznam` (`id_akce`),
  KEY `FK_akce_prihlaseni_log_to_uzivatele_hodnoty` (`id_uzivatele`),
  CONSTRAINT `FK_akce_prihlaseni_log_to_akce_seznam` FOREIGN KEY (`id_akce`) REFERENCES `akce_seznam` (`id_akce`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `FK_akce_prihlaseni_log_to_uzivatele_hodnoty` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `akce_prihlaseni_log`
--

LOCK TABLES `akce_prihlaseni_log` WRITE;
/*!40000 ALTER TABLE `akce_prihlaseni_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `akce_prihlaseni_log` ENABLE KEYS */;
UNLOCK TABLES;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `akce_prihlaseni_spec`
--

LOCK TABLES `akce_prihlaseni_spec` WRITE;
/*!40000 ALTER TABLE `akce_prihlaseni_spec` DISABLE KEYS */;
/*!40000 ALTER TABLE `akce_prihlaseni_spec` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `akce_prihlaseni_stavy`
--

DROP TABLE IF EXISTS `akce_prihlaseni_stavy`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `akce_prihlaseni_stavy` (
  `id_stavu_prihlaseni` tinyint(4) NOT NULL,
  `nazev` varchar(255) COLLATE utf8_czech_ci NOT NULL,
  `platba_procent` float NOT NULL DEFAULT 100,
  PRIMARY KEY (`id_stavu_prihlaseni`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `akce_prihlaseni_stavy`
--

LOCK TABLES `akce_prihlaseni_stavy` WRITE;
/*!40000 ALTER TABLE `akce_prihlaseni_stavy` DISABLE KEYS */;
INSERT INTO `akce_prihlaseni_stavy` VALUES (0,'přihlášen',100),(1,'dorazil',100),(2,'dorazil (náhradník)',100),(3,'nedorazil',100),(4,'pozdě zrušil',50),(5,'náhradník (watchlist)',0);
/*!40000 ALTER TABLE `akce_prihlaseni_stavy` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `akce_seznam`
--

DROP TABLE IF EXISTS `akce_seznam`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `akce_seznam` (
  `id_akce` int(11) NOT NULL AUTO_INCREMENT,
  `patri_pod` int(11) DEFAULT NULL,
  `nazev_akce` varchar(255) COLLATE utf8_czech_ci NOT NULL,
  `url_akce` varchar(64) COLLATE utf8_czech_ci DEFAULT NULL,
  `zacatek` datetime DEFAULT NULL,
  `konec` datetime DEFAULT NULL,
  `lokace` int(11) DEFAULT NULL,
  `kapacita` int(11) NOT NULL,
  `kapacita_f` int(11) NOT NULL,
  `kapacita_m` int(11) NOT NULL,
  `cena` int(11) NOT NULL,
  `bez_slevy` tinyint(1) NOT NULL COMMENT 'na aktivitu se neuplatňují slevy',
  `nedava_slevu` tinyint(1) NOT NULL COMMENT 'aktivita negeneruje organizátorovi slevu',
  `typ` int(11) NOT NULL,
  `dite` varchar(64) COLLATE utf8_czech_ci DEFAULT NULL COMMENT 'potomci oddělení čárkou',
  `rok` int(11) NOT NULL,
  `stav` int(11) NOT NULL DEFAULT 1,
  `teamova` tinyint(1) NOT NULL,
  `team_min` int(11) DEFAULT NULL COMMENT 'minimální velikost teamu',
  `team_max` int(11) DEFAULT NULL COMMENT 'maximální velikost teamu',
  `team_kapacita` int(11) DEFAULT NULL COMMENT 'max. počet týmů, pokud jde o další kolo týmové aktivity',
  `team_nazev` varchar(255) COLLATE utf8_czech_ci DEFAULT NULL,
  `zamcel` int(11) DEFAULT NULL COMMENT 'případně kdo zamčel aktivitu pro svůj team',
  `zamcel_cas` datetime DEFAULT NULL COMMENT 'případně kdy zamčel aktivitu',
  `popis` int(11) NOT NULL,
  `popis_kratky` varchar(255) COLLATE utf8_czech_ci NOT NULL,
  `vybaveni` text COLLATE utf8_czech_ci NOT NULL,
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `akce_seznam`
--

LOCK TABLES `akce_seznam` WRITE;
/*!40000 ALTER TABLE `akce_seznam` DISABLE KEYS */;
/*!40000 ALTER TABLE `akce_seznam` ENABLE KEYS */;
UNLOCK TABLES;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `akce_sjednocene_tagy`
--

LOCK TABLES `akce_sjednocene_tagy` WRITE;
/*!40000 ALTER TABLE `akce_sjednocene_tagy` DISABLE KEYS */;
/*!40000 ALTER TABLE `akce_sjednocene_tagy` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `akce_stav`
--

DROP TABLE IF EXISTS `akce_stav`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `akce_stav` (
  `id_stav` int(11) NOT NULL AUTO_INCREMENT,
  `nazev` varchar(128) COLLATE utf8_czech_ci NOT NULL,
  PRIMARY KEY (`nazev`),
  UNIQUE KEY `id_stav` (`id_stav`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `akce_stav`
--

LOCK TABLES `akce_stav` WRITE;
/*!40000 ALTER TABLE `akce_stav` DISABLE KEYS */;
INSERT INTO `akce_stav` VALUES (1,'nová'),(2,'aktivovaná'),(3,'uzavřená'),(4,'systémová'),(5,'publikovaná'),(6,'připravená'),(7,'zamčená');
/*!40000 ALTER TABLE `akce_stav` ENABLE KEYS */;
UNLOCK TABLES;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `akce_stavy_log`
--

LOCK TABLES `akce_stavy_log` WRITE;
/*!40000 ALTER TABLE `akce_stavy_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `akce_stavy_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `akce_typy`
--

DROP TABLE IF EXISTS `akce_typy`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `akce_typy` (
  `id_typu` int(11) NOT NULL,
  `typ_1p` varchar(32) COLLATE utf8_czech_ci NOT NULL,
  `typ_1pmn` varchar(32) COLLATE utf8_czech_ci NOT NULL,
  `url_typu_mn` varchar(32) COLLATE utf8_czech_ci NOT NULL,
  `stranka_o` int(11) NOT NULL COMMENT 'id stranky "O rpg na GC" apod.',
  `poradi` int(11) NOT NULL,
  `mail_neucast` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'poslat mail účastníkovi, pokud nedorazí',
  `popis_kratky` varchar(255) COLLATE utf8_czech_ci NOT NULL,
  `aktivni` tinyint(1) DEFAULT 1,
  `zobrazit_v_menu` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id_typu`),
  KEY `FK_akce_typy_to_stranka_o` (`stranka_o`),
  CONSTRAINT `FK_akce_typy_to_stranka_o` FOREIGN KEY (`stranka_o`) REFERENCES `stranky` (`id_stranky`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `akce_typy`
--

LOCK TABLES `akce_typy` WRITE;
/*!40000 ALTER TABLE `akce_typy` DISABLE KEYS */;
INSERT INTO `akce_typy` VALUES (0,'(bez typu – organizační)','(bez typu – organizační)','organizacni',79,-1,0,'',1,0),(3,'Přednáška','Přednášky','prednasky',28,10,0,'',1,1),(102,'brigádnická','brigádnické','brigadnicke',79,-3,0,'Placená výpomoc Gameconu',1,0);
/*!40000 ALTER TABLE `akce_typy` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `google_api_user_tokens`
--

DROP TABLE IF EXISTS `google_api_user_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `google_api_user_tokens` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `google_client_id` varchar(128) COLLATE utf8_czech_ci NOT NULL,
  `tokens` text COLLATE utf8_czech_ci NOT NULL,
  PRIMARY KEY (`user_id`,`google_client_id`),
  UNIQUE KEY `id` (`id`),
  CONSTRAINT `FK_google_api_user_tokens_to_uzivatele_hodnoty` FOREIGN KEY (`user_id`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `google_api_user_tokens`
--

LOCK TABLES `google_api_user_tokens` WRITE;
/*!40000 ALTER TABLE `google_api_user_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `google_api_user_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `google_drive_dirs`
--

DROP TABLE IF EXISTS `google_drive_dirs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `google_drive_dirs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `dir_id` varchar(128) COLLATE utf8_czech_ci NOT NULL,
  `original_name` varchar(64) COLLATE utf8_czech_ci NOT NULL,
  `tag` varchar(128) COLLATE utf8_czech_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`dir_id`),
  UNIQUE KEY `id` (`id`),
  UNIQUE KEY `user_and_name` (`user_id`,`original_name`),
  KEY `tag` (`tag`),
  CONSTRAINT `FK_google_drive_dirs_to_uzivatele_hodnoty` FOREIGN KEY (`user_id`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `google_drive_dirs`
--

LOCK TABLES `google_drive_dirs` WRITE;
/*!40000 ALTER TABLE `google_drive_dirs` DISABLE KEYS */;
/*!40000 ALTER TABLE `google_drive_dirs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kategorie_sjednocenych_tagu`
--

DROP TABLE IF EXISTS `kategorie_sjednocenych_tagu`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kategorie_sjednocenych_tagu` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nazev` varchar(128) COLLATE utf8_czech_ci NOT NULL,
  `id_hlavni_kategorie` int(10) unsigned DEFAULT NULL,
  `poradi` int(10) unsigned NOT NULL,
  PRIMARY KEY (`nazev`),
  UNIQUE KEY `id` (`id`),
  KEY `FK_kategorie_sjednocenych_tagu_to_kategorie_sjednocenych_tagu` (`id_hlavni_kategorie`),
  CONSTRAINT `FK_kategorie_sjednocenych_tagu_to_kategorie_sjednocenych_tagu` FOREIGN KEY (`id_hlavni_kategorie`) REFERENCES `kategorie_sjednocenych_tagu` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kategorie_sjednocenych_tagu`
--

LOCK TABLES `kategorie_sjednocenych_tagu` WRITE;
/*!40000 ALTER TABLE `kategorie_sjednocenych_tagu` DISABLE KEYS */;
INSERT INTO `kategorie_sjednocenych_tagu` VALUES (8,'omezení',NULL,90),(50,'omezení Věk',8,95),(1,'Primary',NULL,1),(4,'prostředí',NULL,30),(29,'prostředí Akční',4,35),(30,'prostředí Deskovky',4,36),(28,'prostředí Larp',4,34),(26,'prostředí LKD',4,32),(27,'prostředí mDrD',4,33),(32,'prostředí Přednášky',4,38),(25,'prostředí RPG',4,31),(31,'prostředí WG',4,37),(7,'různé',NULL,80),(49,'různé Partner',7,81),(5,'styl',NULL,40),(37,'styl Akční',5,45),(38,'styl Deskovky',5,46),(36,'styl Larp',5,44),(34,'styl LKD',5,42),(35,'styl mDrD',5,43),(40,'styl Přednášky',5,48),(33,'styl RPG',5,41),(39,'styl WG',5,47),(6,'systém',NULL,50),(45,'systém Akční',6,55),(46,'systém Deskovky',6,56),(44,'systém Larp',6,54),(42,'systém LKD',6,52),(43,'systém mDrD',6,53),(48,'systém Přednášky',6,58),(41,'systém RPG',6,51),(47,'systém WG',6,57),(2,'typ',NULL,10),(13,'typ Akční',2,15),(14,'typ Deskovky',2,16),(12,'typ Larp',2,14),(10,'typ LKD',2,12),(11,'typ mDrD',2,13),(16,'typ Přednášky',2,18),(9,'typ RPG',2,11),(15,'typ WG',2,17),(3,'žánr',NULL,20),(21,'žánr Akční',3,25),(22,'žánr Deskovky',3,26),(20,'žánr Larp',3,24),(18,'žánr LKD',3,22),(19,'žánr mDrD',3,23),(24,'žánr Přednášky',3,28),(17,'žánr RPG',3,21),(23,'žánr WG',3,27);
/*!40000 ALTER TABLE `kategorie_sjednocenych_tagu` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `log_udalosti`
--

DROP TABLE IF EXISTS `log_udalosti`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `log_udalosti` (
  `id_udalosti` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_logujiciho` int(11) NOT NULL,
  `zprava` varchar(255) COLLATE utf8_czech_ci DEFAULT NULL,
  `metadata` varchar(255) COLLATE utf8_czech_ci DEFAULT NULL,
  `rok` int(10) unsigned NOT NULL,
  UNIQUE KEY `id_udalosti` (`id_udalosti`),
  KEY `metadata` (`metadata`),
  KEY `FK_log_udalosti_to_uzivatele_hodnoty` (`id_logujiciho`),
  CONSTRAINT `FK_log_udalosti_to_uzivatele_hodnoty` FOREIGN KEY (`id_logujiciho`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `log_udalosti`
--

LOCK TABLES `log_udalosti` WRITE;
/*!40000 ALTER TABLE `log_udalosti` DISABLE KEYS */;
/*!40000 ALTER TABLE `log_udalosti` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `medailonky`
--

DROP TABLE IF EXISTS `medailonky`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `medailonky` (
  `id_uzivatele` int(11) NOT NULL,
  `o_sobe` mediumtext COLLATE utf8_czech_ci NOT NULL COMMENT 'markdown',
  `drd` mediumtext COLLATE utf8_czech_ci NOT NULL COMMENT 'markdown -- profil pro DrD',
  PRIMARY KEY (`id_uzivatele`),
  CONSTRAINT `FK_medailonky_to_uzivatele_hodnoty` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `medailonky`
--

LOCK TABLES `medailonky` WRITE;
/*!40000 ALTER TABLE `medailonky` DISABLE KEYS */;
/*!40000 ALTER TABLE `medailonky` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `migration_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `migration_code` varchar(128) COLLATE utf8_czech_ci NOT NULL,
  `applied_at` datetime DEFAULT NULL,
  PRIMARY KEY (`migration_code`),
  UNIQUE KEY `migration_id` (`migration_id`)
) ENGINE=InnoDB AUTO_INCREMENT=180 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'000',NULL),(2,'001',NULL),(3,'002',NULL),(4,'003',NULL),(5,'004',NULL),(6,'005',NULL),(7,'006',NULL),(8,'007',NULL),(9,'008',NULL),(10,'009',NULL),(11,'010',NULL),(12,'011',NULL),(13,'012',NULL),(14,'013',NULL),(15,'014',NULL),(16,'015',NULL),(17,'016',NULL),(18,'017',NULL),(19,'018',NULL),(20,'019',NULL),(21,'020',NULL),(22,'021',NULL),(23,'022',NULL),(24,'023',NULL),(25,'024',NULL),(26,'025',NULL),(27,'026',NULL),(28,'027',NULL),(29,'028',NULL),(30,'029',NULL),(31,'030',NULL),(32,'031',NULL),(33,'032',NULL),(34,'033',NULL),(35,'034',NULL),(36,'035',NULL),(37,'036',NULL),(38,'037',NULL),(39,'038',NULL),(40,'039',NULL),(41,'040',NULL),(42,'041',NULL),(43,'042',NULL),(44,'043',NULL),(45,'044',NULL),(46,'045',NULL),(47,'046',NULL),(48,'047',NULL),(49,'048',NULL),(50,'049',NULL),(51,'050',NULL),(52,'051',NULL),(53,'052',NULL),(54,'053',NULL),(55,'054',NULL),(56,'055',NULL),(57,'056',NULL),(58,'057',NULL),(59,'058',NULL),(60,'059',NULL),(61,'060',NULL),(62,'061',NULL),(63,'062',NULL),(64,'063',NULL),(65,'064',NULL),(66,'065',NULL),(67,'066',NULL),(68,'067',NULL),(69,'068',NULL),(70,'069',NULL),(71,'070',NULL),(72,'071','2023-01-27 11:17:05'),(73,'072',NULL),(76,'2021-06-14_01-drop-old-db_migrations','2023-01-27 11:17:05'),(77,'2021-07-01_01-potvrzeni-proti-covidu','2023-01-27 11:17:05'),(78,'2021-07-01_01-sloupec-posadil-k-pravum','2023-01-27 11:17:06'),(79,'2021-07-13_01-prejmenovat-celkovy-report-na-bfgr-report','2023-01-27 11:17:06'),(80,'2021-07-15-samostatny-skript-pro-stravenky-bianco','2023-01-27 11:17:06'),(81,'2021-07-21-lepsi-popis-pro-admin-zidli','2023-01-27 11:17:06'),(82,'2021-11-20-pridat-unikatni-index-na-fio-id','2023-01-27 11:17:06'),(83,'2021-12-17-prejmenovat-slevu-na-bonus','2023-01-27 11:17:06'),(84,'2022-02-23-povolit-mazani-z-tabulky-instanci','2023-01-27 11:17:06'),(85,'2022-04-28-stavy-v-logu-prihlaseni','2023-01-27 11:17:06'),(86,'2022-04-29-pridat-serial-id-do-logu-prihlaseni','2023-01-27 11:17:07'),(87,'2022-05-04-pravo-nastaveni','2023-01-27 11:17:07'),(88,'2022-05-05_01-tabulka-systemove-nastaveni','2023-01-27 11:17:07'),(89,'2022-05-05_02-tabulka-systemove-nastaveni-log','2023-01-27 11:17:07'),(90,'2022-05-05_03-kurz-euro-do-systemoveho-nastaveni','2023-01-27 11:17:07'),(91,'2022-05-05_04-utf8-pro-tabulky-systemoveho-nastaveni','2023-01-27 11:17:07'),(92,'2022-05-09_01-poradi-a-skupina-do-systemoveho-nastaveni','2023-01-27 11:17:07'),(93,'2022-05-09_02-bonusy-vypravecu-do-systemoveho-nastaveni','2023-01-27 11:17:07'),(94,'2022-05-10_01-shop-predmety','2023-01-27 11:17:07'),(96,'2022-05-11_01-shop-upravy','2023-01-27 11:17:07'),(97,'2022-05-11_02-prepinani-rucni-a-automaticke-hodnoty-systemoveho-nastaveni','2023-01-27 11:17:08'),(98,'2022-05-11_03-zacatek-a-konec-gameconu-do-systemoveho-nastaveni','2023-01-27 11:17:08'),(95,'2022-05-11-jen-zakladni-bonus-vypravecu-v-systemovem-nastaveni','2023-01-27 11:17:07'),(100,'2022-05-12_02-dalsi-casy-do-systemoveho-nastaveni','2023-01-27 11:17:08'),(101,'2022-05-12_03-lidstejsi-nazvy-skupin-systemoveho-nastaveni','2023-01-27 11:17:08'),(99,'2022-05-12-zvednout-cenu-kostek-na-25','2023-01-27 11:17:08'),(102,'2022-05-13-prejmenovat-sloupec-s-timestamp-v-logu-prihlaseni','2023-01-27 11:17:08'),(103,'2022-05-14_01-shop-3XL-tricka','2023-01-27 11:17:08'),(104,'2022-05-15_1-mistnosti-2022','2023-01-27 11:17:08'),(105,'2022-05-17_00-url-uzivatele','2023-01-27 11:17:08'),(107,'2022-05-17_1-mistnosti-oprava-2022','2023-01-27 11:17:08'),(106,'2022-05-17_1-Warhammer_40k_turnaj_1_kolo-otevren','2023-01-27 11:17:08'),(108,'2022-05-18_01-import-mistnosti-2022-v3','2023-01-27 11:17:08'),(110,'2022-05-20_01-report-infopult-ucastnici-balicky','2023-01-27 11:17:08'),(111,'2022-05-20_01-zmena-poradi-menu-aktivit','2023-01-27 11:17:08'),(112,'2022-05-20_02-infopult-poznamka-k-uzivateli','2023-01-27 11:17:08'),(109,'2022-05-20-zvednout-cenu-obedu-a-veceri-na-120','2023-01-27 11:17:08'),(113,'2022-05-22_01-zidle-neodhlasovat','2023-01-27 11:17:08'),(114,'2022-05-22_02-neplatic-do-systemoveho-nastaveni','2023-01-27 11:17:08'),(115,'2022-05-24_01-skryt-typ-aktivity-workshop','2023-01-27 11:17:08'),(116,'2022-05-24_01-systemovejsi-skryvani-typu-aktivity-v-menu','2023-01-27 11:17:08'),(117,'2022-05-26_01-reporty-v-xlsx','2023-01-27 11:17:08'),(118,'2022-05-26_1-logovani-zmen-zidli','2023-01-27 11:17:08'),(119,'2022-05-28_1-doplneni-logu-zmen-zidli','2023-01-27 11:17:08'),(120,'2022-05-29_01-cas-posledni-zmeny-platby','2023-01-27 11:17:08'),(121,'2022-05-29_01-zidle-herman','2023-01-27 11:17:08'),(122,'2022-06-08_1-Oldest_Old_World-Tournament-1-kolo','2023-01-27 11:17:08'),(123,'2022-06-11_01-aktivita_editovatelna_x_sekund_po_zavreni','2023-01-27 11:17:08'),(124,'2022-06-11_02-aktivita_stavy_log','2023-01-27 11:17:08'),(125,'2022-06-14-upravit-cizi-klic-aby-slo-smazat-aktivitu','2023-01-27 11:17:08'),(126,'2022-06-16_01-automaticky_zamknout_aktivitu_po','2023-01-27 11:17:08'),(127,'2022-06-16_01-upozornit_na_neuzamknutou_aktivitu_poi','2023-01-27 11:17:08'),(128,'2022-06-22_01-shop-dalsi-xxl-tricka','2023-01-27 11:17:08'),(129,'2022-06-23_01-upozornit_na_neuzamknutou_aktivitu_jen_pokud_ma_par_vypravecu','2023-01-27 11:17:08'),(130,'2022-06-25_01-prejmenovat-skript-s-exportem-ubytovani','2023-01-27 11:17:08'),(131,'2022-06-29_01-report-neplaticu','2023-01-27 11:17:08'),(132,'2022-07-01-obchod_mrizka','2023-01-27 11:17:08'),(133,'2022-07-02-prejmenovat-sekci-v-nastaveni','2023-01-27 11:17:08'),(134,'2022-07-05-smazat-nepouzite-nastaveni-o-poctu-vypravecu','2023-01-27 11:17:08'),(135,'2022-07-07_01-texy-clanku-do-utf8mb4','2023-01-27 11:17:09'),(136,'2022-07-08_01-virtualni-uzivatel-system','2023-01-27 11:17:09'),(137,'2022-07-09_01-uzavrit-nabidku-ubytovani-2022-11-7','2023-01-27 11:17:09'),(138,'2022-07-10_01-text-na-sparovani-odchozich-plateb','2023-01-27 11:17:09'),(139,'2022-07-11_01-datum-pro-ukonceni-prodeje-a-zmen-ubytovani','2023-01-27 11:17:09'),(140,'2022-07-11_01-datum-pro-ukonceni-prodeje-a-zmen-vseho-mozneho','2023-01-27 11:17:09'),(141,'2022-07-11_01-datum-pro-ukonceni-registrace-na-gamecon','2023-01-27 11:17:09'),(142,'2022-07-12_01-posunout-cas-prodeje-jidla','2023-01-27 11:17:09'),(143,'2022-07-14_01-prejmenovat-report-neplaticu','2023-01-27 11:17:09'),(144,'2022-07-14_01-zmenit-editovatelnost-aktivity-na-pridavatelnost','2023-01-27 11:17:09'),(145,'2022-07-21_01-logovat-kdo-zmenil-stav-aktivity','2023-01-27 11:17:09'),(146,'2022-07-22_01-log-udalosti','2023-01-27 11:17:09'),(147,'2022-07-22_01-organizacni-typ-aktivity','2023-01-27 11:17:09'),(148,'2022-07-28_01-nastaveni-do-kdy-lze-pridavat-ucastniky','2023-01-27 11:17:09'),(149,'2022-07-28_02-prejmenovat-stavy-aktivity-v-databazi','2023-01-27 11:17:09'),(150,'2022-08-03_01-pridat-zidli-brigadnik','2023-01-27 11:17:09'),(151,'2022-08-03_02-pridat-typ-aktivity-brigadnicka','2023-01-27 11:17:09'),(152,'2022-11-14_01-sloucit-rozdeleny-report-starych-ucasti','2023-01-27 11:17:09'),(153,'2022-11-30_01-foreign-keys-kaskady','2023-01-27 11:17:13'),(154,'2022-12-05_01-vymenit-nulu-jako-id-stavu-aktivity','2023-01-27 11:17:13'),(155,'2023-01-28_01-rocnik-platnosti-zidle','2023-05-03 22:08:04'),(156,'2023-01-29_01-cizi-klice-pro-zidle','2023-05-03 22:08:05'),(157,'2023-01-30_01-kody-zidli','2023-05-03 22:08:07'),(158,'2023-01-31_01-sql-pohledy-s-platnymi-rolemi','2023-05-03 22:08:07'),(159,'2023-02-01_01-idcka-roli-na-novy-format','2023-05-03 22:08:07'),(160,'2023-02-01_02-typy-roli','2023-05-03 22:08:08'),(161,'2023-02-02_01-vyznam-roli','2023-05-03 22:08:08'),(162,'2023-02-06_01-sjednotit-nazvy-konstant-s-hromadnym-odhlasovanim','2023-05-03 22:08:08'),(163,'2023-02-06_02-cas-tretiho-hromadneho-odhlasovani','2023-05-03 22:08:08'),(164,'2023-02-10_01-zidle-na-role','2023-05-03 22:08:12'),(165,'2023-02-13_01-nrahradit-zidli-za-roli-v-textu','2023-05-03 22:08:12'),(166,'2023-02-13_02-admin-na-prezencni-admin','2023-05-03 22:08:12'),(167,'2023-02-15_01-odstranit-rok-z-kodu-trvalych-roli','2023-05-03 22:08:12'),(168,'2023-02-15_02-skryt-vypravecskou-skupinu','2023-05-03 22:08:12'),(169,'2023-02-16_01-prejmenovat-role-s-bonusy','2023-05-03 22:08:12'),(170,'2023-02-16_02-aktualizovat-nazvy-prav','2023-05-03 22:08:12'),(171,'2023-02-16_03-dalsi-prava-na-noci-zdarma','2023-05-03 22:08:12'),(172,'2023-02-16_04-rozdelit-admin-finance-na-penize','2023-05-03 22:08:12'),(173,'2023-02-16_05-clen-rady-sef-infopultu','2023-05-03 22:08:12'),(174,'2023-02-17_01-pouze-clen-rady-ma-pravo-menit-prava','2023-05-03 22:08:12'),(175,'2023-02-23_01-clenove-rady-2023','2023-05-03 22:08:12'),(176,'2023-02-23_02-kategorie-role','2023-05-03 22:08:12'),(177,'2023-02-25_01-prejmenovat-cfo','2023-05-03 22:08:12'),(178,'2023-03-24_01-kategorie-predmetu','2023-05-03 22:08:13'),(179,'2023-04-06_01-import-mistnosti-2023','2023-05-03 22:08:13');
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `mutex`
--

DROP TABLE IF EXISTS `mutex`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mutex` (
  `id_mutex` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `akce` varchar(128) COLLATE utf8_czech_ci NOT NULL,
  `klic` varchar(128) COLLATE utf8_czech_ci NOT NULL,
  `zamknul` int(11) DEFAULT NULL,
  `od` datetime NOT NULL,
  `do` datetime DEFAULT NULL,
  PRIMARY KEY (`akce`),
  UNIQUE KEY `id_mutex` (`id_mutex`),
  UNIQUE KEY `klic` (`klic`),
  KEY `FK_mutex_to_uzivatele_hodnoty` (`zamknul`),
  CONSTRAINT `FK_mutex_to_uzivatele_hodnoty` FOREIGN KEY (`zamknul`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mutex`
--

LOCK TABLES `mutex` WRITE;
/*!40000 ALTER TABLE `mutex` DISABLE KEYS */;
/*!40000 ALTER TABLE `mutex` ENABLE KEYS */;
UNLOCK TABLES;

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
  `url` varchar(100) COLLATE utf8_czech_ci NOT NULL,
  `nazev` varchar(200) COLLATE utf8_czech_ci NOT NULL,
  `autor` varchar(100) COLLATE utf8_czech_ci DEFAULT NULL,
  `text` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `url` (`url`),
  KEY `FK_novinky_to_texty` (`text`),
  CONSTRAINT `FK_novinky_to_texty` FOREIGN KEY (`text`) REFERENCES `texty` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `novinky`
--

LOCK TABLES `novinky` WRITE;
/*!40000 ALTER TABLE `novinky` DISABLE KEYS */;
/*!40000 ALTER TABLE `novinky` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `obchod_bunky`
--

DROP TABLE IF EXISTS `obchod_bunky`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `obchod_bunky` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `typ` tinyint(4) NOT NULL COMMENT '0-předmět, 1-stránka, 2-zpět, 3-shrnutí',
  `text` varchar(255) COLLATE utf8_czech_ci DEFAULT NULL,
  `barva` varchar(255) COLLATE utf8_czech_ci DEFAULT NULL,
  `cil_id` int(11) DEFAULT NULL COMMENT 'Id cílove mřížky nebo předmětu.',
  `mrizka_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `FK_obchod_bunky_to_obchod_mrizky` (`mrizka_id`),
  CONSTRAINT `FK_obchod_bunky_to_obchod_mrizky` FOREIGN KEY (`mrizka_id`) REFERENCES `obchod_mrizky` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `obchod_bunky`
--

LOCK TABLES `obchod_bunky` WRITE;
/*!40000 ALTER TABLE `obchod_bunky` DISABLE KEYS */;
/*!40000 ALTER TABLE `obchod_bunky` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `obchod_mrizky`
--

DROP TABLE IF EXISTS `obchod_mrizky`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `obchod_mrizky` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `text` varchar(255) COLLATE utf8_czech_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `obchod_mrizky`
--

LOCK TABLES `obchod_mrizky` WRITE;
/*!40000 ALTER TABLE `obchod_mrizky` DISABLE KEYS */;
/*!40000 ALTER TABLE `obchod_mrizky` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `platby`
--

DROP TABLE IF EXISTS `platby`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `platby` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'kvůli indexu a vícenásobným platbám',
  `id_uzivatele` int(11) NOT NULL,
  `fio_id` bigint(20) DEFAULT NULL,
  `castka` decimal(6,2) NOT NULL,
  `rok` smallint(6) NOT NULL,
  `provedeno` timestamp NOT NULL DEFAULT current_timestamp(),
  `provedl` int(11) NOT NULL,
  `poznamka` text COLLATE utf8_czech_ci DEFAULT NULL,
  `pripsano_na_ucet_banky` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fio_id` (`fio_id`),
  KEY `id_uzivatele_rok` (`id_uzivatele`,`rok`),
  KEY `provedl` (`provedl`),
  CONSTRAINT `FK_platby_to_uzivatele_hodnoty` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON UPDATE CASCADE,
  CONSTRAINT `platby_ibfk_2` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`),
  CONSTRAINT `platby_ibfk_3` FOREIGN KEY (`provedl`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `platby`
--

LOCK TABLES `platby` WRITE;
/*!40000 ALTER TABLE `platby` DISABLE KEYS */;
/*!40000 ALTER TABLE `platby` ENABLE KEYS */;
UNLOCK TABLES;

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
  1 AS `vyznam_role` */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `prava_role`
--

LOCK TABLES `prava_role` WRITE;
/*!40000 ALTER TABLE `prava_role` DISABLE KEYS */;
INSERT INTO `prava_role` VALUES (-202300023,1016),(-202300019,1018),(-202300018,1015),(-2202,-2202),(-2201,-2201),(-2102,-2102),(-2101,-2101),(-2002,-2002),(-2001,-2001),(-1902,-1902),(-1901,-1901),(-1802,-1802),(-1801,-1801),(-1702,-1702),(-1701,-1701),(-1602,-1602),(-1601,-1601),(-1502,-1502),(-1501,-1501),(-1402,-1402),(-1401,-1401),(-1302,-1302),(-1301,-1301),(-1202,-1202),(-1201,-1201),(-1102,-1102),(-1101,-1101),(-1002,-1002),(-1001,-1001),(-902,-902),(-901,-901),(20,111),(23,1032),(23,1033);
/*!40000 ALTER TABLE `prava_role` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `r_prava_soupis`
--

DROP TABLE IF EXISTS `r_prava_soupis`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `r_prava_soupis` (
  `id_prava` int(11) NOT NULL AUTO_INCREMENT,
  `jmeno_prava` varchar(255) COLLATE utf8_czech_ci NOT NULL,
  `popis_prava` text COLLATE utf8_czech_ci NOT NULL,
  PRIMARY KEY (`id_prava`)
) ENGINE=InnoDB AUTO_INCREMENT=1034 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `r_prava_soupis`
--

LOCK TABLES `r_prava_soupis` WRITE;
/*!40000 ALTER TABLE `r_prava_soupis` DISABLE KEYS */;
INSERT INTO `r_prava_soupis` VALUES (-2202,'GC2022 přítomen',''),(-2201,'GC2022 přihlášen',''),(-2102,'GC2021 přítomen',''),(-2101,'GC2021 přihlášen',''),(-2002,'GC2020 přítomen',''),(-2001,'GC2020 přihlášen',''),(-1902,'GC2019 přítomen',''),(-1901,'GC2019 přihlášen',''),(-1802,'GC2018 přítomen',''),(-1801,'GC2018 přihlášen',''),(-1702,'GC2017 přítomen',''),(-1701,'GC2017 přihlášen',''),(-1602,'GC2016 přítomen',''),(-1601,'GC2016 přihlášen',''),(-1502,'GC2015 přítomen',''),(-1501,'GC2015 přihlášen',''),(-1402,'GC2014 přítomen',''),(-1401,'GC2014 přihlášen',''),(-1302,'GC2013 přítomen',''),(-1301,'GC2013 přihlášen',''),(-1202,'GC2012 přítomen',''),(-1201,'GC2012 přihlášen',''),(-1102,'GC2011 přítomen',''),(-1101,'GC2011 přihlášen',''),(-1002,'GC2010 přítomen',''),(-1001,'GC2010 přihlášen',''),(-902,'GC2009 přítomen',''),(-901,'GC2009 přihlášen',''),(110,'Administrace - panel Nastavení','Systémové hodnoty pro Gamecon'),(111,'Administrace - panel Peníze','Koutek pro šéfa financí GC'),(1012,'Modré tričko za dosaženou slevu %MODRE_TRICKO_ZDARMA_OD%',''),(1015,'Středeční noc zdarma',''),(1016,'Nerušit automaticky objednávky','Uživateli se při nezaplacení včas nebudou automaticky rušit objednávky'),(1018,'Nedělní noc zdarma',''),(1019,'Sleva na aktivity','Sleva 40% na aktivity'),(1020,'Dvě jakákoli trička zdarma',''),(1021,'Právo na modré tričko','Může si objednávat modrá trička'),(1022,'Právo na červené tričko','Může si objednávat červená trička'),(1023,'Plná sleva na aktivity','Sleva 100% na aktivity'),(1024,'Statistiky - tabulka účasti','V adminu v sekci statistiky v tabulce vlevo nahoře se tato role vypisuje'),(1025,'Report neubytovaných','V reportu Nepřihlášení a neubytovaní vypravěči se lidé na této židli vypisují'),(1026,'Titul „organizátor“','V různých výpisech se označuje jako organizátor'),(1027,'Unikátní židle','Uživatel může mít jen jednu židli s tímto právem'),(1028,'Bez bonusu za vedení aktivit','Nedostává bonus za vedení aktivit ani za účast na technických aktivitách'),(1029,'Čtvrteční noc zdarma',''),(1030,'Páteční noc zdarma',''),(1031,'Sobotní noc zdarma',''),(1032,'Hromadná aktivace aktivit','Může použít \"Aktivovat hromadně\" v aktivitách'),(1033,'Změna práv','Může měnit práva rolím a měnit role uživatelům');
/*!40000 ALTER TABLE `r_prava_soupis` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reporty`
--

DROP TABLE IF EXISTS `reporty`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reporty` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `skript` varchar(100) COLLATE utf8_czech_ci NOT NULL,
  `nazev` varchar(200) COLLATE utf8_czech_ci DEFAULT NULL,
  `format_xlsx` tinyint(1) DEFAULT 1,
  `format_html` tinyint(1) DEFAULT 1,
  `viditelny` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`skript`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reporty`
--

LOCK TABLES `reporty` WRITE;
/*!40000 ALTER TABLE `reporty` DISABLE KEYS */;
INSERT INTO `reporty` VALUES (1,'aktivity','Historie přihlášení na aktivity',1,1,1),(32,'bfgr-report','<span id=\"bfgr\" class=\"hinted\">BFGR (celkový report) {ROK}<span class=\"hint\"><em>Big f**king Gandalf report</em> určený pro Gandalfovu Excelentní magii</span></span>',1,1,1),(9,'duplicity','Duplicitní uživatelé',0,1,1),(18,'finance-aktivity-negenerujici-slevu','Finance: Aktivity negenerující slevu',1,1,1),(17,'finance-lide-v-databazi-a-zustatky','Finance: Lidé v databázi + zůstatky',1,1,1),(19,'finance-prijmy-a-vydaje-infopultaka','Finance: Příjmy a výdaje infopulťáka',1,1,1),(35,'finance-report-neplaticu','Finance: Neplatiči k odhlášení',1,1,1),(33,'finance-report-ubytovani','Ubytování',1,1,0),(6,'grafy-ankety','Grafy k anketě',0,1,1),(15,'maily-dle-data-ucasti','Maily - nedávní účastníci',1,0,1),(13,'maily-neprihlaseni','Maily – nepřihlášení na GC',1,1,1),(12,'maily-prihlaseni','Maily – přihlášení na GC (vč. unsubscribed)',1,1,1),(14,'maily-vypraveci','Maily – vypravěči (vč. unsubscribed)',1,1,1),(8,'neprihlaseni-vypraveci','Nepřihlášení a neubytovaní vypravěči',0,1,1),(5,'parovani-ankety','Párování ankety a údajů uživatelů',0,1,1),(2,'pocty-her','Účastníci a počty jejich aktivit',1,0,1),(3,'pocty-her-graf','Graf rozložení rozmanitosti her',0,1,1),(34,'report-infopult-ucastnici-balicky','Infopult: Balíčky účastníků',1,1,1),(4,'rozesilani-ankety','Rozesílání ankety s tokenem',0,1,1),(10,'stravenky','Stravenky uživatelů',0,1,1),(11,'stravenky-bianco','Stravenky (bianco)',0,1,1),(7,'update-zustatku','UPDATE příkaz zůstatků pro letošní GC',0,1,1),(26,'zazemi-a-program-aktivity-pro-dotaznik-dle-linii','Zázemí & Program: Aktivity pro dotazník dle linií',1,1,1),(28,'zazemi-a-program-casy-a-umisteni-aktivit','Zázemí & Program: Časy a umístění aktivit',1,1,1),(20,'zazemi-a-program-drd-historie-ucasti','Zázemí & Program: DrD: Historie účasti',1,1,1),(21,'zazemi-a-program-drd-seznam-prihlasenych-pro-aktualni-rok','Zázemí & Program: DrD: Seznam přihlášených pro aktuální rok',1,1,1),(25,'zazemi-a-program-emaily-na-ucastniky-dle-linii','Zázemí & Program: Emaily na účastníky dle linií',1,1,1),(24,'zazemi-a-program-emaily-na-vypravece-dle-linii','Zázemí & Program: Emaily na vypravěče dle linií',1,1,1),(23,'zazemi-a-program-pocet-sledujicich-pro-aktualni-rok','Zázemí & Program: Počet sledujících pro aktuální rok',1,1,1),(27,'zazemi-a-program-potvrzeni-pro-navstevniky-mladsi-patnacti-let','Zázemí & Program: Potvrzení pro návštěvníky mladší patnácti let',1,1,1),(29,'zazemi-a-program-prehled-mistnosti','Zázemí & Program: Přehled místností',1,1,1),(30,'zazemi-a-program-seznam-ucastniku-a-tricek','Zázemí & Program: Seznam účastníků a triček',1,1,1),(31,'zazemi-a-program-seznam-ucastniku-a-tricek-grouped','Zázemí & Program: Seznam účastníků a triček (grouped)',1,1,1),(22,'zazemi-a-program-zarizeni-mistnosti','Zázemí & Program: Zařízení místností',1,1,1);
/*!40000 ALTER TABLE `reporty` ENABLE KEYS */;
UNLOCK TABLES;

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
  `format` varchar(10) COLLATE utf8_czech_ci NOT NULL,
  `cas_pouziti` datetime DEFAULT NULL,
  `casova_zona` varchar(100) COLLATE utf8_czech_ci DEFAULT NULL,
  UNIQUE KEY `id` (`id`),
  KEY `report_uzivatel` (`id_reportu`,`id_uzivatele`),
  KEY `FK_reporty_log_pouziti_to_uzivatele_hodnoty` (`id_uzivatele`),
  CONSTRAINT `FK_reporty_log_pouziti_to_reporty` FOREIGN KEY (`id_reportu`) REFERENCES `reporty` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `FK_reporty_log_pouziti_to_uzivatele_hodnoty` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `id_reportu` FOREIGN KEY (`id_reportu`) REFERENCES `reporty` (`id`) ON DELETE NO ACTION ON UPDATE CASCADE,
  CONSTRAINT `id_uzivatele` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON DELETE NO ACTION ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reporty_log_pouziti`
--

LOCK TABLES `reporty_log_pouziti` WRITE;
/*!40000 ALTER TABLE `reporty_log_pouziti` DISABLE KEYS */;
/*!40000 ALTER TABLE `reporty_log_pouziti` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reporty_quick`
--

DROP TABLE IF EXISTS `reporty_quick`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reporty_quick` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nazev` varchar(100) COLLATE utf8_czech_ci NOT NULL,
  `dotaz` text COLLATE utf8_czech_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reporty_quick`
--

LOCK TABLES `reporty_quick` WRITE;
/*!40000 ALTER TABLE `reporty_quick` DISABLE KEYS */;
INSERT INTO `reporty_quick` VALUES (1,'Potvrzení pro návštěvníky mladší patnácti let','SELECT *\nFROM uzivatele_hodnoty\nWHERE (YEAR(\'{gcBeziOd}\') - YEAR(datum_narozeni) -\n       IF(DATE_FORMAT(\'{gcBeziOd}\', \'%m%d\') < DATE_FORMAT(datum_narozeni, \'%m%d\'), 1, 0)) < 15\nORDER BY COALESCE(potvrzeni_zakonneho_zastupce, \'0001-01-01\') ASC,\n         registrovan DESC;');
/*!40000 ALTER TABLE `reporty_quick` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_seznam`
--

DROP TABLE IF EXISTS `role_seznam`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_seznam` (
  `id_role` int(11) NOT NULL AUTO_INCREMENT,
  `kod_role` varchar(36) COLLATE utf8_czech_ci NOT NULL,
  `nazev_role` varchar(255) COLLATE utf8_czech_ci NOT NULL,
  `popis_role` text COLLATE utf8_czech_ci NOT NULL,
  `rocnik_role` int(11) NOT NULL,
  `typ_role` varchar(24) COLLATE utf8_czech_ci NOT NULL,
  `vyznam_role` varchar(48) COLLATE utf8_czech_ci NOT NULL,
  `skryta` tinyint(1) DEFAULT 0,
  `kategorie_role` tinyint(3) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_role`),
  UNIQUE KEY `nazev_role` (`nazev_role`),
  UNIQUE KEY `kod_role` (`kod_role`),
  KEY `typ_zidle` (`typ_role`),
  KEY `vyznam` (`vyznam_role`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_seznam`
--

LOCK TABLES `role_seznam` WRITE;
/*!40000 ALTER TABLE `role_seznam` DISABLE KEYS */;
INSERT INTO `role_seznam` VALUES (-202300028,'GC2023_SOBOTNI_NOC_ZDARMA','Sobotní noc zdarma','Sobotní noc zdarma',2023,'rocnikova','SOBOTNI_NOC_ZDARMA',0,1),(-202300025,'GC2023_BRIGADNIK','Brigádník','Zase práce?',2023,'rocnikova','BRIGADNIK',0,1),(-202300024,'GC2023_HERMAN','Herman','Živoucí návod deskových her sloužící ve jménu Gameconu',-1,'rocnikova','HERMAN',0,1),(-202300023,'GC2023_NEODHLASOVAT','Neodhlašovat','Může zaplatit až na místě. Je chráněn před odhlašováním neplatičů a nezaplacených objednávek.',-1,'rocnikova','NEODHLASOVAT',0,1),(-202300019,'GC2023_NEDELNI_NOC_ZDARMA','Nedělní noc zdarma','',2023,'rocnikova','NEDELNI_NOC_ZDARMA',0,1),(-202300018,'GC2023_STREDECNI_NOC_ZDARMA','Středeční noc zdarma','',2023,'rocnikova','STREDECNI_NOC_ZDARMA',0,1),(-202300017,'GC2023_DOBROVOLNIK_SENIOR','Dobrovolník senior','Dobrovolník senior',2023,'rocnikova','DOBROVOLNIK_SENIOR',0,1),(-202300013,'GC2023_PARTNER','Partner','Partner',2023,'rocnikova','PARTNER',0,1),(-202300008,'GC2023_INFOPULT','Infopult','Infopult',2023,'rocnikova','INFOPULT',0,1),(-202300007,'GC2023_ZAZEMI','Zázemí','Zázemí',2023,'rocnikova','ZAZEMI',0,0),(-202300006,'GC2023_VYPRAVEC','Vypravěč','Vypravěč',2023,'rocnikova','VYPRAVEC',0,1),(-2303,'GC2023_ODJEL','GC2023 odjel','GC2023 odjel',2023,'ucast','ODJEL',0,0),(-2302,'GC2023_PRITOMEN','GC2023 přítomen','GC2023 přítomen',2023,'ucast','PRITOMEN',0,0),(-2301,'GC2023_PRIHLASEN','GC2023 přihlášen','GC2023 přihlášen',2023,'ucast','PRIHLASEN',0,0),(-2203,'GC2022_ODJEL','GC2022 odjel','',2022,'ucast','ODJEL',0,0),(-2202,'GC2022_PRITOMEN','GC2022 přítomen','',2022,'ucast','PRITOMEN',0,0),(-2201,'GC2022_PRIHLASEN','GC2022 přihlášen','',2022,'ucast','PRIHLASEN',0,0),(-2103,'GC2021_ODJEL','GC2021 odjel','',2021,'ucast','ODJEL',0,0),(-2102,'GC2021_PRITOMEN','GC2021 přítomen','',2021,'ucast','PRITOMEN',0,0),(-2101,'GC2021_PRIHLASEN','GC2021 přihlášen','',2021,'ucast','PRIHLASEN',0,0),(-2003,'GC2020_ODJEL','GC2020 odjel','',2020,'ucast','ODJEL',0,0),(-2002,'GC2020_PRITOMEN','GC2020 přítomen','',2020,'ucast','PRITOMEN',0,0),(-2001,'GC2020_PRIHLASEN','GC2020 přihlášen','',2020,'ucast','PRIHLASEN',0,0),(-1903,'GC2019_ODJEL','GC2019 odjel','',2019,'ucast','ODJEL',0,0),(-1902,'GC2019_PRITOMEN','GC2019 přítomen','',2019,'ucast','PRITOMEN',0,0),(-1901,'GC2019_PRIHLASEN','GC2019 přihlášen','',2019,'ucast','PRIHLASEN',0,0),(-1803,'GC2018_ODJEL','GC2018 odjel','',2018,'ucast','ODJEL',0,0),(-1802,'GC2018_PRITOMEN','GC2018 přítomen','',2018,'ucast','PRITOMEN',0,0),(-1801,'GC2018_PRIHLASEN','GC2018 přihlášen','',2018,'ucast','PRIHLASEN',0,0),(-1703,'GC2017_ODJEL','GC2017 odjel','',2017,'ucast','ODJEL',0,0),(-1702,'GC2017_PRITOMEN','GC2017 přítomen','',2017,'ucast','PRITOMEN',0,0),(-1701,'GC2017_PRIHLASEN','GC2017 přihlášen','',2017,'ucast','PRIHLASEN',0,0),(-1603,'GC2016_ODJEL','GC2016 odjel','',2016,'ucast','ODJEL',0,0),(-1602,'GC2016_PRITOMEN','GC2016 přítomen','',2016,'ucast','PRITOMEN',0,0),(-1601,'GC2016_PRIHLASEN','GC2016 přihlášen','',2016,'ucast','PRIHLASEN',0,0),(-1503,'GC2015_ODJEL','GC2015 odjel','',2015,'ucast','ODJEL',0,0),(-1502,'GC2015_PRITOMEN','GC2015 přítomen','',2015,'ucast','PRITOMEN',0,0),(-1501,'GC2015_PRIHLASEN','GC2015 přihlášen','',2015,'ucast','PRIHLASEN',0,0),(-1403,'GC2014_ODJEL','GC2014 odjel','',2014,'ucast','ODJEL',0,0),(-1402,'GC2014_PRITOMEN','GC2014 přítomen','',2014,'ucast','PRITOMEN',0,0),(-1401,'GC2014_PRIHLASEN','GC2014 přihlášen','',2014,'ucast','PRIHLASEN',0,0),(-1302,'GC2013_PRITOMEN','GC2013 přítomen','',2013,'ucast','PRITOMEN',0,0),(-1301,'GC2013_PRIHLASEN','GC2013 přihlášen','',2013,'ucast','PRIHLASEN',0,0),(-1202,'GC2012_PRITOMEN','GC2012 přítomen','',2012,'ucast','PRITOMEN',0,0),(-1201,'GC2012_PRIHLASEN','GC2012 přihlášen','',2012,'ucast','PRIHLASEN',0,0),(-1102,'GC2011_PRITOMEN','GC2011 přítomen','',2011,'ucast','PRITOMEN',0,0),(-1101,'GC2011_PRIHLASEN','GC2011 přihlášen','',2011,'ucast','PRIHLASEN',0,0),(-1002,'GC2010_PRITOMEN','GC2010 přítomen','',2010,'ucast','PRITOMEN',0,0),(-1001,'GC2010_PRIHLASEN','GC2010 přihlášen','',2010,'ucast','PRIHLASEN',0,0),(-902,'GC2009_PRITOMEN','GC2009 přítomen','',2009,'ucast','PRITOMEN',0,0),(-901,'GC2009_PRIHLASEN','GC2009 přihlášen','',2009,'ucast','PRIHLASEN',0,0),(20,'CFO','CFO','Organizátor, který může nakládat s financemi GC',-1,'trvala','CFO',0,0),(21,'PUL_ORG_UBYTKO','Půl-org s ubytkem','Krom jiného ubytování zdarma',-1,'trvala','PUL_ORG_UBYTKO',0,0),(22,'PUL_ORG_TRICKO','Půl-org s tričkem','Krom jiného trička zdarma',-1,'trvala','PUL_ORG_TRICKO',0,0),(23,'CLEN_RADY','Člen rady','Členové rady mají zvláštní zodpovědnost a pravomoce',-1,'trvala','CLEN_RADY',0,0),(24,'SEF_INFOPULTU','Šéf infopultu','S pravomocemi dělat větší zásahy u přhlášených',-1,'trvala','SEF_INFOPULTU',0,0);
/*!40000 ALTER TABLE `role_seznam` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `shop_nakupy`
--

DROP TABLE IF EXISTS `shop_nakupy`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shop_nakupy` (
  `id_uzivatele` int(11) NOT NULL,
  `id_predmetu` int(11) NOT NULL,
  `rok` smallint(6) NOT NULL,
  `cena_nakupni` decimal(6,2) NOT NULL COMMENT 'aktuální cena v okamžiku nákupu (bez slev)',
  `datum` timestamp NOT NULL DEFAULT current_timestamp(),
  KEY `rok_id_uzivatele` (`rok`,`id_uzivatele`),
  KEY `id_predmetu` (`id_predmetu`),
  KEY `id_uzivatele` (`id_uzivatele`),
  CONSTRAINT `FK_shop_nakupy_to_shop_predmety` FOREIGN KEY (`id_predmetu`) REFERENCES `shop_predmety` (`id_predmetu`) ON UPDATE CASCADE,
  CONSTRAINT `FK_shop_nakupy_to_uzivatele_hodnoty` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON UPDATE CASCADE,
  CONSTRAINT `shop_nakupy_ibfk_3` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`),
  CONSTRAINT `shop_nakupy_ibfk_4` FOREIGN KEY (`id_predmetu`) REFERENCES `shop_predmety` (`id_predmetu`),
  CONSTRAINT `shop_nakupy_ibfk_5` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`),
  CONSTRAINT `shop_nakupy_ibfk_6` FOREIGN KEY (`id_predmetu`) REFERENCES `shop_predmety` (`id_predmetu`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `shop_nakupy`
--

LOCK TABLES `shop_nakupy` WRITE;
/*!40000 ALTER TABLE `shop_nakupy` DISABLE KEYS */;
/*!40000 ALTER TABLE `shop_nakupy` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `shop_predmety`
--

DROP TABLE IF EXISTS `shop_predmety`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shop_predmety` (
  `id_predmetu` int(11) NOT NULL AUTO_INCREMENT,
  `nazev` varchar(255) COLLATE utf8_czech_ci NOT NULL,
  `model_rok` smallint(6) NOT NULL,
  `cena_aktualni` decimal(6,2) NOT NULL,
  `stav` tinyint(4) NOT NULL COMMENT '0-mimo, 1-veřejný, 2-podpultový, 3-pozastavený',
  `auto` tinyint(4) NOT NULL COMMENT 'automaticky objednané',
  `nabizet_do` datetime DEFAULT NULL COMMENT 'automatizovaná náhrada za stav 3',
  `kusu_vyrobeno` smallint(6) DEFAULT NULL,
  `typ` tinyint(4) NOT NULL COMMENT '1-předmět, 2-ubytování, 3-tričko, 4-jídlo, 5-vstupné, 6-parcon, 7-vyplaceni',
  `ubytovani_den` tinyint(4) DEFAULT NULL COMMENT 'změněn význam na "obecný atribut den"',
  `popis` varchar(2000) COLLATE utf8_czech_ci NOT NULL,
  `kategorie_predmetu` int(10) unsigned DEFAULT NULL,
  `se_slevou` tinyint(3) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_predmetu`),
  KEY `kategorie_predmetu` (`kategorie_predmetu`)
) ENGINE=InnoDB AUTO_INCREMENT=88 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `shop_predmety`
--

LOCK TABLES `shop_predmety` WRITE;
/*!40000 ALTER TABLE `shop_predmety` DISABLE KEYS */;
INSERT INTO `shop_predmety` VALUES (1,'Dobrovolné vstupné',2015,0.00,2,0,'2015-06-30 23:59:59',NULL,5,NULL,'',NULL,0),(2,'Dobrovolné vstupné (pozdě)',2015,0.00,3,0,NULL,NULL,5,NULL,'',NULL,0),(3,'Parcon',2017,150.00,0,0,NULL,NULL,6,NULL,'',NULL,0),(4,'Proplacení bonusu',2019,0.00,1,0,NULL,NULL,7,NULL,'Pro vyplacení bonusů za vedení aktivit',NULL,0),(5,'Duna kostka',2022,25.00,1,0,'2022-07-13 23:59:00',500,1,NULL,'Duna',2,0),(6,'Placka',2022,25.00,1,0,'2022-07-13 23:59:00',450,1,NULL,'',1,0),(7,'Ponožky (vel. 42-45)',2022,100.00,1,0,'2022-07-13 23:59:00',70,1,NULL,'',7,0),(8,'Ponožky (vel. 38-39)',2022,100.00,1,0,'2022-07-13 23:59:00',100,1,NULL,'',7,0),(9,'Nicknack',2022,60.00,1,0,'2022-07-13 23:59:00',500,1,NULL,'',6,0),(10,'Blok',2022,50.00,1,0,'2022-07-13 23:59:00',100,1,NULL,'',5,0),(11,'Taška',2022,150.00,1,0,'2022-07-13 23:59:00',300,1,NULL,'',NULL,0),(12,'Spacák neděle',2022,100.00,1,0,'2022-07-10 23:59:59',50,2,4,'',NULL,0),(13,'Spacák sobota',2022,100.00,1,0,'2022-07-10 23:59:59',50,2,3,'',NULL,0),(14,'Spacák pátek',2022,100.00,1,0,'2022-07-10 23:59:59',50,2,2,'',NULL,0),(15,'Spacák čtvrtek',2022,100.00,1,0,'2022-07-10 23:59:59',50,2,1,'',NULL,0),(16,'Spacák středa',2022,100.00,1,0,'2022-07-10 23:59:59',50,2,0,'',NULL,0),(17,'Trojlůžák neděle',2022,250.00,1,0,'2022-07-10 23:59:59',200,2,4,'',NULL,0),(18,'Trojlůžák sobota',2022,250.00,1,0,'2022-07-10 23:59:59',200,2,3,'',NULL,0),(19,'Trojlůžák pátek',2022,250.00,1,0,'2022-07-10 23:59:59',200,2,2,'',NULL,0),(20,'Trojlůžák čtvrtek',2022,250.00,1,0,'2022-07-10 23:59:59',200,2,1,'',NULL,0),(21,'Trojlůžák středa',2022,250.00,1,0,'2022-07-10 23:59:59',200,2,0,'',NULL,0),(22,'Dvojlůžák neděle',2022,300.00,1,0,'2022-07-10 23:59:59',245,2,4,'',NULL,0),(23,'Dvojlůžák sobota',2022,300.00,1,0,'2022-07-10 23:59:59',245,2,3,'',NULL,0),(24,'Dvojlůžák pátek',2022,300.00,1,0,'2022-07-10 23:59:59',245,2,2,'',NULL,0),(25,'Dvojlůžák čtvrtek',2022,300.00,1,0,'2022-07-10 23:59:59',245,2,1,'',NULL,0),(26,'Dvojlůžák středa',2022,300.00,1,0,'2022-07-10 23:59:59',245,2,0,'',NULL,0),(27,'Jednolůžák neděle',2022,400.00,1,0,'2022-07-10 23:59:59',11,2,4,'',NULL,0),(28,'Jednolůžák sobota',2022,400.00,1,0,'2022-07-10 23:59:59',11,2,3,'',NULL,0),(29,'Jednolůžák pátek',2022,400.00,1,0,'2022-07-10 23:59:59',11,2,2,'',NULL,0),(30,'Jednolůžák čtvrtek',2022,400.00,1,0,'2022-07-10 23:59:59',11,2,1,'',NULL,0),(31,'Jednolůžák středa',2022,400.00,1,0,'2022-07-10 23:59:59',11,2,0,'',NULL,0),(32,'Tričko modré pánské XXL',2022,200.00,2,0,'2022-06-30 23:59:00',NULL,3,NULL,'',3,1),(33,'Tričko modré pánské XL',2022,200.00,2,0,'2022-06-30 23:59:00',NULL,3,NULL,'',3,1),(34,'Tričko modré pánské S',2022,200.00,2,0,'2022-06-30 23:59:00',NULL,3,NULL,'',3,1),(35,'Tričko modré pánské M',2022,200.00,2,0,'2022-06-30 23:59:00',NULL,3,NULL,'',3,1),(36,'Tričko modré pánské L',2022,200.00,2,0,'2022-06-30 23:59:00',NULL,3,NULL,'',3,1),(37,'Tričko červené pánské XXL',2022,200.00,2,0,'2022-06-30 23:59:00',NULL,3,NULL,'',3,1),(38,'Tričko červené pánské XL',2022,200.00,2,0,'2022-06-30 23:59:00',NULL,3,NULL,'',3,1),(39,'Tričko červené pánské S',2022,200.00,2,0,'2022-06-30 23:59:00',NULL,3,NULL,'',3,1),(40,'Tričko červené pánské M',2022,200.00,2,0,'2022-06-30 23:59:00',NULL,3,NULL,'',3,1),(41,'Tričko červené pánské L',2022,200.00,2,0,'2022-06-30 23:59:00',NULL,3,NULL,'',3,1),(42,'Tričko účastnické pánské XXL',2022,250.00,1,0,'2022-06-30 23:59:00',NULL,3,NULL,'',3,0),(43,'Tričko účastnické pánské XL',2022,250.00,1,0,'2022-06-30 23:59:00',NULL,3,NULL,'',3,0),(44,'Tričko účastnické pánské S',2022,250.00,1,0,'2022-06-30 23:59:00',NULL,3,NULL,'',3,0),(45,'Tričko účastnické pánské M',2022,250.00,1,0,'2022-06-30 23:59:00',NULL,3,NULL,'',3,0),(46,'Tričko účastnické pánské L',2022,250.00,1,0,'2022-06-30 23:59:00',NULL,3,NULL,'',3,0),(47,'Tílko modré dámské S',2022,200.00,2,0,'2022-06-30 23:59:00',NULL,3,NULL,'',4,1),(48,'Tílko modré dámské M',2022,200.00,2,0,'2022-06-30 23:59:00',NULL,3,NULL,'',4,1),(49,'Tílko modré dámské L',2022,200.00,2,0,'2022-06-30 23:59:00',NULL,3,NULL,'',4,1),(50,'Tílko modré dámské XL',2022,200.00,2,0,'2022-06-30 23:59:00',NULL,3,NULL,'',4,1),(51,'Tílko červené dámské S',2022,200.00,2,0,'2022-06-30 23:59:00',NULL,3,NULL,'',4,1),(52,'Tílko červené dámské M',2022,200.00,2,0,'2022-06-30 23:59:00',NULL,3,NULL,'',4,1),(53,'Tílko červené dámské L',2022,200.00,2,0,'2022-06-30 23:59:00',NULL,3,NULL,'',4,1),(54,'Tílko červené dámské XL',2022,200.00,2,0,'2022-06-30 23:59:00',NULL,3,NULL,'',4,1),(55,'Tílko účastnické dámské S',2022,250.00,1,0,'2022-06-30 23:59:00',NULL,3,NULL,'',4,0),(56,'Tílko účastnické dámské M',2022,250.00,1,0,'2022-06-30 23:59:00',NULL,3,NULL,'',4,0),(57,'Tílko účastnické dámské L',2022,250.00,1,0,'2022-06-30 23:59:00',NULL,3,NULL,'',4,0),(58,'Tílko účastnické dámské XL',2022,250.00,1,0,'2022-06-30 23:59:00',NULL,3,NULL,'',4,0),(59,'Večeře neděle',2022,100.00,0,0,'2023-01-27 11:17:08',NULL,4,4,'',NULL,0),(60,'Oběd neděle',2022,100.00,0,0,'2023-01-27 11:17:08',NULL,4,4,'',NULL,0),(61,' Snídaně neděle',2022,45.00,1,0,'2022-07-18 00:00:00',NULL,4,4,'',NULL,0),(62,'Večeře sobota',2022,100.00,0,0,'2023-01-27 11:17:08',NULL,4,3,'',NULL,0),(63,'Oběd sobota',2022,100.00,0,0,'2023-01-27 11:17:08',NULL,4,3,'',NULL,0),(64,' Snídaně sobota',2022,45.00,1,0,'2022-07-18 00:00:00',NULL,4,3,'',NULL,0),(65,'Večeře pátek',2022,100.00,0,0,'2023-01-27 11:17:08',NULL,4,2,'',NULL,0),(66,'Oběd pátek',2022,100.00,0,0,'2023-01-27 11:17:08',NULL,4,2,'',NULL,0),(67,' Snídaně pátek',2022,45.00,1,0,'2022-07-18 00:00:00',NULL,4,2,'',NULL,0),(68,'Večeře čtvrtek',2022,100.00,0,0,'2023-01-27 11:17:08',NULL,4,1,'',NULL,0),(69,'Oběd čtvrtek',2022,100.00,0,0,'2023-01-27 11:17:08',NULL,4,1,'',NULL,0),(70,'Dobrovolné vstupné (pozdě)',2022,0.00,3,0,NULL,NULL,5,NULL,'',NULL,0),(71,'Kostka kruhy',2022,25.00,2,0,'2022-07-13 23:59:00',NULL,5,NULL,'',2,0),(72,'Proplacení bonusu',2022,0.00,1,0,NULL,NULL,7,NULL,'Pro vyplacení bonusů za vedení aktivit',NULL,0),(73,'COVID test',2022,250.00,2,0,'2022-07-11 00:00:00',NULL,1,NULL,'',8,0),(74,'Tričko modré pánské XXXL',2022,200.00,2,0,'2022-06-30 23:59:00',NULL,3,NULL,'',3,1),(75,'Tričko účastnické pánské XXXL',2022,250.00,1,0,'2022-06-30 23:59:00',NULL,3,NULL,'',3,0),(76,'Večeře neděle',2022,120.00,1,0,'2022-07-18 00:00:00',NULL,4,4,'',NULL,0),(77,'Oběd neděle',2022,120.00,1,0,'2022-07-18 00:00:00',NULL,4,4,'',NULL,0),(78,'Večeře sobota',2022,120.00,1,0,'2022-07-18 00:00:00',NULL,4,3,'',NULL,0),(79,'Oběd sobota',2022,120.00,1,0,'2022-07-18 00:00:00',NULL,4,3,'',NULL,0),(80,'Večeře pátek',2022,120.00,1,0,'2022-07-18 00:00:00',NULL,4,2,'',NULL,0),(81,'Oběd pátek',2022,120.00,1,0,'2022-07-18 00:00:00',NULL,4,2,'',NULL,0),(82,'Večeře čtvrtek',2022,120.00,1,0,'2022-07-18 00:00:00',NULL,4,1,'',NULL,0),(83,'Oběd čtvrtek',2022,120.00,1,0,'2022-07-18 00:00:00',NULL,4,1,'',NULL,0),(84,'Tričko červené pánské XXXL',2022,200.00,2,0,'2022-06-30 23:59:00',NULL,3,NULL,'',3,1),(85,'Tílko modré dámské XXL',2022,200.00,2,0,'2022-06-30 23:59:00',NULL,3,NULL,'',4,1),(86,'Tílko červené dámské XXL',2022,200.00,2,0,'2022-06-30 23:59:00',NULL,3,NULL,'',4,1),(87,'Tílko účastnické dámské XXL',2022,250.00,1,0,'2022-06-30 23:59:00',NULL,3,NULL,'',4,0);
/*!40000 ALTER TABLE `shop_predmety` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sjednocene_tagy`
--

DROP TABLE IF EXISTS `sjednocene_tagy`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sjednocene_tagy` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_kategorie_tagu` int(10) unsigned NOT NULL,
  `nazev` varchar(128) COLLATE utf8_czech_ci NOT NULL,
  `poznamka` text COLLATE utf8_czech_ci NOT NULL,
  PRIMARY KEY (`nazev`),
  UNIQUE KEY `id` (`id`),
  KEY `FK_sjednocene_tagy_to_kategorie_sjednocenych_tagu` (`id_kategorie_tagu`),
  CONSTRAINT `FK_sjednocene_tagy_to_kategorie_sjednocenych_tagu` FOREIGN KEY (`id_kategorie_tagu`) REFERENCES `kategorie_sjednocenych_tagu` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12367 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sjednocene_tagy`
--

LOCK TABLES `sjednocene_tagy` WRITE;
/*!40000 ALTER TABLE `sjednocene_tagy` DISABLE KEYS */;
INSERT INTO `sjednocene_tagy` VALUES (45,41,'13th Age',''),(2309,14,'Abstraktní',''),(109,3,'Akční',''),(769,4,'Alternativní historie',''),(4944,26,'Amazonie','Pravděpodobně ročníkově specifický štítek prostředí LKD.'),(66,14,'Ameritrash',''),(99,41,'Apocalypse World',''),(277,36,'Argumentační',''),(8282,4,'Asterion',''),(3665,5,'Atmosférická','; Atmosférická'),(776,41,'Aye Dark Overlord',''),(919,37,'Běhací',''),(232,41,'Bez systému',''),(7991,33,'Bez vypravěče',''),(2615,41,'Blades in the Dark',''),(87,26,'Blázinec','Pravděpodobně ročníkově specifický štítek prostředí LKD.'),(2790,41,'Bliss Stage',''),(2784,41,'Capes RPG',''),(12347,49,'CGE',''),(12348,41,'City of Mist',''),(2182,4,'Cthulhu Mythos',''),(227,3,'Cyberpunk',''),(9246,4,'Časová smyčka',''),(768,41,'Časy se mění',''),(12366,26,'Čechy','Letošní prostředí LKD'),(84,26,'Čína','Pravděpodobně ročníkově specifický štítek LKD'),(8161,41,'Dakara',''),(2648,4,'Dark Fantasy',''),(1837,16,'Debata',''),(97,14,'Deckbuilding',''),(72,1,'Deluxe',''),(10284,38,'Demohraní',''),(9107,49,'Deskofobie',''),(88,3,'Detektivka',''),(12,41,'DnD 4e',''),(50,41,'DnD 5e',''),(9342,41,'DnD 5e klon',''),(2598,3,'Dobrodružná',''),(13,41,'Dogs in the Vineyard',''),(60,41,'Dračí doupě','Kvůli přednáškám.'),(2,41,'Dračí kutloch',''),(49,41,'DrD 1.0',''),(5,41,'DrD 1.6','; Štítek by měl být specifický jako všechny ostatní, přemazal bych nejpravděpodobnější možností.'),(18,41,'DrD II',''),(9333,41,'DrD klon',''),(38,41,'DrD+',''),(7,41,'Dread',''),(9008,41,'Dungeon World',''),(8109,33,'Dungeoncrawl',''),(920,5,'Edukativní',''),(5078,38,'Ekonomická',''),(12349,36,'Emoční',''),(5302,41,'End of the World',''),(62,1,'Eng Only',''),(16,41,'Engel',''),(68,14,'Euro',''),(10,41,'Exalted',''),(33,41,'FAE',''),(2181,41,'Fall of Delta Green',''),(1110,4,'Fantasy',''),(1,41,'Fate','Tady prosim nechat Fate bez oznaceni, pouzivam v pripadech, kdy neni jasne, kterou verzi Fate vypravec pouzije'),(32,41,'Fate 4e','; CHYBA - má být \"Fate 4e\"'),(12350,41,'Feng Shui 2',''),(41,41,'Fiasco',''),(299,4,'Forgotten Realms',''),(1372,26,'Francie','Letošní prostředí LKD.'),(5283,7,'Gamebook',''),(2142,41,'GUMSHOE',''),(17,41,'GURPS',''),(300,4,'High Fantasy',''),(351,4,'Historie',''),(371,3,'Horor',''),(11102,41,'Hunter: the Vigil',''),(2416,41,'Chuubo',''),(104,1,'I pro nováčky',''),(11764,5,'Improvizační',''),(83,26,'Itálie','Letošní prostředí LKD.'),(2633,12,'Jeepforma',''),(12365,41,'k6 core',''),(85,26,'Kensington','Pravděpodobně ročníkově specifický štítek prostředí LKD.'),(1109,47,'Kings of War',''),(78,3,'Komedie',''),(36,25,'Končina',''),(94,26,'Konstantinopol','Pravděpodobně ročníkově specifický štítek prostředí LKD.'),(67,14,'Kooperativní',''),(355,36,'Kostýmová',''),(8074,41,'Labyrinth Lord',''),(1855,26,'Londýn','Pravděpodobně ročníkově specifický štítek prostředí LKD.'),(1672,4,'Low Fantasy',''),(31,41,'Mage',''),(92,26,'Malajsie','Pravděpodobně ročníkově specifický štítek LKD.'),(2220,41,'Malifaux',''),(5875,26,'Mexiko','Letošní prostředí LKD.; Letošní prostředí LKD. Psal bych to česky.'),(2308,41,'Microscope',''),(12352,49,'Mindok',''),(21,41,'Mouse Guard',''),(11785,5,'Muzikál','Nový, ale dává smysl.'),(2652,41,'My Little Pony',''),(2432,3,'Mysteriózní',''),(12353,3,'Mystická','Go to Gate 6'),(93,26,'Nanking','Pravděpodobně ročníkově specifický štítek prostředí LKD.'),(12354,26,'Německo','Letošní prostředí LKD'),(2614,3,'New Weird',''),(2141,41,'Night\'s Black Agents',''),(1464,3,'Noir',''),(1859,26,'Norsko','Pravděpodobně ročníkově specifický štítek prostředí LKD.'),(34,41,'Numenéra',''),(223,5,'Odlehčená',''),(1054,37,'Odpočinková',''),(52,41,'Og',''),(3768,33,'Oldschool',''),(1853,4,'Pán prstenů',''),(230,41,'Paranoia XP',''),(75,14,'Párty hra',''),(46,41,'Pathfinder',''),(15,41,'Pendragon',''),(8241,41,'Penny for My Thoughts',''),(1569,3,'Piráti',''),(10865,1,'Playtest',''),(7989,41,'Polaris',''),(1468,3,'Post-apo',''),(96,41,'Powered by the Apocalypse',''),(1863,26,'Praha','Letošní prostředí LKD.'),(25,41,'Primetime Adventures',''),(69,1,'Pro pokročilé',''),(1046,37,'Přemýšlecí',''),(9,41,'Příběhy Impéria',''),(8242,5,'Psychologická',''),(2599,3,'Pulp',''),(40,41,'Renaissance Deluxe',''),(6049,41,'Risus',''),(4454,3,'Romance',''),(5717,26,'Rovníková Afrika','Pravděpodobně ročníkově specifický štítek prostředí LKD.'),(12355,5,'Rozhodovací','larp Speciální Jednotka'),(3659,2,'RPG','Hodí se pro přednášky a momentálně i wargaming.'),(1132,47,'Saga',''),(231,3,'Sci-fi',''),(1469,3,'Science-fantasy',''),(63,14,'Semi-kooperativní',''),(101,41,'Shadow of the Demon Lord',''),(29,41,'Shadowrun 2e',''),(6,41,'Shadows',''),(58,41,'Shadows of Esteren',''),(1488,41,'Schwarze Auge 5e',''),(4947,26,'Sibiř','Pravděpodobně ročníkově specifický štítek LKD'),(82,26,'Singapur','Pravděpodobně ročníkově specifický štítek LKD'),(315,12,'Skriptovaná',''),(86,26,'Sny','Pravděpodobně ročníkově specifický štítek prostředí LKD.'),(11765,36,'Sociální drama',''),(8,41,'Solar',''),(89,4,'Současnost','; Pravděpodobně.'),(1463,3,'Space opera',''),(2126,4,'Star Wars',''),(98,41,'Star Wars Saga Edition',''),(2221,3,'Steampunk',''),(64,38,'Strategická',''),(4,41,'Střepy snů',''),(2785,3,'Superhrdinové','Neschválený, ale dává smysl.'),(10994,41,'Symbaroum',''),(2651,41,'Tails of Equestria',''),(12317,13,'Taneční','Nový, ale dává smysl.'),(2368,25,'Taria',''),(5807,26,'Tasmánie','Pravděpodobně ročníkově specifický štítek LKD'),(1377,26,'Taškent','Pravděpodobně ročníkově specifický štítek prostředí LKD.'),(12356,4,'Tauril',''),(1598,41,'Ten Candles',''),(1852,41,'The One Ring',''),(226,41,'The Sprawl',''),(2219,41,'Through the Breach',''),(5869,26,'Tibet','Pravděpodobně ročníkově specifický štítek prostředí LKD.'),(7990,3,'Tragédie',''),(4026,26,'Tropy','Ročníkově specifický štítek prostředí.'),(73,1,'Turnaj','; Bude lepší použít štítky “Turnaj” a “Párty hra”'),(1047,1,'Týmová',''),(12357,26,'Uhry','Letošní prostředí LKD'),(2143,3,'Urbanfantasy',''),(274,5,'Vážná',''),(5074,50,'Věk: 10+',''),(4120,50,'Věk: 12+',''),(4055,50,'Věk: 14+',''),(4067,50,'Věk: 15+','; U věku bych nakonec udělal výjimku a kategorii psal, je to tak srozumitelnější a asi to i vypadá lépe.'),(12360,50,'Věk: 16+',''),(4464,50,'Věk: 18+',''),(12345,50,'Věk: 6+','Není schválený, ale možná bych nechal rozvolněné.'),(12361,50,'Věk: 7+',''),(4941,50,'Věk: 8+',''),(8759,50,'Věk: 9+','Není schválený, ale možná bych nechal rozvolněné.'),(12362,50,'Věk: do 15','akční hra Turnaj na ostrově příšerek JUNIOR'),(76,37,'Venkovní',''),(4455,4,'Vesmír','Ročníkově specifický štítek prostředí. U turnaje v Marsu Teraformci. Jako asi ok.'),(12358,33,'Vícepostavová',''),(2586,4,'Viktoriánská doba',''),(65,5,'Vyjednávací',''),(71,5,'Vyprávěcí',''),(77,33,'Vyprávění dle karet',''),(12359,8,'Výška: 120+','Lasergame, asi dobré využití štítku'),(276,36,'Vztahová',''),(70,14,'Wargame',''),(11855,2,'Wargaming','Přijde mi poněkud zbytečné, aby měl veškerý wargaming štítek wargaming. Maximálně tak, kdyby o něm byla přednáška. Asi smazat.'),(12363,4,'Warhammer (svět)','Prostředí, jako obdoba Warhammer 40k. Právě jsem doplnil k Chaosu.'),(1246,41,'Warhammer 40k (RPG)',''),(54,47,'Warhammer 40k (WG)',''),(19,41,'Warhammer 40k: Dark Heresy',''),(56,41,'Warhammer 40k: Rogue Trader',''),(1129,47,'Warhammer Fantasy Battle','; dlouhý...; Vytvořený omylem, Dreadfleet je deskovka na pomezí WG z daného prostředí.'),(51,41,'Warhammer Fantasy Roleplay','dlouhý...'),(35,41,'Warhammer Fantasy Roleplay 2e','WHFR 2e...'),(28,41,'Warhammer Fantasy Roleplay 3e','WHFR 3e...'),(1223,47,'Warzone',''),(4955,4,'Western',''),(4278,14,'Worker Placement',''),(690,37,'Workshop',''),(103,41,'World of Darkness 4e',''),(11,41,'World of Darkness 5e','; Tohle byl Sirienův loňský v páté edici, tak bych nechal.'),(12364,41,'Zaklínač RPG',''),(79,36,'Žánrovka','');
/*!40000 ALTER TABLE `sjednocene_tagy` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `slevy`
--

DROP TABLE IF EXISTS `slevy`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `slevy` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_uzivatele` int(11) NOT NULL,
  `castka` decimal(6,2) NOT NULL,
  `rok` int(11) NOT NULL,
  `provedeno` timestamp NOT NULL DEFAULT current_timestamp(),
  `provedl` int(11) DEFAULT NULL,
  `poznamka` text COLLATE utf8_czech_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `FK_slevy_to_uzivatele_hodnoty` (`id_uzivatele`),
  KEY `FK_slevy_provedl_to_uzivatele_hodnoty` (`provedl`),
  CONSTRAINT `FK_slevy_provedl_to_uzivatele_hodnoty` FOREIGN KEY (`provedl`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `FK_slevy_to_uzivatele_hodnoty` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `slevy`
--

LOCK TABLES `slevy` WRITE;
/*!40000 ALTER TABLE `slevy` DISABLE KEYS */;
/*!40000 ALTER TABLE `slevy` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stranky`
--

DROP TABLE IF EXISTS `stranky`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stranky` (
  `id_stranky` int(11) NOT NULL AUTO_INCREMENT,
  `url_stranky` varchar(64) COLLATE utf8_czech_ci NOT NULL,
  `obsah` longtext COLLATE utf8_czech_ci NOT NULL COMMENT 'markdown',
  `poradi` tinyint(4) NOT NULL,
  PRIMARY KEY (`id_stranky`),
  UNIQUE KEY `url_stranky` (`url_stranky`)
) ENGINE=InnoDB AUTO_INCREMENT=80 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stranky`
--

LOCK TABLES `stranky` WRITE;
/*!40000 ALTER TABLE `stranky` DISABLE KEYS */;
INSERT INTO `stranky` VALUES (28,'o-prednaskach-na-gc','#Přednášky\r\n\r\nTěšte se na přednášky, workshopy a panelové diskuze se známými i méně známými promotery.\r\n\r\n**Uvedené časy přednášek jsou orientační**. Upřesnění naleznete v anotaci.\r\n\r\n**Přednášky jsou bezplatné.**',0),(78,'info-po-gc','#Info po GC a zpětná vazba',0),(79,'o-aktivite-bez-typu','Každá aktivita by měla mít typ - to že má tento je špatně. Toto je pseudo typ existující jen proto, aby se systém nehroutil. Pokud nevíš tak zřejmě hledáš typ \"technická\"',0);
/*!40000 ALTER TABLE `stranky` ENABLE KEYS */;
UNLOCK TABLES;

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
  `aktivni` tinyint(1) DEFAULT 1,
  `datovy_typ` varchar(24) NOT NULL DEFAULT 'string',
  `nazev` varchar(255) NOT NULL,
  `popis` varchar(1028) NOT NULL DEFAULT '',
  `zmena_kdy` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `skupina` varchar(128) DEFAULT NULL,
  `poradi` int(10) unsigned DEFAULT NULL,
  `pouze_pro_cteni` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`klic`),
  UNIQUE KEY `id_nastaveni` (`id_nastaveni`),
  UNIQUE KEY `nazev` (`nazev`),
  KEY `skupina` (`skupina`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `systemove_nastaveni`
--

LOCK TABLES `systemove_nastaveni` WRITE;
/*!40000 ALTER TABLE `systemove_nastaveni` DISABLE KEYS */;
INSERT INTO `systemove_nastaveni` VALUES (18,'AKTIVITA_EDITOVATELNA_X_MINUT_PRED_JEJIM_ZACATKEM','20',1,'integer','Kolik minut před začátkem lze už aktivitu editovat','Kolik minut před začátkem aktivity už může vypravěč editovat přihlášené','2023-01-27 10:17:08','Aktivita',NULL,0),(21,'AUTOMATICKY_UZAMKNOUT_AKTIVITU_X_MINUT_PO_ZACATKU','45',1,'integer','Po kolika minutách se aktivita sama zamkne','Po jaké době běžící aktivitu uzamkne automat, pokud to někdo neudělá ručně - může to být se zpožděním, automat se pouští jen jednou za hodinu','2023-01-27 10:17:08','Aktivita',NULL,0),(4,'BONUS_ZA_STANDARDNI_3H_AZ_5H_AKTIVITU','280',1,'integer','Bonus za vedení 3-5h aktivity','Kolik dostane vypravěč standardní aktivity, která trvala tři až pět hodin','2023-01-27 10:17:08','Finance',4,0),(10,'GC_BEZI_DO','',0,'datetime','Konec Gameconu','Datum a čas, kdy končí Gamecon','2023-01-27 10:17:08','Časy',6,0),(9,'GC_BEZI_OD','',0,'datetime','Začátek Gameconu','Datum a čas, kdy začíná Gamecon','2023-01-27 10:17:08','Časy',5,0),(13,'HROMADNE_ODHLASOVANI_1','',0,'datetime','První hromadné odhlašování','Kdy budou poprvé hromadně odhlášeni neplatiči','2023-05-03 20:08:08','Časy',9,0),(14,'HROMADNE_ODHLASOVANI_2','',0,'datetime','Druhé hromadné odhlašování','Kdy budou podruhé hromadně odhlášeni neplatiči','2023-05-03 20:08:08','Časy',10,0),(32,'HROMADNE_ODHLASOVANI_3','',0,'datetime','Třetí hromadné odhlašování','Kdy budou potřetí hromadně odhlášeni neplatiči','2023-05-03 20:08:08','Časy',11,0),(26,'JIDLO_LZE_OBJEDNAT_A_MENIT_DO_DNE','',0,'date','Ukončení prodeje jídla na konci dne','Datum, do kdy ještě (včetně) lze v přihlášce měnit jídlo, než se zamkne','2023-05-03 20:08:08','Časy',14,0),(1,'KURZ_EURO','24',1,'number','Kurz Eura','Kolik kč je pro nás letos jedno €','2023-01-27 10:17:08','Finance',1,0),(16,'NEPLATIC_CASTKA_POSLAL_DOST','1000',1,'number','Už dost velká částka proti odhlášení','Kolik kč musí letos účastník poslat, abychom ho nezařadili do neplatičů','2023-01-27 10:17:08','Neplatič',NULL,0),(15,'NEPLATIC_CASTKA_VELKY_DLUH','200',1,'number','Ještě příliš velký dluh neplatiče','Kolik kč je pro nás stále tak velký dluh, že mu hrozí odhlášení jako neplatiči','2023-01-27 10:17:08','Neplatič',NULL,0),(17,'NEPLATIC_POCET_DNU_PRED_VLNOU_KDY_JE_CHRANEN','7',1,'integer','Počet dní od registrace před vlnou kdy je chráněn','Kolik nejvýše dní od registrace do odhlašovací vlny neplatičů je nový účastník ještě chráněn, aby nebyl brán jako neplatič','2023-01-27 10:17:08','Neplatič',NULL,0),(27,'PREDMETY_BEZ_TRICEK_LZE_OBJEDNAT_A_MENIT_DO_DNE','',0,'date','Ukončení prodeje předmětů (vyjma oblečení) na konci dne','Datum, do kdy ještě (včetně) lze v přihlášce měnit předměty, než se zamknou','2023-05-03 20:08:08','Časy',15,0),(20,'PRIHLASENI_NA_POSLEDNI_CHVILI_X_MINUT_PRED_ZACATKEM_AKTIVITY','10',1,'integer','Kolik minut před začátkem aktivity je \"na poslední chvíli\"','Nejvíce před kolika minutami před začátkem aktivity se účastník přihlásí, aby Moje aktivity ukázaly varování, že je nejspíš na cestě a ať na něj počkají','2023-01-27 10:17:08','Aktivita',NULL,0),(12,'REG_AKTIVIT_OD','',0,'datetime','Začátek první vlny aktivit','Od kdy se účastníci mohou začít přihlašovat na aktivity','2023-01-27 10:17:08','Časy',8,0),(29,'REG_GC_DO','',0,'datetime','Ukončení registrací přes web','Do kdy se lze registrovat na Gamecon přes přihlášlu na webu','2023-05-03 20:08:08','Časy',17,0),(11,'REG_GC_OD','',0,'datetime','Začátek registrací účastníků','Od kdy se mohou začít účastníci registrovat na Gamecon','2023-01-27 10:17:08','Časy',7,0),(31,'ROCNIK','2023',1,'integer','Ročník','Který ročník GC je aktivní','2023-05-03 20:08:08','Časy',19,1),(24,'TEXT_PRO_SPAROVANI_ODCHOZI_PLATBY','Vrácení zůstatku účastníka ID',1,'text','Text pro rozpoznání odchozí GC platby','Přesné znění \"Zpráva pro příjemce\", za kterém následuje ID účastníka GC, kterému odesíláme z banky peníze, abychom podle něj spárovali odchozí platbu (stačí nalými písmeny a bez diakritiky)','2023-05-03 20:08:08','Finance',12,0),(28,'TRICKA_LZE_OBJEDNAT_A_MENIT_DO_DNE','',0,'date','Ukončení prodeje potištěných triček a tílek na konci dne','Datum, do kdy ještě (včetně) lze v přihlášce měnit trička a tílka, než se zamknou','2023-05-03 20:08:08','Časy',16,0),(25,'UBYTOVANI_LZE_OBJEDNAT_A_MENIT_DO_DNE','2022-07-10',1,'date','Ukončení prodeje bytování na konci dne','Datum, do kdy ještě (včetně) lze v přihlášce měnit ubytování, než se zamkne','2023-05-03 20:08:08','Časy',13,0),(30,'UCASTNIKY_LZE_PRIDAVAT_X_DNI_PO_GC_U_NEUZAVRENE_PREZENCE','30',1,'integer','Do kolika dní po GC lze přidat účastníka','Kolik dní po konci GC lze ještě přidávat účastníky na Neuzavřenou aktivitu','2023-05-03 20:08:08','Časy',18,0),(19,'UCASTNIKY_LZE_PRIDAVAT_X_MINUT_PO_KONCI_AKTIVITY','60',1,'integer','Kolik minut po konci aktivity lze potvrzovat účastníky','Kolik minut může ještě vypravěč zpětně přidávat účastníky a potvrzovat jejich účast od okamžiku jejího skončení. Neplatí pro odebírání účastníků.','2023-01-27 10:17:09','Aktivita',NULL,0),(22,'UPOZORNIT_NA_NEUZAMKNUTOU_AKTIVITU_X_MINUT_PO_KONCI','60',1,'integer','Kdy vypravěče upozorníme že nezavřel','Po jaké době od konce aktivity odešleme vypravěčům mail, že aktivitu neuzavřeli - může to být se zpožděním, automat se pouští jen jednou za hodinu','2023-01-27 10:17:08','Aktivita',NULL,0);
/*!40000 ALTER TABLE `systemove_nastaveni` ENABLE KEYS */;
UNLOCK TABLES;

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
  `aktivni` tinyint(1) DEFAULT NULL,
  `kdy` timestamp NOT NULL DEFAULT current_timestamp(),
  UNIQUE KEY `id_nastaveni_log` (`id_nastaveni_log`),
  KEY `FK_systemove_nastaveni_log_to_systemove_nastaveni` (`id_nastaveni`),
  KEY `FK_systemove_nastaveni_log_to_uzivatele_hodnoty` (`id_uzivatele`),
  CONSTRAINT `FK_systemove_nastaveni_log_to_systemove_nastaveni` FOREIGN KEY (`id_nastaveni`) REFERENCES `systemove_nastaveni` (`id_nastaveni`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `FK_systemove_nastaveni_log_to_uzivatele_hodnoty` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `systemove_nastaveni_log`
--

LOCK TABLES `systemove_nastaveni_log` WRITE;
/*!40000 ALTER TABLE `systemove_nastaveni_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `systemove_nastaveni_log` ENABLE KEYS */;
UNLOCK TABLES;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `texty`
--

LOCK TABLES `texty` WRITE;
/*!40000 ALTER TABLE `texty` DISABLE KEYS */;
INSERT INTO `texty` VALUES (0,'');
/*!40000 ALTER TABLE `texty` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ubytovani`
--

DROP TABLE IF EXISTS `ubytovani`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ubytovani` (
  `id_uzivatele` int(11) NOT NULL,
  `den` tinyint(4) NOT NULL,
  `pokoj` varchar(255) COLLATE utf8_czech_ci NOT NULL,
  `rok` smallint(6) NOT NULL,
  PRIMARY KEY (`rok`,`id_uzivatele`,`den`),
  KEY `id_uzivatele` (`id_uzivatele`),
  CONSTRAINT `FK_ubytovani_to_uzivatele_hodnoty` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `ubytovani_ibfk_2` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`),
  CONSTRAINT `ubytovani_ibfk_3` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ubytovani`
--

LOCK TABLES `ubytovani` WRITE;
/*!40000 ALTER TABLE `ubytovani` DISABLE KEYS */;
/*!40000 ALTER TABLE `ubytovani` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `uzivatele_hodnoty`
--

DROP TABLE IF EXISTS `uzivatele_hodnoty`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  `pomoc_typ` varchar(64) COLLATE utf8_czech_ci NOT NULL,
  `pomoc_vice` text COLLATE utf8_czech_ci NOT NULL,
  `op` varchar(4096) COLLATE utf8_czech_ci NOT NULL COMMENT 'zašifrované číslo OP',
  `nechce_maily` datetime DEFAULT NULL COMMENT 'kdy se odhlásil z odebírání mail(er)u',
  `poznamka` varchar(4096) COLLATE utf8_czech_ci NOT NULL,
  `potvrzeni_zakonneho_zastupce` date DEFAULT NULL,
  `potvrzeni_proti_covid19_pridano_kdy` datetime DEFAULT NULL,
  `potvrzeni_proti_covid19_overeno_kdy` datetime DEFAULT NULL,
  `infopult_poznamka` varchar(128) COLLATE utf8_czech_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id_uzivatele`),
  UNIQUE KEY `login_uzivatele` (`login_uzivatele`),
  UNIQUE KEY `email1_uzivatele` (`email1_uzivatele`),
  KEY `infopult_poznamka` (`infopult_poznamka`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `uzivatele_hodnoty`
--

LOCK TABLES `uzivatele_hodnoty` WRITE;
/*!40000 ALTER TABLE `uzivatele_hodnoty` DISABLE KEYS */;
INSERT INTO `uzivatele_hodnoty` VALUES (1,'SYSTEM','SYSTEM','SYSTEM','SYSTEM','SYSTEM',1,'SYSTEM','SYSTEM','2023-01-27','',0,'system@gamecon.cz','system@gamecon.cz','',1,'','2e3012801cdebf6db162',0,'m','2023-01-27 00:00:00',NULL,NULL,'','','','2023-01-27 00:00:00','',NULL,NULL,NULL,'');
/*!40000 ALTER TABLE `uzivatele_hodnoty` ENABLE KEYS */;
UNLOCK TABLES;

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
  PRIMARY KEY (`id_uzivatele`,`id_role`),
  KEY `posadil` (`posadil`),
  KEY `FK_uzivatele_role_role_seznam` (`id_role`),
  CONSTRAINT `FK_uzivatele_role_role_seznam` FOREIGN KEY (`id_role`) REFERENCES `role_seznam` (`id_role`),
  CONSTRAINT `FK_uzivatele_role_uzivatele_hodnoty` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `uzivatele_role`
--

LOCK TABLES `uzivatele_role` WRITE;
/*!40000 ALTER TABLE `uzivatele_role` DISABLE KEYS */;
/*!40000 ALTER TABLE `uzivatele_role` ENABLE KEYS */;
UNLOCK TABLES;

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
  `zmena` varchar(128) COLLATE utf8_czech_ci NOT NULL,
  `kdy` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  KEY `FK_r_uzivatele_zidle_log_to_uzivatele_hodnoty` (`id_uzivatele`),
  KEY `FK_r_uzivatele_zidle_log_to_r_zidle_soupis` (`id_role`),
  KEY `FK_r_uzivatele_zidle_log_zmenil_to_uzivatele_hodnoty` (`id_zmenil`),
  CONSTRAINT `FK_uzivatele_role_log_to_role_seznam` FOREIGN KEY (`id_role`) REFERENCES `role_seznam` (`id_role`),
  CONSTRAINT `FK_uzivatele_role_log_to_uzivatele_hodnoty` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`),
  CONSTRAINT `FK_uzivatele_role_log_zmenil_to_uzivatele_hodnoty` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `uzivatele_role_log`
--

LOCK TABLES `uzivatele_role_log` WRITE;
/*!40000 ALTER TABLE `uzivatele_role_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `uzivatele_role_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `uzivatele_url`
--

DROP TABLE IF EXISTS `uzivatele_url`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `uzivatele_url` (
  `id_url_uzivatele` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_uzivatele` int(11) NOT NULL,
  `url` varchar(255) COLLATE utf8_czech_ci NOT NULL,
  PRIMARY KEY (`url`),
  UNIQUE KEY `id_url_uzivatele` (`id_url_uzivatele`),
  KEY `FK_uzivatele_url_to_uzivatele_hodnoty` (`id_uzivatele`),
  CONSTRAINT `FK_uzivatele_url_to_uzivatele_hodnoty` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `uzivatele_url`
--

LOCK TABLES `uzivatele_url` WRITE;
/*!40000 ALTER TABLE `uzivatele_url` DISABLE KEYS */;
/*!40000 ALTER TABLE `uzivatele_url` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Final view structure for view `platne_role`
--

/*!50001 DROP VIEW IF EXISTS `platne_role`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8 */;
/*!50001 SET character_set_results     = utf8 */;
/*!50001 SET collation_connection      = utf8_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`%` SQL SECURITY INVOKER */
/*!50001 VIEW `platne_role` AS select `role_seznam`.`id_role` AS `id_role`,`role_seznam`.`kod_role` AS `kod_role`,`role_seznam`.`nazev_role` AS `nazev_role`,`role_seznam`.`popis_role` AS `popis_role`,`role_seznam`.`rocnik_role` AS `rocnik_role`,`role_seznam`.`typ_role` AS `typ_role`,`role_seznam`.`vyznam_role` AS `vyznam_role` from `role_seznam` where `role_seznam`.`rocnik_role` in ((select `systemove_nastaveni`.`hodnota` from `systemove_nastaveni` where `systemove_nastaveni`.`klic` = 'ROCNIK' limit 1),-1) */;
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
/*!50001 SET character_set_client      = utf8 */;
/*!50001 SET character_set_results     = utf8 */;
/*!50001 SET collation_connection      = utf8_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`%` SQL SECURITY INVOKER */
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
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2023-05-03 22:12:58
