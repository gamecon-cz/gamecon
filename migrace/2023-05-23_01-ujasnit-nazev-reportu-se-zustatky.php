<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
UPDATE reporty
SET nazev = 'Finance: Lidé v databázi + zůstatky z předchozího ročníku'
WHERE nazev = 'Finance: Lidé v databázi + zůstatky'
    AND skript = 'finance-lide-v-databazi-a-zustatky'
SQL,
);
