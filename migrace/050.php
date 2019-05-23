<?php
/** @var \Godric\DbMigrations\Migration $this */
$this->q("
ALTER TABLE uzivatele_hodnoty 
ADD COLUMN potvrzeni_zakonneho_zastupce DATE DEFAULT NULL
");