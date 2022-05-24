<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
UPDATE akce_typy
SET poradi = CASE typ_1pmn
    WHEN 'workshopy' THEN 0
    WHEN 'organizační výpomoc' THEN 1
    WHEN 'deskoherna' THEN 2
    WHEN 'turnaje v deskovkách' THEN 3
    WHEN 'epické deskovky' THEN 4
    WHEN 'wargaming' THEN 5
    WHEN 'larpy' THEN 6
    WHEN 'RPG' THEN 7
    WHEN 'mistrovství v DrD' THEN 8
    WHEN 'legendy klubu dobrodruhů' THEN 9
    WHEN 'akční a bonusové aktivity' THEN 10
    WHEN 'přednášky' THEN 11
    WHEN 'doprovodný program' THEN 12
    ELSE typ_1pmn
    END
SQL
);
