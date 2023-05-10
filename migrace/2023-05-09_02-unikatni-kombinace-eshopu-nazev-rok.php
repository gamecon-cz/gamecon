<?php

/** @var \Godric\DbMigrations\Migration $this */
$this->q(<<<SQL
UPDATE shop_predmety
SET nazev = CONCAT(nazev, ' levnější')
WHERE model_rok = 2022
    AND typ = 4 -- jidlo
    AND stav = 0
SQL,
);

/** @var \Godric\DbMigrations\Migration $this */
$this->q(<<<SQL
ALTER TABLE shop_predmety
ADD UNIQUE INDEX UNIQ_nazev_model_rok(nazev, model_rok)
SQL,
);
