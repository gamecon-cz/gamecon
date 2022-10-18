<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
INSERT INTO r_zidle_soupis
    SET id_zidle = 24,
    jmeno_zidle = 'Brigádník',
    popis_zidle = 'Zase práce?'
SQL
);
