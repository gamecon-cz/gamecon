<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
ALTER TABLE systemove_nastaveni
    CHANGE COLUMN aktivni vlastni TINYINT(1) DEFAULT 0 NULL
SQL
);

$this->q(<<<SQL
ALTER TABLE systemove_nastaveni_log
    CHANGE COLUMN aktivni vlastni TINYINT(1)
SQL
);
