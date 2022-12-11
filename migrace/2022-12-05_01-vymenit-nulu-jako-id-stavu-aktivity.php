<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
UPDATE akce_stav
    SET id_stav = id_stav + 1
ORDER BY id_stav DESC
SQL
);

$this->q(<<<SQL
ALTER TABLE akce_seznam
    MODIFY COLUMN stav INT NOT NULL DEFAULT 1
SQL
);
