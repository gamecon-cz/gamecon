<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
INSERT INTO systemove_nastaveni
SET klic = 'SLEVA_ORGU_NA_JIDLO_CASTKA',
    hodnota = '25',
    vlastni = 1,
    datovy_typ = 'integer',
    nazev = 'Jakou slevu mají mít orgové na jídlo',
    popis = 'Jakou slevu na jídlo mají dostat všichni s rolí "Jídlo se slevou"',
    zmena_kdy = NOW(),
    skupina = 'Finance',
    poradi = (SELECT MAX(poradi) + 1 FROM systemove_nastaveni AS predchozi),
    pouze_pro_cteni  = 0,
    rocnik_nastaveni = -1 -- navždy
SQL,
);
