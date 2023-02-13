<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
UPDATE r_prava_soupis
SET popis_prava = REGEXP_REPLACE(popis_prava, 'židle', 'role')
SQL
);
