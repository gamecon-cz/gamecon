<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
ALTER TABLE `akce_prihlaseni_log`
MODIFY COLUMN `typ` VARCHAR(64),
    ADD INDEX (`typ`)
SQL
);
