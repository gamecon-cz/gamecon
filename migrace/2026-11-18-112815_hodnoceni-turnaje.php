<?php

declare(strict_types=1);

use Gamecon\Pravo;
use Gamecon\Role\Role;

/** @var Godric\DbMigrations\Migration $this */

// Hodnocení skupin turnaje (zatím D&D) vypravěči, viz Gamecon\HodnoceniTurnaje\HodnoceniTurnaje.
// Šablona (škála, kategorie, kritéria) je jedna na ročník a typ aktivity, hodnocení jedno na skupinu a kolo.

$this->q(<<<'SQL'
CREATE TABLE IF NOT EXISTS turnaj_hodnoceni_sablona
(
    id        INT UNSIGNED         NOT NULL AUTO_INCREMENT,
    rok       SMALLINT UNSIGNED    NOT NULL,
    id_typu   INT UNSIGNED         NOT NULL,
    -- hodnoty škály oddělené mezerou, v pořadí ze souboru
    skala     VARCHAR(255)         NOT NULL,
    -- nahraný text, aby šel upravit a nahrát znovu
    zdroj     TEXT                 NOT NULL,
    nahrano   DATETIME             NOT NULL,
    id_nahral BIGINT(20) UNSIGNED           DEFAULT NULL,
    -- uzavřené hodnocení vypravěči už nezmění
    uzavreno  DATETIME                      DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY (rok, id_typu),
    FOREIGN KEY (id_typu) REFERENCES akce_typy (id_typu) ON UPDATE CASCADE,
    FOREIGN KEY (id_nahral) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_czech_ci
SQL,
);

$this->q(<<<'SQL'
CREATE TABLE IF NOT EXISTS turnaj_hodnoceni_kategorie
(
    id         INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    id_sablony INT UNSIGNED      NOT NULL,
    poradi     SMALLINT UNSIGNED NOT NULL,
    nazev      VARCHAR(255)      NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY (id_sablony, poradi),
    FOREIGN KEY (id_sablony) REFERENCES turnaj_hodnoceni_sablona (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_czech_ci
SQL,
);

$this->q(<<<'SQL'
CREATE TABLE IF NOT EXISTS turnaj_hodnoceni_kriterium
(
    id           INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    id_kategorie INT UNSIGNED      NOT NULL,
    poradi       SMALLINT UNSIGNED NOT NULL,
    nazev        VARCHAR(255)      NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY (id_kategorie, poradi),
    FOREIGN KEY (id_kategorie) REFERENCES turnaj_hodnoceni_kategorie (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_czech_ci
SQL,
);

// Jedno hodnocení na skupinu a kolo. Patří ke kolu, ne k aktivitě: aktivita je jen informace, na které skupina kolo
// hrála, a se smazanou aktivitou hodnocení zůstane.
$this->q(<<<'SQL'
CREATE TABLE IF NOT EXISTS turnaj_hodnoceni
(
    id             INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    id_sablony     INT UNSIGNED        NOT NULL,
    id_tymu        BIGINT(20) UNSIGNED NOT NULL,
    kolo           SMALLINT UNSIGNED   NOT NULL,
    id_akce        BIGINT(20) UNSIGNED          DEFAULT NULL,
    -- vypravěč, kterému hodnocení patří (i když ho za něj zadal org turnaje)
    id_hodnotitele BIGINT(20) UNSIGNED          DEFAULT NULL,
    -- kdo hodnocení naposledy zadal nebo změnil
    id_zadal       BIGINT(20) UNSIGNED          DEFAULT NULL,
    vytvoreno      DATETIME            NOT NULL,
    upraveno       DATETIME            NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY (id_sablony, id_tymu, kolo),
    KEY (id_tymu),
    KEY (id_akce),
    KEY (id_hodnotitele),
    KEY (id_zadal),
    FOREIGN KEY (id_sablony) REFERENCES turnaj_hodnoceni_sablona (id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_tymu) REFERENCES akce_tym (id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_akce) REFERENCES akce_seznam (id_akce) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (id_hodnotitele) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (id_zadal) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_czech_ci
SQL,
);

$this->q(<<<'SQL'
CREATE TABLE IF NOT EXISTS turnaj_hodnoceni_hodnota
(
    id_hodnoceni INT UNSIGNED NOT NULL,
    id_kriteria  INT UNSIGNED NOT NULL,
    hodnota      SMALLINT     NOT NULL,
    PRIMARY KEY (id_hodnoceni, id_kriteria),
    KEY (id_kriteria),
    FOREIGN KEY (id_hodnoceni) REFERENCES turnaj_hodnoceni (id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_kriteria) REFERENCES turnaj_hodnoceni_kriterium (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_czech_ci
SQL,
);

$idPrava = Pravo::ADMINISTRACE_HODNOCENI_TURNAJE;
$this->q(<<<SQL
INSERT INTO r_prava_soupis (id_prava, jmeno_prava, popis_prava)
VALUES ({$idPrava}, 'Administrace - panel Hodnocení turnaje',
        'Kritéria hodnocení turnaje D&D, výsledky skupin a export; hodnotit smí za kohokoli')
ON DUPLICATE KEY UPDATE
    jmeno_prava = VALUES(jmeno_prava),
    popis_prava = VALUES(popis_prava)
SQL,
);

// Trvalá role pro orga turnaje. Přidělit ji smí každý, kdo smí do Práv: dává jen tuhle stránku.
$idRole = Role::ORGANIZATOR_TURNAJE_DND;
$vyznam = Role::VYZNAM_ORGANIZATOR_TURNAJE_DND;
$kategorie = Role::kategoriePodleVyznamu($vyznam);
$this->q(<<<SQL
INSERT INTO role_seznam (id_role, kod_role, nazev_role, popis_role, rocnik_role, typ_role, vyznam_role, skryta,
                         kategorie_role)
VALUES ({$idRole}, '{$vyznam}', 'Organizátor turnaje D&D',
        'Nahrává kritéria hodnocení turnaje D&D, vidí výsledky skupin a export', -1, 'trvala', '{$vyznam}', 0,
        {$kategorie})
ON DUPLICATE KEY UPDATE
    kod_role = VALUES(kod_role),
    nazev_role = VALUES(nazev_role),
    popis_role = VALUES(popis_role),
    rocnik_role = VALUES(rocnik_role),
    typ_role = VALUES(typ_role),
    vyznam_role = VALUES(vyznam_role),
    skryta = VALUES(skryta),
    kategorie_role = VALUES(kategorie_role)
SQL,
);

$this->q(<<<SQL
INSERT INTO prava_role (id_role, id_prava)
VALUES ({$idRole}, {$idPrava})
ON DUPLICATE KEY UPDATE id_role = id_role
SQL,
);
