<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
INSERT IGNORE INTO systemove_nastaveni (klic, hodnota, nazev, popis, datovy_typ, skupina)
    VALUES
        ('AUTOMATICKY_UZAMKNOUT_AKTIVITU_X_MINUT_PO_ZACATKU', 45, 'Po kolika minutách se aktivita sama zamkne', 'Po jaké době běžící aktivitu uzamkne automat, pokud to někdo neudělá ručně - může to být se zpožděním, automat se pouští jen jednou za hodinu', 'integer','Čas')
SQL
);
