<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
DROP FUNCTION IF EXISTS rocnik
SQL
);

// workaround abychom mohli použít variable v SQL view
$this->q(<<<SQL
CREATE FUNCTION rocnik()
    RETURNS INTEGER
BEGIN
    RETURN IF(@rocnik IS NOT NULL, @rocnik, YEAR(NOW()));
END
SQL
);

$this->q(<<<SQL
DROP VIEW IF EXISTS letos_platne_zidle
SQL
);

$jakykoliRocnik = \Gamecon\Role\Zidle::JAKYKOLI_ROK;
$this->q(<<<SQL
CREATE SQL SECURITY INVOKER VIEW letos_platne_zidle
AS SELECT * FROM r_zidle_soupis
WHERE rok IN (rocnik(), $jakykoliRocnik)
SQL
);

$this->q(<<<SQL
DROP VIEW IF EXISTS letos_platne_zidle_uzivatelu
SQL
);

$this->q(<<<SQL
CREATE SQL SECURITY INVOKER VIEW letos_platne_zidle_uzivatelu
AS SELECT r_uzivatele_zidle.*
   FROM r_uzivatele_zidle
   JOIN letos_platne_zidle ON r_uzivatele_zidle.id_zidle = letos_platne_zidle.id_zidle
SQL
);
