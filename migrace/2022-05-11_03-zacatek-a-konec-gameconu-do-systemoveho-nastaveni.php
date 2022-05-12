<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
INSERT IGNORE INTO systemove_nastaveni (klic, hodnota, aktivni, nazev, popis, datovy_typ, skupina, poradi)
    VALUES
        -- \Gamecon\Cas\DateTimeGamecon::zacatekGameconu
        ('GC_BEZI_OD', '', 0 /* neaktivní, aby se vzala výchozí hodnota */, 'Začátek Gameconu', 'Datum a čas, kdy začíná Gamecon', 'datetime', 'cas',  (SELECT MAX(poradi) + 1 FROM systemove_nastaveni AS predchozi)),
        -- \Gamecon\Cas\DateTimeGamecon::konecGameconu
        ('GC_BEZI_DO', '', 0 /* neaktivní, aby se vzala výchozí hodnota */, 'Konec Gameconu', 'Datum a čas, kdy končí Gamecon', 'datetime', 'cas',  (SELECT MAX(poradi) + 1 FROM systemove_nastaveni AS predchozi))
SQL
);
