<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
ALTER TABLE akce_seznam
CHANGE COLUMN nedava_slevu nedava_bonus TINYINT(1) NOT NULL COMMENT 'aktivita negeneruje organizátorovi bonus za vedení aktivity'
SQL,
);

$this->q(<<<SQL
UPDATE reporty
SET nazev = 'Finance: Aktivity negenerující bonus',
    skript = 'finance-aktivity-negenerujici-bonus'
WHERE skript = 'finance-aktivity-negenerujici-slevu'
SQL,
);
