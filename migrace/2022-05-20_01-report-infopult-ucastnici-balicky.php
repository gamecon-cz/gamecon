<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
INSERT INTO reporty(skript,nazev,format_csv,format_html,viditelny)
VALUES ('report-infopult-ucastnici-balicky','Infopult: Balíčky účastníků',1,1,1)
SQL
);
