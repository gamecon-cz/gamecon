<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
ALTER TABLE systemove_nastaveni
ADD COLUMN rocnik_nastaveni INT NOT NULL
SQL
);

$this->q(<<<SQL
UPDATE systemove_nastaveni
SET rocnik_nastaveni = -1
SQL
);

$this->q(<<<SQL
ALTER TABLE systemove_nastaveni
    DROP PRIMARY KEY,
    DROP KEY nazev,
    ADD PRIMARY KEY (klic, rocnik_nastaveni),
    ADD UNIQUE KEY (nazev, rocnik_nastaveni)
SQL
);
