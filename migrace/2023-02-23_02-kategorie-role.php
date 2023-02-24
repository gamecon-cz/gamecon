<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
ALTER TABLE role_seznam
ADD COLUMN kategorie_role TINYINT UNSIGNED NOT NULL DEFAULT 0 -- omezené role
SQL
);

$this->q(<<<SQL
UPDATE role_seznam
SET kategorie_role = 1 -- běžné role, které může přidávat kde kdo kde komu
WHERE
    vyznam_role IN (
        'BRIGADNIK',
        'DOBROVOLNIK_SENIOR',
        'HERMAN',
        'INFOPULT',
        'NEODHLASOVAT',
        'PARTNER',
        'STREDECNI_NOC_ZDARMA',
        'SOBOTNI_NOC_ZDARMA',
        'NEDELNI_NOC_ZDARMA',
        'VYPRAVEC',
        'ZAZEMI'
    )
SQL
);
