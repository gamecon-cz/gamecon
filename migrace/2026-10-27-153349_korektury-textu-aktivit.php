<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// One row per activity text sent out for proofreading by an AI: the texts as they were exported
// (to show the diff against them and to notice that the organizer changed the text meanwhile),
// the AI's proposal and what the proofreader did with it. `id_akce` is the main activity of a family
// of instances, which share the texts.
$this->q(<<<'SQL'
CREATE TABLE IF NOT EXISTS akce_korektura
(
    id_korektury         BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    id_akce              BIGINT(20) UNSIGNED NOT NULL,
    balik                INT(10) UNSIGNED    NOT NULL,
    stav                 VARCHAR(20)         NOT NULL,
    puvodni_nazev        VARCHAR(255)        NOT NULL,
    puvodni_popis_kratky VARCHAR(255)        NOT NULL,
    puvodni_popis        LONGTEXT            NOT NULL,
    navrh_nazev          VARCHAR(255)                 DEFAULT NULL,
    navrh_popis_kratky   VARCHAR(255)                 DEFAULT NULL,
    navrh_popis          LONGTEXT                     DEFAULT NULL,
    zmeny                TEXT                         DEFAULT NULL,
    exportoval           BIGINT(20) UNSIGNED          DEFAULT NULL,
    exportovano_kdy      TIMESTAMP           NOT NULL DEFAULT current_timestamp(),
    navrzeno_kdy         TIMESTAMP           NULL     DEFAULT NULL,
    vyridil              BIGINT(20) UNSIGNED          DEFAULT NULL,
    vyrizeno_kdy         TIMESTAMP           NULL     DEFAULT NULL,
    PRIMARY KEY (id_korektury),
    KEY stav (stav),
    KEY balik (balik),
    CONSTRAINT akce_korektura_stav CHECK (stav IN ('exportovano', 'navrzeno', 'prijato', 'zamitnuto')),
    FOREIGN KEY (id_akce) REFERENCES akce_seznam (id_akce) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (exportoval) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (vyridil) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_czech_ci
SQL,
);
