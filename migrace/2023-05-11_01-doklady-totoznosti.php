<?php

/** @var \Godric\DbMigrations\Migration $this */
$this->q(<<<SQL
ALTER TABLE uzivatele_hodnoty
    ADD COLUMN typ_dokladu_totoznosti VARCHAR(16) NOT NULL DEFAULT ''
SQL,
);

/** @var \Godric\DbMigrations\Migration $this */
$this->q(<<<SQL
UPDATE uzivatele_hodnoty
    SET op = ''
WHERE TRUE
SQL,
);
