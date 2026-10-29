<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Žádosti o obnovu hesla: kdy, z jaké IP a pro jaký účet (NULL = k zadanému e-mailu
// účet není) a jestli mail odešel. Slouží k dohledání, kdo komu posílal odkazy, a počítají
// se z něj limity proti zahlcení cizí schránky (Gamecon\Uzivatel\ObnovaHeslaLog).
// IP je text kvůli IPv6 (nejvýš 45 znaků).
$this->q(<<<'SQL'
CREATE TABLE IF NOT EXISTS obnova_hesla_log
(
    id_log       BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    kdy          TIMESTAMP           NOT NULL DEFAULT current_timestamp(),
    ip           VARCHAR(45)         NOT NULL,
    id_uzivatele BIGINT(20) UNSIGNED          DEFAULT NULL,
    odeslano     TINYINT(1)          NOT NULL,
    PRIMARY KEY (id_log),
    KEY ip_kdy (ip, kdy),
    KEY id_uzivatele_kdy (id_uzivatele, kdy),
    KEY kdy (kdy),
    FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_czech_ci
SQL,
);
