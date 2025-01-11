<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
ALTER TABLE shop_predmety
DROP COLUMN auto
SQL,
);

