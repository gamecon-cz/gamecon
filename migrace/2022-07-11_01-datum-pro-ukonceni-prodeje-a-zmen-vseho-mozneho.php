<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
INSERT IGNORE INTO systemove_nastaveni (klic, hodnota, aktivni, nazev, popis, datovy_typ, skupina, poradi)
    VALUES
        ('JIDLO_LZE_OBJEDNAT_A_MENIT_DO_DNE', '', 0, 'Ukončení prodeje jídla na konci dne', 'Datum, do kdy ještě (včetně) lze v přihlášce měnit jídlo, než se zamkne', 'date', 'Časy',  (SELECT MAX(poradi) + 1 FROM systemove_nastaveni AS predchozi)),
        ('PREDMETY_BEZ_TRICEK_LZE_OBJEDNAT_A_MENIT_DO_DNE', '', 0, 'Ukončení prodeje předmětů (vyjma oblečení) na konci dne', 'Datum, do kdy ještě (včetně) lze v přihlášce měnit předměty, než se zamknou', 'date', 'Časy',  (SELECT MAX(poradi) + 1 FROM systemove_nastaveni AS predchozi)),
        ('TRICKA_LZE_OBJEDNAT_A_MENIT_DO_DNE', '', 0, 'Ukončení prodeje potištěných triček a tílek na konci dne', 'Datum, do kdy ještě (včetně) lze v přihlášce měnit trička a tílka, než se zamknou', 'date', 'Časy',  (SELECT MAX(poradi) + 1 FROM systemove_nastaveni AS predchozi))
SQL
);
