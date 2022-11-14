<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
UPDATE reporty
SET skript = 'maily-dle-data-ucasti',
    nazev = 'Maily - nedávní účastníci'
WHERE skript = 'maily-dle-data-ucasti?start=0'
SQL
);

$this->q(<<<SQL
DELETE FROM reporty
WHERE skript = 'maily-dle-data-ucasti?start=2000'
SQL
);
