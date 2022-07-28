<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
INSERT IGNORE INTO systemove_nastaveni (klic, hodnota, nazev, popis, datovy_typ, skupina)
    VALUES
        ('UPOZORNIT_NA_NEUZAMKNUTOU_AKTIVITU_S_MAXIMALNE_X_VYPRAVECI', 3, 'Kolik vypravěčů ještě upozorníme že aktivitu nezavřeli', 'Kolik nanejvýš vypravěčů má mít aktivita, abychom jim ještě poslali mail (aniž bychom spamovali davy), že ji neuzavřeli - může to být se zpožděním, automat se pouští jen jednou za hodinu', 'integer','Čas')
SQL
);
