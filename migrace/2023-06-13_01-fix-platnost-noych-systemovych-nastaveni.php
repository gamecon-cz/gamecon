<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
ALTER TABLE systemove_nastaveni
MODIFY COLUMN rocnik_nastaveni INT NOT NULL DEFAULT -1
SQL,
);

$this->q(<<<SQL
UPDATE systemove_nastaveni
SET rocnik_nastaveni = -1
WHERE rocnik_nastaveni = 0
SQL,
);
