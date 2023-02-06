<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
UPDATE systemove_nastaveni
SET klic = 'HROMADNE_ODHLASOVANI_1'
WHERE klic = 'HROMADNE_ODHLASOVANI'
SQL
);
