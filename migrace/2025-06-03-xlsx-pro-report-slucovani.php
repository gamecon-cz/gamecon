<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
UPDATE reporty
SET format_xlsx = 1
WHERE skript like "duplicity";
SQL,
);
