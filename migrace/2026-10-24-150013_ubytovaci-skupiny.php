<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Roommate groups: participants who want to share a room put themselves into one group
// by its code. A row is one membership, so a group exists while it has at least one member
// and the primary key keeps everyone in at most one group per year. Microseconds in pripojen
// keep the order in which people joined, the founder first.
$this->q(<<<'SQL'
CREATE TABLE IF NOT EXISTS ubytovani_skupiny_clenove
(
    rok          SMALLINT            NOT NULL,
    id_uzivatele BIGINT(20) UNSIGNED NOT NULL,
    kod_skupiny  VARCHAR(255)        NOT NULL COLLATE utf8mb4_czech_ci,
    pripojen     TIMESTAMP(6)        NOT NULL DEFAULT current_timestamp(6),
    PRIMARY KEY (rok, id_uzivatele),
    KEY ubytovani_skupiny_clenove_kod_skupiny (rok, kod_skupiny),
    FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_czech_ci
SQL,
);
