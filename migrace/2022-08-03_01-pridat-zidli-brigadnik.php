<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
INSERT INTO r_zidle_soupis
    SET id_zidle = 25,
    jmeno_zidle = 'Brigádník',
    popis_zidle = 'Zase práce?'
SQL
);
