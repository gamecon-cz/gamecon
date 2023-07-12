<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
UPDATE reporty
SET viditelny = 0
WHERE skript = 'zazemi-a-program-seznam-ucastniku-a-tricek'
SQL,
);
