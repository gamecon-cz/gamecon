<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
INSERT INTO r_prava_soupis
SET id_prava = 1033,
    jmeno_prava = 'Změna práv',
    popis_prava = 'Může měnit práva rolím a měnit role uživatelům'
SQL
);

$this->q(<<<SQL
INSERT INTO prava_role
SET id_prava = 1033, id_role = 23
SQL
);
