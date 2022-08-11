<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
INSERT IGNORE INTO systemove_nastaveni (klic, hodnota, nazev, popis, datovy_typ, skupina)
    VALUES
        ('UPOZORNIT_NA_NEUZAMKNUTOU_AKTIVITU_X_MINUT_PO_KONCI', 60, 'Kdy vypravěče upozorníme že nezavřel', 'Po jaké době od konce aktivity odešleme vypravěčům mail, že aktivitu neuzavřeli - může to být se zpožděním, automat se pouští jen jednou za hodinu', 'integer','Čas')
SQL
);
