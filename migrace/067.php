<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
UPDATE r_prava_soupis
SET jmeno_prava = 'Modré tričko za dosaženou slevu %MODRE_TRICKO_ZDARMA_OD%'
WHERE jmeno_prava = 'Modré tričko za dosaženou slevu 660'
SQL
);
