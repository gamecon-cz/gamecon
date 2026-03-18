<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q("INSERT INTO reporty (skript, nazev, format_xlsx, format_html, viditelny)
    VALUES ('infopult-ucastnici-bez-infopultu', 'Infopult: Účastníci aktivit bez průchodu infopultem', 1, 1, 1)");
