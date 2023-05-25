<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
CREATE OR REPLACE SQL SECURITY INVOKER VIEW platne_role
AS
SELECT *
FROM role_seznam
WHERE rocnik_role IN ((SELECT hodnota FROM systemove_nastaveni WHERE klic = 'ROCNIK' LIMIT 1), -1)
   OR role_seznam.typ_role = 'ucast'
SQL,
);
