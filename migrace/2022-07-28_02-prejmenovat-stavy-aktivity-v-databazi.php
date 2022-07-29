<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
UPDATE akce_stav
SET nazev = 'zamčená' WHERE id_stav = 6
SQL
);

$this->q(<<<SQL
UPDATE akce_stav
SET nazev = 'uzavřená' WHERE id_stav = 2
SQL
);
