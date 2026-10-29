<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Announcements shown on every page of the public web, typically during the festival (admin Web → Oznámení na webu).
// Without a start it shows at once, without an end until somebody ends it.
$this->q(<<<'SQL'
CREATE TABLE IF NOT EXISTS oznameni_na_webu
(
    id_oznameni INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    text        TEXT                NOT NULL COLLATE utf8mb4_czech_ci,
    typ         VARCHAR(20)         NOT NULL COLLATE utf8mb4_czech_ci,
    zobrazit_od DATETIME                     DEFAULT NULL,
    zobrazit_do DATETIME                     DEFAULT NULL,
    zmenil      BIGINT(20) UNSIGNED          DEFAULT NULL,
    zmeneno_kdy TIMESTAMP           NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    CONSTRAINT oznameni_na_webu_text_neprazdny CHECK (TRIM(text) <> ''),
    CONSTRAINT oznameni_na_webu_od_pred_do CHECK (zobrazit_od IS NULL OR zobrazit_do IS NULL OR zobrazit_od < zobrazit_do),
    FOREIGN KEY (zmenil) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE SET NULL ON UPDATE CASCADE,
    PRIMARY KEY (id_oznameni)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_czech_ci
SQL,
);

// Colours of the modern look for the year, set by the graphic designer (admin Web → Barvy a motivy webu).
// At most two rows: the published colours and a draft the designer previews before publishing.
// Colours as JSON {"cervena": "#e22630", …}, a colour missing from it keeps the default from moderni.css.
$this->q(<<<'SQL'
CREATE TABLE IF NOT EXISTS barvy_webu
(
    stav        VARCHAR(20)         NOT NULL COLLATE utf8mb4_czech_ci,
    barvy       TEXT                NOT NULL COLLATE utf8mb4_czech_ci,
    zmenil      BIGINT(20) UNSIGNED          DEFAULT NULL,
    zmeneno_kdy TIMESTAMP           NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    CONSTRAINT barvy_webu_json CHECK (JSON_VALID(barvy)),
    FOREIGN KEY (zmenil) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE SET NULL ON UPDATE CASCADE,
    PRIMARY KEY (stav)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_czech_ci
SQL,
);

// Special themes of the modern look (April Fools' and the like) live in code, here is only when each one is on.
$this->q(<<<'SQL'
CREATE TABLE IF NOT EXISTS naplanovany_motiv_webu
(
    id_naplanovaneho_motivu INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    motiv                   VARCHAR(50)         NOT NULL COLLATE utf8mb4_czech_ci,
    zobrazit_od             DATETIME            NOT NULL,
    zobrazit_do             DATETIME            NOT NULL,
    zmenil                  BIGINT(20) UNSIGNED          DEFAULT NULL,
    zmeneno_kdy             TIMESTAMP           NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    CONSTRAINT naplanovany_motiv_webu_od_pred_do CHECK (zobrazit_od < zobrazit_do),
    FOREIGN KEY (zmenil) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE SET NULL ON UPDATE CASCADE,
    PRIMARY KEY (id_naplanovaneho_motivu)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_czech_ci
SQL,
);
