<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q("INSERT INTO reporty (skript, nazev, format_xlsx, format_html, viditelny)
    VALUES ('finance-report-odhlaseni-neplaticu', 'Finance: Odhlášené objednávky neplatičů', 1, 1, 1)");
