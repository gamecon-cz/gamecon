<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Zviditelnit report ubytovaných cizinců v modulu Reporty (dosud viditelny = 0,
// dostupný jen přes odkaz v modulu Peníze).
$this->q(<<<SQL
UPDATE reporty SET viditelny = 1 WHERE skript = 'finance-report-ubytovani-cizinci'
SQL);
