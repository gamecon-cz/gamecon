<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
ALTER TABLE akce_typy
ADD COLUMN aktivni TINYINT(1) DEFAULT 1
SQL
);

$this->q(<<<SQL
UPDATE akce_typy
SET aktivni = 0 WHERE typ_1pmn = 'workshopy'
SQL
);
