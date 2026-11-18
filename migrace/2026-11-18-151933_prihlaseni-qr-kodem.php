<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Login with a QR code (Gamecon\Uzivatel\PrihlaseniQrKodem): a device without a password shows a QR code with a link
// and a number, the user opens the link on a phone where they are logged in, picks the same number and approves.
// The device asks meanwhile and then logs in as whoever approved.
// - kod_hash: SHA-256 of the code in the link, reading the table gives nobody a link to approve.
// - cislo: the number the device shows, the phone has to pick the same one.
// - nabidka_cisel: the three numbers the phone offers, in their order, comma separated: cislo and two random decoys.
//   Stored, not derived from the code, so whoever has the code (a link without the device's screen) cannot tell
//   the right number from the decoys.
// - times in seconds since the epoch: the code is valid for a moment only, and a moment measured in local DATETIME
//   breaks when the daylight-saving time changes.
// - ip, ip_rozsah, prohlizec: the device that asks, shown on the phone before approving. ip_rozsah is the range
//   the limit of requests counts by, the same expression as in obnova_hesla_log (2026-11-06-110407).
// - razitko_prihlaseni: the login stamp of whoever approved, from their session. When it changes before the device
//   logs in (logging out other devices, a new password), the device does not log in.
$this->q(<<<'SQL'
CREATE TABLE IF NOT EXISTS prihlaseni_qr
(
    id                 BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    kod_hash           CHAR(64)            NOT NULL,
    cislo              TINYINT UNSIGNED    NOT NULL,
    nabidka_cisel      VARCHAR(8)          NOT NULL,
    do_adminu          TINYINT(1)          NOT NULL,
    ip                 VARCHAR(45)         NOT NULL,
    ip_rozsah          VARCHAR(45) AS (
        CASE
            WHEN NOT IS_IPV6(ip) THEN ip
            WHEN IS_IPV4_MAPPED(INET6_ATON(ip)) THEN INET6_NTOA(SUBSTRING(INET6_ATON(ip), 13))
            ELSE INET6_NTOA(CONCAT(LEFT(INET6_ATON(ip), 8), UNHEX('0000000000000000')))
        END
    ) STORED,
    prohlizec          VARCHAR(255)        NOT NULL,
    vytvoreno_unix     INT UNSIGNED        NOT NULL,
    id_uzivatele       BIGINT(20) UNSIGNED          DEFAULT NULL,
    razitko_prihlaseni VARCHAR(32)                  DEFAULT NULL,
    schvaleno_unix     INT UNSIGNED                 DEFAULT NULL,
    zamitnuto_unix     INT UNSIGNED                 DEFAULT NULL,
    uplatneno_unix     INT UNSIGNED                 DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY kod_hash (kod_hash),
    KEY ip_rozsah_vytvoreno (ip_rozsah, vytvoreno_unix),
    KEY vytvoreno_unix (vytvoreno_unix),
    FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_czech_ci
SQL,
);
