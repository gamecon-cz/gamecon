<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
CREATE TABLE systemove_nastaveni (
    id_nastaveni SERIAL,
    klic VARCHAR(128) NOT NULL PRIMARY KEY,
    hodnota VARCHAR(255) NOT NULL DEFAULT '',
    datovy_typ VARCHAR(24) NOT NULL DEFAULT 'string',
    nazev VARCHAR(255) NOT NULL UNIQUE,
    popis VARCHAR(1028) NOT NULL DEFAULT '',
    zmena_kdy TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)
SQL
);
