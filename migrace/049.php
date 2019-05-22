<?php
// Provázání čtvrtfinále a semifinále DrD, aby byli hráči při zápisu rovnou zapsáni i tam

/** @var \Godric\DbMigrations\Migration $this */
$this->q("
UPDATE akce_seznam 
SET dite = (SELECT GROUP_CONCAT(t.id_akce) FROM (SELECT id_akce FROM `akce_seznam` WHERE rok = 2019 and nazev_akce = 'Mistrovství v DrD - Finále') t) 
WHERE id_akce IN (SELECT t.id_akce FROM (SELECT id_akce FROM `akce_seznam` WHERE rok = 2019 and nazev_akce = 'Mistrovství v DrD - Semifinále') t);

UPDATE akce_seznam 
SET dite = (SELECT GROUP_CONCAT(t.id_akce) FROM (SELECT id_akce FROM `akce_seznam` WHERE rok = 2019 and nazev_akce = 'Mistrovství v DrD - Semifinále') t)  
WHERE id_akce IN (SELECT t.id_akce FROM (SELECT id_akce FROM `akce_seznam` WHERE rok = 2019 and nazev_akce = 'Mistrovství v DrD - Čtvrtfinále') t);

UPDATE akce_seznam
SET dite = 3065
WHERE id_akce IN (3055, 3056, 3057, 3058, 3059, 3067);

UPDATE akce_seznam
SET dite = 3066
WHERE id_akce IN (3060, 3061, 3062, 3063, 3064, 3068);
");