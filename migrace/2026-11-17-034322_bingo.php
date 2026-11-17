<?php

declare(strict_types=1);

use Gamecon\Pravo;

/** @var Godric\DbMigrations\Migration $this */

// Bingo z jednání rady, viz Gamecon\Bingo\Bingo. Fráze jsou společné všem soutěžím, soutěž je jeden termín jednání.

$this->q(<<<'SQL'
CREATE TABLE IF NOT EXISTS bingo_fraze
(
    id        INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    text      VARCHAR(255)        NOT NULL,
    id_autora BIGINT(20) UNSIGNED          DEFAULT NULL,
    pridano   DATETIME            NOT NULL DEFAULT current_timestamp(),
    -- smazaná fráze zůstane na kartách, které ji už mají, jen se nedostane na nové
    smazano   DATETIME                     DEFAULT NULL,
    PRIMARY KEY (id),
    KEY (smazano),
    FOREIGN KEY (id_autora) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_czech_ci
SQL,
);

$this->q(<<<'SQL'
CREATE TABLE IF NOT EXISTS bingo_jednani
(
    id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    datum    DATE         NOT NULL,
    -- kdy soutěž někdo spustil, jinak začne sama v 19:00
    spusteno DATETIME              DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY (datum)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_czech_ci
SQL,
);

$this->q(<<<'SQL'
CREATE TABLE IF NOT EXISTS bingo_karta
(
    id           INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    id_jednani   INT UNSIGNED        NOT NULL,
    id_uzivatele BIGINT(20) UNSIGNED NOT NULL,
    vygenerovano DATETIME            NOT NULL DEFAULT current_timestamp(),
    -- karta vyhrála: čas binga, které soutěž ukončilo
    vyhra        DATETIME                     DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY (id_jednani, id_uzivatele),
    FOREIGN KEY (id_jednani) REFERENCES bingo_jednani (id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_czech_ci
SQL,
);

// pozice 0–24 po řádcích, prostřední 12 je volné pole a nemá řádek
$this->q(<<<'SQL'
CREATE TABLE IF NOT EXISTS bingo_karta_pole
(
    id_karty INT UNSIGNED     NOT NULL,
    pozice   TINYINT UNSIGNED NOT NULL,
    id_fraze INT UNSIGNED     NOT NULL,
    PRIMARY KEY (id_karty, pozice),
    UNIQUE KEY (id_karty, id_fraze),
    KEY (id_fraze),
    FOREIGN KEY (id_karty) REFERENCES bingo_karta (id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_fraze) REFERENCES bingo_fraze (id) ON UPDATE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_czech_ci
SQL,
);

// fráze, která na jednání zazněla, platí pro všechny karty toho jednání
$this->q(<<<'SQL'
CREATE TABLE IF NOT EXISTS bingo_zazneni
(
    id_jednani   INT UNSIGNED        NOT NULL,
    id_fraze     INT UNSIGNED        NOT NULL,
    id_uzivatele BIGINT(20) UNSIGNED          DEFAULT NULL,
    kdy          DATETIME            NOT NULL,
    -- odškrtnutí zrušené po bingu zůstane, aby šlo vrátit i s původním časem
    zruseno      DATETIME                     DEFAULT NULL,
    id_zrusil    BIGINT(20) UNSIGNED          DEFAULT NULL,
    PRIMARY KEY (id_jednani, id_fraze),
    KEY (id_fraze),
    FOREIGN KEY (id_jednani) REFERENCES bingo_jednani (id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_fraze) REFERENCES bingo_fraze (id) ON UPDATE CASCADE,
    FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (id_zrusil) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_czech_ci
SQL,
);

$idPrava = Pravo::ADMINISTRACE_BINGO;
$this->q(<<<SQL
INSERT INTO r_prava_soupis (id_prava, jmeno_prava, popis_prava)
VALUES ({$idPrava}, 'Administrace - panel Bingo', 'Bingo z jednání rady: fráze, karty a odškrtávání toho, co zaznělo')
ON DUPLICATE KEY UPDATE
    jmeno_prava = VALUES(jmeno_prava),
    popis_prava = VALUES(popis_prava)
SQL,
);

// Hrají orgové, tedy role s titulem organizátora. Všechny jsou trvalé, ročníkové role se tu neřeší.
$titulOrganizator = Pravo::TITUL_ORGANIZATOR;
$this->q(<<<SQL
INSERT IGNORE INTO prava_role (id_role, id_prava)
SELECT prava_role.id_role, {$idPrava}
FROM prava_role
WHERE prava_role.id_prava = {$titulOrganizator}
SQL,
);

// termíny jednání rady známé při zavedení Binga
$this->q(<<<'SQL'
INSERT IGNORE INTO bingo_jednani (datum)
VALUES ('2026-11-29'), ('2026-12-13')
SQL,
);
