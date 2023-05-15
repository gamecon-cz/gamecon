<?php
/** @var \Godric\DbMigrations\Migration $this */

$zacatekPrvniVlny = defined('PRVNI_VLNA_KDY') ? PRVNI_VLNA_KDY : '';

$this->q(<<<SQL
UPDATE systemove_nastaveni
    SET
        klic = 'PRVNI_VLNA_KDY',
        popis = 'Kdy se poprvé hromadně změní aktivity Připravené k aktivaci na Aktivované',
        poradi = (SELECT MAX(poradi) + 1 FROM systemove_nastaveni AS predchozi),
        aktivni = IF('$zacatekPrvniVlny' = hodnota, 0 /* bude použita výchozí hodnota */, 1)
WHERE klic= 'REG_AKTIVIT_OD'
SQL
);

$this->q(<<<SQL
INSERT INTO systemove_nastaveni
SET klic = 'DRUHA_VLNA_KDY',
    hodnota = '',
    aktivni = 0, -- bude použita výchozí hodnota
    datovy_typ = 'datetime',
    nazev = 'Začátek druhé vlny aktivit',
    popis = 'Kdy se podruhé hromadně změní aktivity Připravené k aktivaci na Aktivované',
    zmena_kdy = NOW(),
    skupina = 'Časy',
    poradi = (SELECT MAX(poradi) + 1 FROM systemove_nastaveni AS predchozi),
    pouze_pro_cteni  = 0
SQL
);

$this->q(<<<SQL
INSERT INTO systemove_nastaveni
SET klic = 'TRETI_VLNA_KDY',
    hodnota = '',
    aktivni = 0, -- bude použita výchozí hodnota
    datovy_typ = 'datetime',
    nazev = 'Začátek třetí vlny aktivit',
    popis = 'Kdy se potřetí hromadně změní aktivity Připravené k aktivaci na Aktivované',
    zmena_kdy = NOW(),
    skupina = 'Časy',
    poradi = (SELECT MAX(poradi) + 1 FROM systemove_nastaveni AS predchozi),
    pouze_pro_cteni  = 0
SQL
);
