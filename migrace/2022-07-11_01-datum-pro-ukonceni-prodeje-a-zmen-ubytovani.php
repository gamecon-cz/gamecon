<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
INSERT IGNORE INTO systemove_nastaveni (klic, hodnota, aktivni, nazev, popis, datovy_typ, skupina, poradi)
    VALUES
        ('UBYTOVANI_LZE_OBJEDNAT_A_MENIT_DO_DNE', '2022-07-10', 1, 'Ukončení prodeje bytování na konci dne', 'Datum, do kdy ještě (včetně) lze v přihlášce měnit ubytování, než se zamkne', 'date', 'Časy',  (SELECT MAX(poradi) + 1 FROM systemove_nastaveni AS predchozi))
SQL
);
