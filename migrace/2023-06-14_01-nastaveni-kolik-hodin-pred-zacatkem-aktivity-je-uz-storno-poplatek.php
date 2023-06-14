<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
INSERT INTO systemove_nastaveni
SET klic = 'KOLIK_HODIN_PRED_ZACATKEM_AKTIVITY_JE_UZ_STORNO_POPLATEK',
    hodnota = '0', -- úvodní "vlastní" hodnota, když někdo zaškrtne vlastní
    vlastni = 0, -- použijeme výchozí hodnotu, vlastní necháme pouze v záloze
    datovy_typ = 'integer',
    nazev = 'Kolik hodin před začátkem aktivity je už storno poplatek',
    popis = 'Když se účastník odhlásí z aktivity jen několik hodin před jejím začátkem, napočítáme mu pokutu za pozdní zrušení',
    zmena_kdy = NOW(),
    skupina = 'Aktivita',
    poradi = (SELECT MAX(poradi) + 1 FROM systemove_nastaveni AS predchozi),
    pouze_pro_cteni  = 0,
    rocnik_nastaveni = -1 -- navždy
SQL,
);
