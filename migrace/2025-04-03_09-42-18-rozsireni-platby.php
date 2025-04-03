<?php

/** @var \Godric\DbMigrations\Migration $this */
$this->q(<<<SQL
ALTER TABLE platby
    ADD COLUMN vs VARCHAR(255) NULL DEFAULT NULL AFTER fio_id,
    ADD COLUMN nazev_protiuctu VARCHAR(255) NULL DEFAULT NULL AFTER vs,
    ADD COLUMN cislo_protiuctu VARCHAR(255) NULL DEFAULT NULL AFTER nazev_protiuctu,
    ADD COLUMN kod_banky_protiuctu VARCHAR(127) NULL DEFAULT NULL AFTER cislo_protiuctu,
    ADD COLUMN nazev_banky_protiuctu VARCHAR(255) NULL DEFAULT NULL AFTER kod_banky_protiuctu
SQL,
);
