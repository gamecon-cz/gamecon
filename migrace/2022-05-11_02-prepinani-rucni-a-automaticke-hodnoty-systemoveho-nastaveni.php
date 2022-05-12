<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
ALTER TABLE systemove_nastaveni
ADD COLUMN aktivni TINYINT(1) DEFAULT 1 AFTER hodnota
SQL
);

$this->q(<<<SQL
ALTER TABLE systemove_nastaveni_log
    ADD COLUMN aktivni TINYINT(1) NULL AFTER hodnota,
    MODIFY COLUMN hodnota VARCHAR(256) NULL
SQL
);
