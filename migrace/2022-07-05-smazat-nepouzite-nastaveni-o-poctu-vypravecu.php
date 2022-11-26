<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
DELETE FROM systemove_nastaveni
WHERE klic = 'UPOZORNIT_NA_NEUZAMKNUTOU_AKTIVITU_S_MAXIMALNE_X_VYPRAVECI'
SQL
);
