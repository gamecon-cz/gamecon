<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
DROP TEMPORARY TABLE IF EXISTS akce_lokace_tmp;

CREATE TEMPORARY TABLE akce_lokace_tmp
LIKE akce_lokace;

INSERT INTO akce_lokace_tmp(id_lokace, nazev, dvere, poznamka, poradi, rok)
VALUES
(1,'RPG 1 - repráky','Budova C, dveře č. 1','Pokoj 3L, repráky',1,0),
(2,'RPG 2 - repráky','Budova C, dveře č. 38','Pokoj 3L, repráky',2,0),
(3,'RPG 3 - 2L pokoj','Budova C, dveře č. 2','Pokoj 2L',3,0),
(4,'RPG 4','Budova C, dveře č. 37','Pokoj 3L',4,0),
(5,'RPG 5','Budova C, dveře č. 36','Pokoj 3L',5,0),
(6,'RPG 6','Budova C, dveře č. 35','Pokoj 3L',6,0),
(7,'RPG 7','Budova C, dveře č. 34','Pokoj 3L',7,0),
(8,'RPG 8','Budova C, dveře č. 33','Pokoj 3L',8,0),
(9,'RPG 9','Budova C, dveře č. 32','Pokoj 3L',9,0),
(10,'RPG 10','Budova C, dveře č. 22','Pokoj 3L',10,0),
(11,'RPG 11','Budova C, dveře č. 21','Pokoj 3L',11,0),
(12,'RPG 12','Budova C, dveře č. 20','Pokoj 3L',12,0),
(13,'RPG 13','Budova C, dveře č. 19','Pokoj 3L',13,0),
(14,'RPG 14','Budova C, dveře č. 18','Pokoj 3L',14,0),
(15,'RPG 15','Budova C, dveře č. 17','Pokoj 3L',15,0),
(16,'RPG 16','Budova C, dveře č. 16','Pokoj 3L',16,0),
(17,'RPG 17','Budova C, dveře č. 125','Pokoj 3L',17,0),
(18,'RPG 18','Budova C, dveře č. 123','Pokoj 3L',18,0),
(81,'RPG 19','Budova C, dveře č. 121','Pokoj 3L',19,0),
(80,'RPG 20 - bunkr C','Budova C, bunkr C','Bunkr, dveře vzadu vpravo, repráky',20,0),
(19,'mDrD 1','Budova C, dveře č. 100','Pokoj 3L',21,0),
(20,'mDrD 2','Budova C, dveře č. 135','Pokoj 3L',22,0),
(21,'mDrD 3','Budova C, dveře č. 134','Pokoj 3L',23,0),
(22,'mDrD 4','Budova C, dveře č. 102','Pokoj 3L',24,0),
(23,'mDrD 5','Budova C, dveře č. 133','Pokoj 3L',25,0),
(24,'mDrD 6','Budova C, dveře č. 132','Pokoj 3L',26,0),
(25,'mDrD 7','Budova C, dveře č. 131','Pokoj 3L',27,0),
(26,'mDrD 8','Budova C, dveře č. 130','Pokoj 3L',28,0),
(27,'mDrD 9','Budova C, dveře č. 129','Pokoj 3L',29,0),
(28,'mDrD 10','Budova C, dveře č. 127','Pokoj 3L',30,0),
(29,'mDrD 11','Budova C, dveře č. 126','Pokoj 3L',31,0),
(30,'mDrD 12 - klubovna','Budova C, dveře č. 103','Velká klubovna na C',32,0),
(31,'EPIC 1 - prosklená 0p','Budova C, dveře č. 11','Prosklená klubovna',33,0),
(32,'EPIC 2 - pokoj 0p','Budova C, dveře č. 12','Pokoj 3L',34,0),
(33,'EPIC 3 - tv místnost 0p','Budova C, dveře č. 13','TV místnost na C',35,0),
(34,'EPIC 4 - pokoj 0p','Budova C, dveře č. 15','Pokoj 3L',36,0),
(35,'EPIC 5 - prosklená 1p','Budova C, dveře č. 110','Prosklená klubovna',37,0),
(36,'EPIC 6 - tv místnost 1p','Budova C, dveře č. 111','TV místnost na C',38,0),
(37,'EPIC 7 - klubovna 2p','Budova C, dveře č. 203','Velká klubovna na C',39,0),
(38,'EPIC 8 - prosklená 2p','Budova C, dveře č. 210','Prosklená klubovna',40,0),
(82,'EPIC 9 - klubovna 3p','Budova C, dveře č. 303','Velká klubovna na C',41,0),
(39,'Larp 1 - 1L pokoj 3p.','Budova C, dveře č. 308','Pokoj 1L',43,0),
(40,'Larp 2 - dvojpokoj 3p.','Budova C, dveře č. 310+311','Dvojmístnost',44,0),
(41,'Larp 3 - bunkr B','Budova C, suterén, bunkr B','Dveře vzadu vlevo',45,0),
(42,'Larp 4 - DDM sál','DDM, přízemí, velký sál','',46,0),
(43,'Larp 5 - DDM knihovna','DDM, přízemí, naproti sálu','',47,0),
(44,'Larp 6 - DDM 42, malá','DDM, 1. patro, dveře č. 42','',48,0),
(45,'Larp 7 - DDM 36, třída','DDM, 1. patro, dveře č. 36','',49,0),
(46,'Larp 8 - DDM, hudebna','DDM, 2. patro, dveře č. 12','',50,0),
(47,'Larp 9 - Sborovna','Budova A, dveře č. 18','Sborovna na A',51,0),
(48,'Larp 10 - knihovna','Budova B, suterén','Po schodech dolů vpravo, dveře vpravo',52,0),
(49,'Larp 11 - W. družina','Waldorf, družina','Samostatná budova',53,0),
(50,'Larp 12 - W. zahrada','','Zahrada Waldorf družiny',54,0),
(51,'WarG 1','KD, 1. patro vlevo','',55,0),
(69,'WarG 2 - KD1','KD, 1. patro, první vpravo','',56,0),
(62,'WarG 3 - KD2','KD, 1. patro, druhá vpravo','',57,0),
(66,'WarG 4 - Předsálí up','KD, 1. patro, předsálí','',58,0),
(79,'WarG 5 - tělocvična','ZŠ Staňkova','Vchod za KD',59,0),
(null,'WarG 6 - C1','','',60,0),
(null,'WarG 7 - C2','','',61,0),
(52,'Bonus 1 - klubovna','Budova C, dveře č. 3','Velká klubovna na C',62,0),
(53,'Bonus 2 - bunkr I','Budova C, suterén, bunkr I','Tři propojené kumbály, napravo',63,0),
(56,'Bonus 5 - zahrada C','Budova C, zahrada','Hřiště',64,0),
(57,'Bonus 6 - venku na GC','','',65,0),
(58,'Bonus 7 - mimo GC','','',66,0),
(null,'Bonus 8 - tělocvična','ZŠ Staňkova','Vchod za KD',67,0),
(59,'Desk 1 - hlavní','KD, 1. patro, taneční sál','',68,0),
(60,'Desk 2 - hlavní, pódium','KD, 1. patro, pódium v sále','',69,0),
(61,'Desk 3 - malá','KD, 1. patro, prosklený sál','Prosklený sál na konci chodby',70,0),
(63,'Přednáškovka','Budova C, Hudební Klub','Hudební klub, suterén',71,0),
(64,'Prog 1 - Zahrada KD','Atrium za KD, vchod kolem infopultu','',72,0),
(54,'Prog 2 - Zahrada A','Budova A, zahrada','Nějaké stromy atp.',73,0),
(55,'Prog 3 - Zahrada B','Budova B, zahrada','Volnější prostor, blíž bráně',74,0),
(65,'Prog 4 - Kino','','',75,0),
(67,'Prog 5 - předsálí down','KD, přízemí, předsálí','',76,0),
(68,'Prog 6 - Bunkr D+E','Budova C, suterén','Vstup přes bunkr C',77,0),
(70,'Prog 7 - rezerva 1','n/a','n/a',78,0),
(71,'Prog 8 - rezerva 2','n/a','n/a',79,0),
(72,'Prog 9 - jídelna','Budova C mezipatro pod přízemím vzadu','',80,0),
(73,'Prog 10 - mimo GC','','',81,0),
(74,'Záz 1 - infopult','KD, přízemí u šaten','',82,0),
(75,'Záz 2 - štáb','Budova C, přízemí, dveře 28','',83,0),
(76,'Záz 3 - sklad IT','Budova C, přízemí, dveře 30','Pokoj 3L',84,0),
(77,'Záz 4 - snídárna','Budova B, dveře č. 27','Snídárna na B',85,0),
(78,'Záz 5 - ostatní','','',86,0),
(null,'KDD S-vstup','','',87,0),
(null,'KDD DH-vstup','','',88,0),
(null,'KDD DH-bar','','',89,0),
(null,'KDD L1','','',90,0),
(null,'KDD L2','','',91,0),
(null,'KDD L3','','',92,0),
(null,'KDD L4','','',93,0),
(null,'KDD L5','','',94,0),
(null,'KDD P1','','',95,0),
(null,'KDD P2','','',96,0),
(null,'KDD P3','','',97,0),
(null,'KDD P4','','',98,0),
(null,'KDD vstup pod.','','',99,0),
(null,'SM 1','','',100,0),
(null,'SM 2','','',101,0),
(null,'SM 3','','',102,0),
(null,'SM 4','','',103,0),
(null,'EPIC X','rezervní EPIC','rezerva',42,0);

UPDATE akce_lokace
JOIN akce_lokace_tmp on akce_lokace.nazev = akce_lokace_tmp.nazev
SET akce_lokace.nazev = UUID();

UPDATE akce_lokace
JOIN akce_lokace_tmp on akce_lokace.id_lokace = akce_lokace_tmp.id_lokace
SET akce_lokace.nazev = akce_lokace_tmp.nazev,
  akce_lokace.poradi = akce_lokace_tmp.poradi,
  akce_lokace.rok = akce_lokace_tmp.rok,
  akce_lokace.dvere = akce_lokace_tmp.dvere,
  akce_lokace.poznamka = akce_lokace_tmp.poznamka;

INSERT INTO akce_lokace (nazev, dvere, poznamka, poradi, rok)
SELECT akce_lokace_tmp.nazev, akce_lokace_tmp.dvere, akce_lokace_tmp.poznamka, akce_lokace_tmp.poradi, akce_lokace_tmp.rok
FROM akce_lokace_tmp
LEFT JOIN akce_lokace ON akce_lokace_tmp.id_lokace = akce_lokace.id_lokace
WHERE akce_lokace.id_lokace IS NULL;

DROP TEMPORARY TABLE akce_lokace_tmp;
SQL
);
