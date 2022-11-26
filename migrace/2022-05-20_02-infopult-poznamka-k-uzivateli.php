<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
ALTER TABLE uzivatele_hodnoty
    ADD COLUMN infopult_poznamka VARCHAR(128) NOT NULL DEFAULT '',
    ADD INDEX (infopult_poznamka)
SQL
);
