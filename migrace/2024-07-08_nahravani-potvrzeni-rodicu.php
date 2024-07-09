<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
ALTER TABLE uzivatele_hodnoty
ADD COLUMN potvrzeni_zakonneho_zastupce_soubor datetime
SQL,
);
