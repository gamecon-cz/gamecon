<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
ALTER TABLE texty
MODIFY COLUMN `text` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci
SQL
);
