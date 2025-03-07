<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
ALTER TABLE platby
ADD COLUMN skryta_poznamka TEXT NULL AFTER poznamka
SQL
);
