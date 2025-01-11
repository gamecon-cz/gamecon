<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
ALTER TABLE shop_predmety
DROP COLUMN auto, DROP COLUMN kategorie_predmetu, DROP COLUMN se_slevou;
SQL,
);


