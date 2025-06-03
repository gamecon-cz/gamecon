<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
UPDATE reporty
SET format_xlsx = 1
WHERE id = 9;
SQL,
);
