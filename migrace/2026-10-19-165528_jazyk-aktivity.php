<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Language the activity is run in, so that foreigners can find what they can join (see Gamecon\Aktivita\JazykAktivity).
$this->q(<<<SQL
ALTER TABLE akce_seznam
    ADD COLUMN jazyk VARCHAR(16) NOT NULL DEFAULT 'cs' COLLATE utf8mb4_czech_ci
        COMMENT 'jazyk, ve kterém aktivita probíhá: cs, en, cs-en (obojí), nezavisla (jazykově nezávislá)'
        AFTER popis_kratky,
    ADD CONSTRAINT akce_seznam_jazyk_znamy CHECK (jazyk IN ('cs', 'en', 'cs-en', 'nezavisla'))
SQL,
);
