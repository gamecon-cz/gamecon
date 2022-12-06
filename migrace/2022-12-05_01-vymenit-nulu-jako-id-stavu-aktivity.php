<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
UPDATE akce_stav
    SET id_stav = id_stav + 1
ORDER BY id_stav DESC
SQL
);
