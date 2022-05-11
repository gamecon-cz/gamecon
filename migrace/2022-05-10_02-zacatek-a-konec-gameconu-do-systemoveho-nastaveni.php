<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
INSERT IGNORE INTO systemove_nastaveni (klic, hodnota, aktivni, nazev, popis, datovy_typ, skupina, poradi)
    VALUES
        ('GC_BEZI_OD', '', 0, 'Začátek Gameconu', 'Datum a čas, kdy začíná Gamecon', 'datetime', 'cas',  (SELECT MAX(poradi) + 1 FROM systemove_nastaveni AS predchozi)),
        ('GC_BEZI_DO', '', 0, 'Konec Gameconu', 'Datum a čas, kdy končí Gamecon', 'datetime', 'cas',  (SELECT MAX(poradi) + 1 FROM systemove_nastaveni AS predchozi))
SQL
);
