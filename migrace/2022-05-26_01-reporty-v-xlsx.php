<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
ALTER TABLE reporty
CHANGE format_xlsx format_xlsx TINYINT(1) DEFAULT 1 NULL
SQL
);
