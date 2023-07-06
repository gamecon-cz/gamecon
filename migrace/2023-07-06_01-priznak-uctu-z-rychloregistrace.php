<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
ALTER TABLE uzivatele_hodnoty
ADD COLUMN z_rychloregistrace TINYINT(1) DEFAULT 0
SQL,
);
