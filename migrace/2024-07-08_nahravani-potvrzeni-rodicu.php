<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
ALTER TABLE uzivatele_hodnoty
ADD COLUMN potvrzeni_zakonneho_zastupce_soubor datetime
SQL,
);

$this->q(<<<SQL
INSERT INTO reporty(skript, nazev, format_xlsx, format_html, viditelny) VALUES ('infopult-report-nezkontrolovane-potvrzeni-rodicu', 'Infopult: Nezkontrolované potvrzení rodičů', 1, 1, 1)
SQL,
);
