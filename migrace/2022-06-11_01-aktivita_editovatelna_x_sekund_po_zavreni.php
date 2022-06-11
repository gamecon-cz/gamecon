<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
INSERT IGNORE INTO systemove_nastaveni (klic, hodnota, nazev, popis, datovy_typ, skupina)
    VALUES
        ('AKTIVITA_EDITOVATELNA_X_MINUT_PRED_JEJIM_ZACATKEM', 20, 'Kolik minut před začátkem lze už aktivitu editovat', 'Kolik minut před začátkem aktivity už může vypravěč editovat přihlášené', 'int','Čas')
SQL
);

$this->q(<<<SQL
INSERT IGNORE INTO systemove_nastaveni (klic, hodnota, nazev, popis, datovy_typ, skupina)
    VALUES
        ('AKTIVITA_EDITOVATELNA_X_MINUT_PO_JEJIM_UZAVRENI', 60, 'Kolik minut lze aktivitu editovat po uzavření', 'Kolik minut může ještě vypravěč zpětně editovat přihlášené na aktivitě od okamžiku jejího uzavření', 'int','Čas')
SQL
);

$this->q(<<<SQL
INSERT IGNORE INTO systemove_nastaveni (klic, hodnota, nazev, popis, datovy_typ, skupina)
    VALUES
        ('PRIHLASENI_NA_POSLEDNI_CHVILI_X_MINUT_PRED_ZACATKEM_AKTIVITY', 10, 'Kolik minut před začátkem aktivity je "na poslední chvíli"', 'Nejvíce před kolika minutami před začátkem aktivity se účastník přihlásí, aby Moje aktivity ukázaly varování, že je nejspíš na cestě a ať na něj počkají', 'int','Čas')
SQL
);
