<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
UPDATE systemove_nastaveni
    SET klic = 'UCASTNIKY_LZE_PRIDAVAT_X_MINUT_PO_KONCI_AKTIVITY',
    nazev = 'Kolik minut po konci aktivity lze potvrzovat účastníky',
    popis = 'Kolik minut může ještě vypravěč zpětně přidávat účastníky a potvrzovat jejich účast od okamžiku jejího skončení. Neplatí pro odebírání účastníků.'
    WHERE klic = 'AKTIVITA_EDITOVATELNA_X_MINUT_PO_JEJIM_KONCI'
SQL
);
