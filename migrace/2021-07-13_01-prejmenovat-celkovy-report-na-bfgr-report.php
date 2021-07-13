<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
UPDATE reporty
SET skript = 'bfgr-report'
WHERE skript = 'celkovy-report'
SQL
);
