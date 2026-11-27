<?php

declare(strict_types=1);

use Gamecon\Pravo;
use Gamecon\Role\Role;

/** @var Godric\DbMigrations\Migration $this */

// Web push notifications (Trello 1019, Gamecon\PushNotifikace): a browser subscribes with the push service of its
// vendor and the server keeps the subscription, one row per browser.
// - id_trvaleho_prihlaseni: the persistent login of the device the subscription was made on. The subscription ends
//   with it (ON DELETE CASCADE), so logging out, logging out other devices, a new password and a merge of accounts
//   stop the notifications without any JavaScript running on that device.
// - endpoint: the address at the push service, case-sensitive (utf8mb4_bin, the only other collation CollationTest
//   allows besides utf8mb4_czech_ci). The specification gives it no length bound, and 2048 characters do not fit
//   a unique key, so uniqueness and lookups go through endpoint_hash.
// - endpoint_hash: the full SHA-256 of the endpoint, computed by the database.
// - p256dh, auth: the keys of the browser the payload is encrypted with (base64url of 65 and 16 bytes).
// - vapid_verejny_klic, origin: the VAPID public key and the web address of the environment the subscription was made
//   in. Beta and previews run on a copy of the production database, and they send only to rows that carry their own.
// - zarizeni: a short description derived from the User-Agent ("Chrome, Android"), not the header itself.
// - naposledy_predano: the last time the push service accepted a notification. Whether the device showed it, the
//   server never learns.
$this->q(<<<'SQL'
CREATE TABLE IF NOT EXISTS uzivatele_push_subscriptions
(
    id                     BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    id_uzivatele           BIGINT(20) UNSIGNED NOT NULL,
    id_trvaleho_prihlaseni BIGINT(20) UNSIGNED NOT NULL,
    endpoint               VARCHAR(2048) COLLATE utf8mb4_bin NOT NULL,
    endpoint_hash          CHAR(64) AS (SHA2(endpoint, 256)) STORED,
    p256dh                 VARCHAR(255) COLLATE utf8mb4_bin  NOT NULL,
    auth                   VARCHAR(255) COLLATE utf8mb4_bin  NOT NULL,
    vapid_verejny_klic     VARCHAR(255) COLLATE utf8mb4_bin  NOT NULL,
    origin                 VARCHAR(255)        NOT NULL,
    zarizeni               VARCHAR(255)        NOT NULL DEFAULT '',
    vytvoreno              DATETIME            NOT NULL DEFAULT current_timestamp(),
    aktualizovano          DATETIME            NOT NULL DEFAULT current_timestamp(),
    naposledy_predano      DATETIME                     DEFAULT NULL,
    pocet_chyb_v_rade      SMALLINT UNSIGNED   NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY endpoint_hash (endpoint_hash),
    KEY (id_uzivatele),
    FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_trvaleho_prihlaseni) REFERENCES uzivatele_trvala_prihlaseni (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_czech_ci
SQL,
);

// Who sent a notification to whom and how it went, without its content. The limit of how often one may send counts
// by it, in seconds since the epoch (a moment measured in local DATETIME breaks when the daylight-saving time changes).
// - id_odesilatele: NULL for a notification sent by the system (a cron), which nobody sends today.
// - typ: the kind of the notification (Gamecon\PushNotifikace\TypNotifikace).
$this->q(<<<'SQL'
CREATE TABLE IF NOT EXISTS push_notifikace_odeslani
(
    id             BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    id_odesilatele BIGINT(20) UNSIGNED          DEFAULT NULL,
    id_prijemce    BIGINT(20) UNSIGNED NOT NULL,
    typ            VARCHAR(64)         NOT NULL,
    odeslano_unix  INT UNSIGNED        NOT NULL,
    pocet_zarizeni SMALLINT UNSIGNED   NOT NULL DEFAULT 0,
    pocet_predano  SMALLINT UNSIGNED   NOT NULL DEFAULT 0,
    pocet_smazano  SMALLINT UNSIGNED   NOT NULL DEFAULT 0,
    pocet_chyb     SMALLINT UNSIGNED   NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY odesilatel_odeslano (id_odesilatele, odeslano_unix),
    KEY prijemce_odeslano (id_prijemce, odeslano_unix),
    FOREIGN KEY (id_odesilatele) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (id_prijemce) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_czech_ci
SQL,
);

// The right guards sending from the admin only. Turning notifications on for one's own device needs no right.
$idPrava = Pravo::ADMINISTRACE_PUSH_NOTIFIKACE;
$this->q(<<<SQL
INSERT INTO r_prava_soupis (id_prava, jmeno_prava, popis_prava)
VALUES ({$idPrava}, 'Administrace - Push notifikace', 'Smí z adminu poslat push notifikaci vybranému uživateli')
ON DUPLICATE KEY UPDATE
    jmeno_prava = VALUES(jmeno_prava),
    popis_prava = VALUES(popis_prava)
SQL,
);

// Only developers send for now, the council gives the right to other roles in the admin.
$idRoleDev = Role::DEV;
$this->q(<<<SQL
INSERT IGNORE INTO prava_role (id_role, id_prava)
SELECT role_seznam.id_role, {$idPrava}
FROM role_seznam
WHERE role_seznam.id_role = {$idRoleDev}
SQL,
);

// Off by default: a merge to main deploys to production, and nobody sees the feature until it is turned on here.
// `vlastni = 1` because the key has no record in SystemoveNastaveni::dejVychoziHodnoty().
$this->q(<<<'SQL'
INSERT IGNORE INTO systemove_nastaveni (klic, hodnota, vlastni, datovy_typ, nazev, popis, skupina, poradi,
                                        pouze_pro_cteni, rocnik_nastaveni)
VALUES ('PUSH_NOTIFIKACE_ZAPNUTE', '0', 1, 'boolean', 'Push notifikace zapnuté',
        'Přihlášení uživatelé si v Nastavení na webu mohou zapnout notifikace na svém zařízení a z adminu jim je lze poslat. Vypnuté: nikdo je nevidí a nic se neodešle.',
        'Notifikace', NULL, 0, -1)
SQL,
);
