<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
UPDATE reporty
SET nazev = 'Finance: Zůstatky všech účastníků'
WHERE skript = 'finance-lide-v-databazi-a-zustatky'
SQL,
);
