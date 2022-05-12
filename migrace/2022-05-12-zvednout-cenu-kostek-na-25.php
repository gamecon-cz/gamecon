<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
UPDATE`shop_predmety`
SET cena_aktualni = 25
WHERE nazev LIKE '%kostka%' COLLATE utf8_czech_ci
AND model_rok <= 2022
SQL
);


