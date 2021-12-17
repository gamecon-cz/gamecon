<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
UPDATE `r_prava_soupis`
SET `jmeno_prava` = 'Bez bonusu za vedení aktivit', popis_prava = 'Nedostává bonus za vedení aktivit ani za účast na technických aktivitách'
WHERE `jmeno_prava` = 'Bez slevy za aktivity'
SQL
);
