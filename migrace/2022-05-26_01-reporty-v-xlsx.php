<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
ALTER TABLE reporty
CHANGE format_csv format_xlsx TINYINT(1) DEFAULT 1 NULL
SQL
);
