<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
ALTER TABLE shop_predmety ADD COLUMN podtyp VARCHAR(255) DEFAULT NULL AFTER typ
SQL,
);
