<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
ALTER TABLE r_zidle_soupis
ADD COLUMN typ_zidle VARCHAR(24) DEFAULT NULL
SQL
);

$this->q(<<<SQL
UPDATE r_zidle_soupis
SET typ_zidle = CASE
    WHEN id_zidle <= -100000 THEN 'rocnikova'
    WHEN id_zidle > -100000 AND id_zidle < 0 THEN 'ucast'
    ELSE 'trvala' END
SQL
);

$this->q(<<<SQL
ALTER TABLE r_zidle_soupis
MODIFY COLUMN typ_zidle VARCHAR(24) NOT NULL,
    ADD INDEX (typ_zidle)
SQL
);
