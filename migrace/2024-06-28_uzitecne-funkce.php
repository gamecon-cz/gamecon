<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
CREATE FUNCTION IF NOT EXISTS maPravo(user INT, pravo INT) RETURNS BOOL READS SQL DATA
RETURN EXISTS(SELECT 1
              FROM platne_role_uzivatelu
                JOIN prava_role ON prava_role.id_role = platne_role_uzivatelu.id_role
              WHERE platne_role_uzivatelu.id_uzivatele = user AND prava_role.id_prava = pravo)
SQL,
);
