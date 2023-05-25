<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
INSERT INTO systemove_nastaveni
SET klic = 'KOLIK_MINUT_JE_ODHLASENI_AKTIVITY_BEZ_POKUTY',
    hodnota = '5',
    vlastni = 1,
    datovy_typ = 'integer',
    nazev = 'Kolik minut je odhlášení aktivity bez pokuty',
    popis = 'Když se účastník přihlásí na aktivitu a do několika minut se zase odhlásí, tak mu nebudeme počítat storno ani pár hodin před jejím začátkem',
    zmena_kdy = NOW(),
    skupina = 'Aktivita',
    poradi = (SELECT MAX(poradi) + 1 FROM systemove_nastaveni AS predchozi),
    pouze_pro_cteni  = 0,
    rocnik_nastaveni = -1 -- navždy
SQL,
);
