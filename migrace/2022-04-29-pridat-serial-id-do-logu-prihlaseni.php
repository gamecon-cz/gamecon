<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
ALTER TABLE `akce_prihlaseni_log`
    ADD COLUMN id_log SERIAL FIRST
SQL
);
