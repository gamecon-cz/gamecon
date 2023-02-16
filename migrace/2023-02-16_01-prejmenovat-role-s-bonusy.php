<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
UPDATE role_seznam
    SET nazev_role = 'Půl-org ubytko',
    popis_role = 'Krom jiného ubytování zdarma',
    kod_role = 'PUL_ORG_UBYTKO',
    vyznam_role = 'PUL_ORG_UBYTKO'
WHERE kod_role = 'ORGANIZATOR_S_BONUSY_1'
SQL
);

$this->q(<<<SQL
UPDATE role_seznam
    SET nazev_role = 'Půl-org tričko',
    popis_role = 'Krom jiného trička zdarma',
    kod_role ='PUL_ORG_TRICKO',
    vyznam_role = 'PUL_ORG_TRICKO'
WHERE kod_role = 'ORGANIZATOR_S_BONUSY_2'
SQL
);
