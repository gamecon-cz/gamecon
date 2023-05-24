<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
ALTER TABLE uzivatele_hodnoty
MODIFY COLUMN pohlavi CHAR(1) NOT NULL
SQL,
);
