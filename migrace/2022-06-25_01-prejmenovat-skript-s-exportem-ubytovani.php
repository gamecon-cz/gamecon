<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
UPDATE reporty
SET skript = 'finance-report-ubytovani'
WHERE skript = 'ubytovani'
SQL
);
