<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
UPDATE akce_typy
SET poradi = CASE typ_1pmn
    WHEN 'workshopy' THEN -2
    WHEN 'organizační výpomoc' THEN -1

    WHEN 'deskoherna' THEN 1
    WHEN 'turnaje v deskovkách' THEN 2
    WHEN 'epické deskovky' THEN 3
    WHEN 'wargaming' THEN 4
    WHEN 'larpy' THEN 5
    WHEN 'RPG' THEN 6
    WHEN 'mistrovství v DrD' THEN 7
    WHEN 'legendy klubu dobrodruhů' THEN 8
    WHEN 'akční a bonusové aktivity' THEN 9
    WHEN 'přednášky' THEN 10
    WHEN 'doprovodný program' THEN 11
    ELSE typ_1pmn
    END
SQL
);
