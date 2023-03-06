<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
DELETE FROM systemove_nastaveni
WHERE klic IN ('HROMADNE_ODHLASOVANI_1', 'HROMADNE_ODHLASOVANI_2', 'HROMADNE_ODHLASOVANI_3');
SQL
);
