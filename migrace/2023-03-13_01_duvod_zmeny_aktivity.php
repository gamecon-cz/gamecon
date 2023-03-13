<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
ALTER TABLE akce_prihlaseni_log
    ADD COLUMN duvod_zmeny VARCHAR(128) DEFAULT NULL,
    ADD INDEX duvod_zmeny(duvod_zmeny)
SQL
);
