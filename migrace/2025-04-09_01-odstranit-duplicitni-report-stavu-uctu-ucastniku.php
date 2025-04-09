<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
DELETE FROM reporty WHERE skript = 'stavy-uctu-ucastniku';
SQL
);
