<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
DELETE FROM reporty
WHERE skript IN('maily-dle-data-ucasti', 'maily-neprihlaseni');
SQL,
);
