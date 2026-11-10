<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Persistent login ("Trvale přihlásit") gets one row per device. Until now it was a single token per user
// in uzivatele_hodnoty.random, so logging in on another device logged the previous one out.
// The cookie carries the token, the table only its SHA-256, so reading the database logs nobody in.
// predchozi_token_hash keeps the token replaced at the last use for a moment, see TrvalePrihlaseni.
// Every statement may run twice: the preview of the branch ran an older version of this migration under another name.
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

// The login stamp: a running session remembers it and ends when it changes, see Uzivatel::zmenRazitkoPrihlaseni().
// NULL until it changes for the first time, so the sessions from before the deploy, which have none, keep running.
$this->q(<<<'SQL'
ALTER TABLE uzivatele_hodnoty
    ADD COLUMN IF NOT EXISTS razitko_prihlaseni VARCHAR(32) DEFAULT NULL AFTER random
SQL,
);

// The current tokens keep working, so nobody is logged out by the deploy. Which of them still sit in a browser
// is unknown: the old code wrote a fresh token at every registration, logout and password change too, so most
// of the rows will never be used. They expire after a year like any other, and TrvalePrihlaseni::zaloz() then
// deletes a few of the expired rows at each persistent login, not all of them in one request.
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

// The plaintext tokens do not stay in `random`, whoever read it would log in with them. The new code writes
// there only the code emailed after a quick registration, which nothing reads.
$this->q(<<<'SQL'
UPDATE uzivatele_hodnoty
SET random = ''
WHERE random != ''
SQL,
);
