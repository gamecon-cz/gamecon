<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
ALTER TABLE systemove_nastaveni CONVERT TO CHARACTER SET utf8;
ALTER TABLE systemove_nastaveni_log CONVERT TO CHARACTER SET utf8;
SQL
);
