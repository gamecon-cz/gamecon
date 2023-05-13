<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
INSERT INTO systemove_nastaveni
SET klic = 'POSILAT_MAIL_O_ODHLASENI_A_UVOLNENEM_UBYTOVANI',
    hodnota = '0',
    vlastni = 1,
    datovy_typ = 'boolean',
    nazev = 'Poslat nám e-mail o uvolněném ubytování',
    popis = 'Když se účastník odhlásí z GC a měl objednané ubytování, tak nám o tom přijde email na info@gamecon.cz',
    zmena_kdy = NOW(),
    skupina = 'Notifikace',
    poradi = (SELECT MAX(poradi) + 1 FROM systemove_nastaveni AS predchozi),
    pouze_pro_cteni  = 0,
    rocnik_nastaveni = -1 -- navždy
SQL,
);
