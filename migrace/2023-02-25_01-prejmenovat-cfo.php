<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
UPDATE role_seznam
SET vyznam_role = 'CFO',
    nazev_role = 'CFO',
    kod_role = 'CFO'
WHERE kod_role = 'SPRAVCE_FINANCI_GC'
SQL
);
