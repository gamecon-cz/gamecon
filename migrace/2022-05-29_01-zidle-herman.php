<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
INSERT INTO r_zidle_soupis(id_zidle, jmeno_zidle, popis_zidle) VALUES
(24, 'Herman', 'Živoucí návod deskových her sloužící ve jménu Gameconu');

INSERT INTO r_prava_zidle(id_zidle, id_prava)
    SELECT 24, r_prava_zidle.id_prava
    FROM r_prava_zidle
    JOIN r_zidle_soupis ON r_prava_zidle.id_zidle = r_zidle_soupis.id_zidle
    WHERE r_zidle_soupis.jmeno_zidle = 'Dobrovolník senior'
SQL
);
