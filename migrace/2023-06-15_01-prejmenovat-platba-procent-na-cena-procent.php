<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
ALTER TABLE akce_prihlaseni_stavy
    CHANGE COLUMN platba_procent cena_procent FLOAT DEFAULT 100 NOT NULL
SQL,
);
