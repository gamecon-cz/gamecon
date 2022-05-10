<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
INSERT IGNORE INTO systemove_nastaveni (klic, hodnota, nazev, popis, datovy_typ)
    VALUES ('KURZ_EURO', 24, 'Kurz Eura', 'Kolik kč je pro nás letos jedno €', 'number')
SQL
);
