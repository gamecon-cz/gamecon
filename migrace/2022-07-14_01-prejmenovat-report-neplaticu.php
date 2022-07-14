<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
UPDATE reporty
    SET skript = 'finance-report-neplaticu'
    WHERE skript = 'finance-neplatici'
SQL
);
