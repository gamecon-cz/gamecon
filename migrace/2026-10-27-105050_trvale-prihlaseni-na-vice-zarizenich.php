<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Persistent login ("Trvale přihlásit") gets one row per device. Until now it was a single token per user
// in uzivatele_hodnoty.random, so logging in on another device logged the previous one out.
// The cookie carries the token, the table only its SHA-256, so reading the database logs nobody in.
// predchozi_token_hash keeps the token replaced at the last use for a moment, see TrvalePrihlaseni.
$this->q(<<<'SQL'
CREATE TABLE IF NOT EXISTS uzivatele_trvala_prihlaseni
(
    id                   BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    id_uzivatele         BIGINT(20) UNSIGNED NOT NULL,
    token_hash           CHAR(64)            NOT NULL,
    predchozi_token_hash CHAR(64)                     DEFAULT NULL,
    vytvoreno            DATETIME            NOT NULL DEFAULT current_timestamp(),
    naposledy_pouzito    DATETIME            NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (id),
    UNIQUE KEY (token_hash),
    KEY (predchozi_token_hash),
    KEY (naposledy_pouzito),
    FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_czech_ci
SQL,
);

// The current tokens keep working, so nobody is logged out by the deploy. Which of them still sit
// in a browser is unknown, the unused ones expire after a year like any other.
// Except quick registrations: their `random` may still be the code emailed in the confirmation link,
// which mail logs keep. Those who logged in persistently since then log in once more.
$this->q(<<<'SQL'
INSERT IGNORE INTO uzivatele_trvala_prihlaseni (id_uzivatele, token_hash)
SELECT id_uzivatele, SHA2(random, 256)
FROM uzivatele_hodnoty
WHERE random != ''
    AND COALESCE(z_rychloregistrace, 0) = 0
SQL,
);

// From now on `random` is the login stamp: a running session remembers it and ends when it changes.
// The plaintext tokens do not stay in the table, whoever read it would log in with them.
// Sessions keep running until the stamp of their user changes for the first time, see Uzivatel::PREDPONA_RAZITKA.
$this->q(<<<'SQL'
UPDATE uzivatele_hodnoty
SET random = ''
WHERE random != ''
SQL,
);
