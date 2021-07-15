<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
UPDATE reporty
SET skript = 'stravenky-bianco', format_csv = 0
WHERE skript = 'stravenky?ciste';
SQL
);
