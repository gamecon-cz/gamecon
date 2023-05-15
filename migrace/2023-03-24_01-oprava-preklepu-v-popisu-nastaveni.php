<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
UPDATE systemove_nastaveni
    SET nazev = 'Ukončení prodeje ubytování na konci dne'
WHERE popis = 'Ukončení prodeje bytování na konci dne'
SQL
);
