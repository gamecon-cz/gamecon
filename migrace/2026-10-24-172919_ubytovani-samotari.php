<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Participants who come alone and want roommates assigned. After the accommodation sales end
// a batch in the admin puts them into rooms with a free bed. A row is one such wish for one year,
// it goes away once the participant is in a roommate group. Microseconds in zapsan keep the order
// in which people asked.
$this->q(<<<'SQL'
CREATE TABLE IF NOT EXISTS ubytovani_samotari
(
    rok          SMALLINT            NOT NULL,
    id_uzivatele BIGINT(20) UNSIGNED NOT NULL,
    zapsan       TIMESTAMP(6)        NOT NULL DEFAULT current_timestamp(6),
    PRIMARY KEY (rok, id_uzivatele),
    FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_czech_ci
SQL,
);
