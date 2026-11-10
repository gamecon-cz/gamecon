<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Veřejný archiv proběhlých přednášek (/prednasky/archiv), viz docs/generated/archiv-prednasek.md.
// Přednáška je samostatný záznam, s aktivitou programu je spárovaná jen volitelně (id_akce = hlavní
// aktivita karty, u instancí akce_instance.id_hlavni_akce). Anotaci a štítky čte web ze spárované aktivity.
// Přednášející nemá unikátní id_uzivatele: slučování uživatelů (UzivatelSlucovani) převádí odkazy přes
// UPDATE IGNORE a řádek, který narazí na unikátní klíč, smaže, takže by přednášející zmizel i z přednášek.
$this->q(<<<'SQL'
CREATE TABLE IF NOT EXISTS archiv_prednasek
(
    id_prednasky BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    nazev        VARCHAR(255)        NOT NULL,
    rok          INT(11)             NOT NULL,
    youtube_id   VARCHAR(11)                  DEFAULT NULL,
    skryta       TINYINT(1)          NOT NULL DEFAULT 0,
    id_akce      BIGINT(20) UNSIGNED          DEFAULT NULL,
    PRIMARY KEY (id_prednasky),
    UNIQUE KEY rok_nazev (rok, nazev),
    UNIQUE KEY id_akce (id_akce),
    FOREIGN KEY (id_akce) REFERENCES akce_seznam (id_akce) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_czech_ci;

CREATE TABLE IF NOT EXISTS archiv_prednasek_prednasejici
(
    id_prednasejiciho BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    jmeno             VARCHAR(255)        NOT NULL,
    id_uzivatele      BIGINT(20) UNSIGNED          DEFAULT NULL,
    PRIMARY KEY (id_prednasejiciho),
    KEY jmeno (jmeno),
    KEY id_uzivatele (id_uzivatele),
    FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_czech_ci;

CREATE TABLE IF NOT EXISTS archiv_prednasek_prednaska_prednasejici
(
    id_prednasky      BIGINT(20) UNSIGNED  NOT NULL,
    id_prednasejiciho BIGINT(20) UNSIGNED  NOT NULL,
    poradi            SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id_prednasky, id_prednasejiciho),
    KEY id_prednasejiciho (id_prednasejiciho),
    FOREIGN KEY (id_prednasky) REFERENCES archiv_prednasek (id_prednasky) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_prednasejiciho) REFERENCES archiv_prednasek_prednasejici (id_prednasejiciho) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_czech_ci;

CREATE TABLE IF NOT EXISTS archiv_prednasek_klicove_slovo
(
    id_klicoveho_slova BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    nazev              VARCHAR(255)        NOT NULL,
    PRIMARY KEY (id_klicoveho_slova),
    UNIQUE KEY nazev (nazev)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_czech_ci;

CREATE TABLE IF NOT EXISTS archiv_prednasek_prednaska_klicove_slovo
(
    id_prednasky       BIGINT(20) UNSIGNED NOT NULL,
    id_klicoveho_slova BIGINT(20) UNSIGNED NOT NULL,
    PRIMARY KEY (id_prednasky, id_klicoveho_slova),
    KEY id_klicoveho_slova (id_klicoveho_slova),
    FOREIGN KEY (id_prednasky) REFERENCES archiv_prednasek (id_prednasky) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_klicoveho_slova) REFERENCES archiv_prednasek_klicove_slovo (id_klicoveho_slova) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_czech_ci;
SQL,
);
