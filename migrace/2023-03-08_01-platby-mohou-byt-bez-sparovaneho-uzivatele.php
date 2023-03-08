<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
ALTER TABLE platby
MODIFY COLUMN id_uzivatele INT NULL DEFAULT NULL
SQL
);
