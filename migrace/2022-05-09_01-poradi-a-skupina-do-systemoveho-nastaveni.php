<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
ALTER TABLE systemove_nastaveni
    ADD COLUMN skupina VARCHAR(128) DEFAULT NULL,
    ADD COLUMN poradi INTEGER UNSIGNED,
    ADD INDEX (skupina)
SQL
);

$this->q(<<<SQL
UPDATE systemove_nastaveni SET skupina = 'finance', poradi = 1
    WHERE klic = 'KURZ_EURO'
SQL
);
