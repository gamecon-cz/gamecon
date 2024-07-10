<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
UPDATE hromadne_akce_log
SET akce = REPLACE(akce, 'email-varobvani-', 'email-varovani-')
WHERE akce
LIKE 'email-varobvani-%'
SQL
);
