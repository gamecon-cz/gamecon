<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
ALTER TABLE platby
    MODIFY COLUMN castka DECIMAL (10,2) NOT NULL
SQL,
);

$this->q(<<<SQL
ALTER TABLE slevy
    MODIFY COLUMN castka DECIMAL (10,2) NOT NULL
SQL,
);
