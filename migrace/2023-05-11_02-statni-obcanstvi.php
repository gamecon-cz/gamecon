<?php

/** @var \Godric\DbMigrations\Migration $this */
$this->q(<<<SQL
ALTER TABLE uzivatele_hodnoty
    ADD COLUMN statni_obcanstvi VARCHAR(64) NULL DEFAULT NULL
SQL,
);

/** @var \Godric\DbMigrations\Migration $this */
$this->q(<<<SQL
UPDATE uzivatele_hodnoty
    SET statni_obcanstvi = CASE stat_uzivatele
        WHEN 1 THEN 'ČR'
        WHEN 2 THEN 'SK'
    END
SQL,
);
