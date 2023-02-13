<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
UPDATE role_seznam
SET nazev_role = 'Prezenční admin'
WHERE nazev_role = 'Admin'
SQL
);
