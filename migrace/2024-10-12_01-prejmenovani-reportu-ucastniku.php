<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
UPDATE reporty
SET nazev = 'Maily – přihlášení na letošní GC'
WHERE nazev = 'Maily – přihlášení na GC (vč. unsubscribed)';
SQL,
);

$this->q(<<<SQL
UPDATE reporty
SET nazev = 'Maily – letošní vypravěči'
WHERE nazev = 'Maily – vypravěči (vč. unsubscribed)';
SQL,
);
