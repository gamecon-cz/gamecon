<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Persistent login ("Trvale přihlásit") gets one row per device. Until now it was a single token per user
// in uzivatele_hodnoty.random, so logging in on another device logged the previous one out.
// The cookie carries the token, the table only its SHA-256, so reading the database logs nobody in.
// The selector is the stable part of the cookie that finds the row: an outdated token for an existing selector means
// the cookie has been copied, however many times the token was replaced since. The table keeps only its SHA-256
// (selektor_hash), so reading the database or a dump gives nobody a selector to log a user out with. The rows
// converted from `random` have none until their first use, their cookie carries only the token.
// pocet_vymen counts the replacements of the token and the cookie carries it too: a cookie ahead of its row comes
// from after the dump the database was restored from, not from a copy.
// predchozi_token_hash keeps the token replaced at the last use for a moment, and the new token is derived from it
// with sul_tokenu, so concurrent requests with the replaced one get the same new token, see TrvalePrihlaseni.
// vymeneno_unix is when the token was replaced, in seconds since the epoch: the replaced token logs in only for a moment
// afterwards, and a moment measured in local DATETIME breaks when the daylight-saving time changes.
// Every statement may run twice: the preview of the branch ran older versions of this migration under other names.
$this->q(<<<'SQL'
CREATE TABLE IF NOT EXISTS uzivatele_trvala_prihlaseni
(
    id                   BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    id_uzivatele         BIGINT(20) UNSIGNED NOT NULL,
    selektor_hash        CHAR(64)                     DEFAULT NULL,
    token_hash           CHAR(64)            NOT NULL,
    predchozi_token_hash CHAR(64)                     DEFAULT NULL,
    sul_tokenu           CHAR(64)                     DEFAULT NULL,
    pocet_vymen          INT UNSIGNED        NOT NULL DEFAULT 0,
    vymeneno_unix        INT UNSIGNED                 DEFAULT NULL,
    vytvoreno            DATETIME            NOT NULL DEFAULT current_timestamp(),
    naposledy_pouzito    DATETIME            NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (id),
    UNIQUE KEY selektor_hash (selektor_hash),
    UNIQUE KEY (token_hash),
    KEY (predchozi_token_hash),
    KEY (naposledy_pouzito),
    FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_czech_ci
SQL,
);

// The preview created the table without them.
$this->q(<<<'SQL'
ALTER TABLE uzivatele_trvala_prihlaseni
    ADD COLUMN IF NOT EXISTS sul_tokenu CHAR(64) DEFAULT NULL AFTER predchozi_token_hash,
    ADD COLUMN IF NOT EXISTS selektor_hash CHAR(64) DEFAULT NULL AFTER id_uzivatele,
    ADD COLUMN IF NOT EXISTS pocet_vymen INT UNSIGNED NOT NULL DEFAULT 0 AFTER sul_tokenu,
    ADD COLUMN IF NOT EXISTS vymeneno_unix INT UNSIGNED DEFAULT NULL AFTER pocet_vymen,
    ADD UNIQUE KEY IF NOT EXISTS selektor_hash (selektor_hash)
SQL,
);

// The preview measured the moment after the replacement by naposledy_pouzito, which the replacement set too. Without
// the time of the replacement, a concurrent tab with the replaced token would count as a lost response and end the login.
$this->q(<<<'SQL'
UPDATE uzivatele_trvala_prihlaseni
SET vymeneno_unix = UNIX_TIMESTAMP(naposledy_pouzito)
WHERE predchozi_token_hash IS NOT NULL
    AND vymeneno_unix IS NULL
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
// Except accounts without a password: after a quick registration `random` holds the code emailed in the confirmation
// link, which mail logs keep. A persistent login needed the password, and setting one through the web (password reset,
// profile) wrote a fresh token, so an account without a password has no token in any browser. Merging accounts could
// give a quick registration a password without writing a token; z_rychloregistrace excludes those, but it is marked
// only since July 2023, so an older quick registration merged like that, whose `random` nothing rewrote since, stays a gap.
$this->q(<<<'SQL'
INSERT IGNORE INTO uzivatele_trvala_prihlaseni (id_uzivatele, token_hash)
SELECT id_uzivatele, SHA2(random, 256)
FROM uzivatele_hodnoty
WHERE random != ''
    AND heslo_md5 != ''
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
