<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
UPDATE reporty
SET format_xlsx = 0
WHERE skript = 'report-infopult-ucastnici-balicky'
SQL,
);
