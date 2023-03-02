<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
UPDATE systemove_nastaveni
SET nazev = 'Počet dní od registrace před hromadným odhlašováním kdy je chráněn'
WHERE nazev = 'Počet dní od registrace před vlnou kdy je chráněn'
SQL
);
