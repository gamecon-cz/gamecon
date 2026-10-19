<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Language the participant uses the website in, so that e-mails sent outside of a request (cron) know it too.
$this->q(<<<SQL
ALTER TABLE uzivatele_hodnoty
    ADD COLUMN jazyk VARCHAR(5) NOT NULL DEFAULT 'cs' COLLATE utf8mb4_czech_ci
        COMMENT 'jazyk veřejného webu a e-mailů: cs, en',
    ADD CONSTRAINT uzivatele_hodnoty_jazyk_znamy CHECK (jazyk IN ('cs', 'en'))
SQL,
);
