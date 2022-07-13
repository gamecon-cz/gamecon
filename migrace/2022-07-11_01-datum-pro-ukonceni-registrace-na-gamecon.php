<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
INSERT IGNORE INTO systemove_nastaveni (klic, hodnota, aktivni, nazev, popis, datovy_typ, skupina, poradi)
    VALUES
        ('REG_GC_DO', '', 0, 'Ukončení registrací přes web', 'Do kdy se lze registrovat na Gamecon přes přihlášlu na webu', 'datetime', 'Časy',  (SELECT MAX(poradi) + 1 FROM systemove_nastaveni AS predchozi))
SQL
);
