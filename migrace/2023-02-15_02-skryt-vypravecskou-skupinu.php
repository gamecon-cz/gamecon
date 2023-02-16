<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
ALTER TABLE role_seznam
    ADD COLUMN skryta TINYINT(1) DEFAULT 0
SQL
);

$this->q(<<<SQL
UPDATE role_seznam
    SET skryta = 1
WHERE kod_role = 'VYPRAVECSKA_SKUPINA'
SQL
);
