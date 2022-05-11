<?php

/** @var \Godric\DbMigrations\Migration $this */

// pouze BONUS_ZA_STANDARDNI_3H_AZ_5H_AKTIVITU necháme zdávat v adminu, zbylé bonusy vypočítáme
$this->q(<<<SQL
DELETE FROM systemove_nastaveni
WHERE klic IN (
    'BONUS_ZA_1H_AKTIVITU',
    'BONUS_ZA_2H_AKTIVITU',
    'BONUS_ZA_6H_AZ_7H_AKTIVITU',
    'BONUS_ZA_8H_AZ_9H_AKTIVITU',
    'BONUS_ZA_10H_AZ_11H_AKTIVITU',
    'BONUS_ZA_12H_AZ_13H_AKTIVITU'
)
SQL
);

$this->q(<<<SQL
UPDATE systemove_nastaveni
SET datovy_typ = 'integer'
WHERE klic = 'BONUS_ZA_STANDARDNI_3H_AZ_5H_AKTIVITU'
SQL
);
