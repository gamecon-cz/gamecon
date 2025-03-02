<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
DROP FUNCTION IF EXISTS konec_gc;
SQL,
);
