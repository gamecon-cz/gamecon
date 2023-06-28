<?php
/** @var \Godric\DbMigrations\Migration $this */

$ceskaRepublika = \Gamecon\Stat::CZ_ID;
$this->q(<<<SQL
UPDATE uzivatele_hodnoty
SET stat_uzivatele = $ceskaRepublika
WHERE stat_uzivatele = 0
SQL,
);
