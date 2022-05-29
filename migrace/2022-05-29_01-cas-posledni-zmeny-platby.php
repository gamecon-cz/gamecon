<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
ALTER TABLE platby
ADD COLUMN pripsano TIMESTAMP NULL DEFAULT NULL
SQL
);
