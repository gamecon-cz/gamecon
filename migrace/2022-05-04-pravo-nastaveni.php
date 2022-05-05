<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
INSERT INTO r_prava_soupis (id_prava,jmeno_prava,popis_prava)
VALUES (110,'Administrace - panel Nastavení','Systémové hodnoty pro Gamecon')
SQL
);
