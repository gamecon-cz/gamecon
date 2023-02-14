<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
UPDATE role_seznam
    SET nazev_role = '1/2 org ubytko',
    popis_role = 'Krom jiného ubytování zdarma'
WHERE nazev_role = 'Organizátor (s bonusy 1)'
SQL
);

$this->q(<<<SQL
UPDATE role_seznam
    SET nazev_role = '1/2 org tričko',
    popis_role = 'Krom jiného trička zdarma'
WHERE nazev_role = 'Organizátor (s bonusy 2)'
SQL
);
