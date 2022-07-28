<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
INSERT IGNORE INTO systemove_nastaveni (klic, hodnota, aktivni, nazev, popis, datovy_typ, skupina, poradi)
    VALUES
        ('UCASTNIKY_LZE_PRIDAVAT_X_DNI_PO_KONCI_GC_U_NEUZAVRENE_PREZENCE', '30', 0, 'Do kdy lze přidat účastníka', 'Kolik dní po konci GC lze ještě přidávat účastníky na Neuzavřenou aktivitu', 'datetime', 'Časy',  (SELECT MAX(poradi) + 1 FROM systemove_nastaveni AS predchozi))
SQL
);
