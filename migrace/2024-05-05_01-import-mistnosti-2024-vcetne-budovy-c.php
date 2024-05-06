<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
CREATE TEMPORARY TABLE akce_lokace_tmp
LIKE akce_lokace;

INSERT INTO akce_lokace_tmp(id_lokace, nazev, dvere, poznamka, poradi, rok)
VALUES
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
(75,'Provoz 5 - ostatní','Ostatní','',85,0);

UPDATE akce_lokace
JOIN akce_lokace_tmp on akce_lokace.nazev = akce_lokace_tmp.nazev
SET akce_lokace.nazev = UUID()
WHERE TRUE;

UPDATE akce_lokace
JOIN akce_lokace_tmp on akce_lokace.id_lokace = akce_lokace_tmp.id_lokace
SET akce_lokace.nazev = akce_lokace_tmp.nazev,
  akce_lokace.poradi = akce_lokace_tmp.poradi,
  akce_lokace.rok = akce_lokace_tmp.rok,
  akce_lokace.dvere = akce_lokace_tmp.dvere,
  akce_lokace.poznamka = akce_lokace_tmp.poznamka;

INSERT INTO akce_lokace(nazev, dvere, poznamka, poradi, rok)
    VALUES
('EPIC 8 - klubovna 2p', 'Budova C, dveře č. 203', 'Prosklená klubovna', 34, 0),
('EPIC 9 - klubovna 2p', 'Budova C, dveře č. 210', 'Prosklená klubovna', 35, 0),
('EPIC 10 - klubovna 3p', 'Budova C, dveře č. 303', 'Prosklená klubovna', 36, 0),
('EPIC 11 - sál 0p', 'Budova C, vchod z jídelny', 'Tělocvična vedle jídelny', 37, 0),
('Prog 9 - rezerva', '', '', 77, 0),
('Prog 10 - rezerva', '', '', 78, 0),
('Prog 11 - rezerva', '', '', 79, 0),
('Prog 12 - rezerva', '', '', 80, 0),
('LARP 9 - DM knihovna', 'Budova B, suterén', 'Po schodech dolů vpravo, dveře vpravo', 46, 0),
('LARP 10 - 1L pokoj', 'Budova C, dveře č. 308', 'Pokoj 1L', 47, 0);

DROP TEMPORARY TABLE akce_lokace_tmp;
SQL
);
