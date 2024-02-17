<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
UPDATE reporty
SET nazev = 'Finance: Aktivity bez slev',
    skript = 'finance-aktivity-bez-slev'
WHERE skript = 'finance-aktivity-negenerujici-bonus'
SQL,
);
