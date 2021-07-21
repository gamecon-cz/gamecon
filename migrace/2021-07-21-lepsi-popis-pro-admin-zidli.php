<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
UPDATE r_zidle_soupis
SET popis_zidle = 'Pro změnu účastníků v uzavřených aktivitách. NEBEZPEČNÉ, NEPOUŽÍVAT!'
WHERE jmeno_zidle = 'Admin'
SQL
);
