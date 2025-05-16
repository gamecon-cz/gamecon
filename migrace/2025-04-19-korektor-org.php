<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
INSERT INTO role_seznam
SET
    id_role = 27,
    kod_role = 'KOREKTOR',
    nazev_role = 'Korektor',
    popis_role = 'Kontrola a opravy textu',
    rocnik_role = -1,
    typ_role = 'trvala',
    vyznam_role = 'KOREKTOR',
    skryta = 0,
    kategorie_role = 0 -- omezená, pouze pro členy rady
SQL,
);
