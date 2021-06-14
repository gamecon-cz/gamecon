<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
DROP TABLE db_migrations;
SQL
);
