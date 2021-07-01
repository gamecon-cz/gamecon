<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
ALTER TABLE uzivatele_hodnoty
ADD COLUMN potvrzeni_proti_covid19_pridano_kdy DATETIME DEFAULT NULL,
ADD COLUMN potvrzeni_proti_covid19_overeno_kdy DATETIME DEFAULT NULL
SQL
);
