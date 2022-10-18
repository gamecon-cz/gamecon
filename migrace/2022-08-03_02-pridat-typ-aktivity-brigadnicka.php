<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
INSERT IGNORE INTO stranky
    SET id_stranky = 165,
        url_stranky = 'o-aktivite-bez-typu',
        obsah = 'Každá aktivita by měla mít typ - to že má tento je špatně. Toto je pseudo typ existující jen proto, aby se systém nehroutil. Pokud nevíš tak zřejmě hledáš typ "technická"',
        poradi = 0
SQL
);
