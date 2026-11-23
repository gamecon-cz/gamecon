<?php

declare(strict_types=1);

use Gamecon\Pravo;

/** @var Godric\DbMigrations\Migration $this */

// Text medailonku, který vypravěč napíše v Nastavení na webu, se na web dostane, až ho schválí organizátor,
// viz Gamecon\Uzivatel\MedailonekKeSchvaleni. Řádek zůstane i po schválení, vypravěč v Nastavení vidí „schváleno“.
$this->q(<<<'SQL'
CREATE TABLE IF NOT EXISTS medailonky_ke_schvaleni
(
    id_uzivatele BIGINT(20) UNSIGNED NOT NULL,
    -- NULL: text se proti zveřejněnému nemění
    o_sobe       LONGTEXT                     DEFAULT NULL,
    drd          LONGTEXT                     DEFAULT NULL,
    -- s mikrosekundami: schválení ověřuje, že vypravěč mezitím neposlal nový text, i ve stejné sekundě
    odeslano     DATETIME(6)         NOT NULL DEFAULT current_timestamp(6),
    -- NULL: čeká na schválení
    schvaleno    DATETIME                     DEFAULT NULL,
    id_schvalil  BIGINT(20) UNSIGNED          DEFAULT NULL,
    PRIMARY KEY (id_uzivatele),
    KEY (schvaleno),
    FOREIGN KEY (id_uzivatele) REFERENCES medailonky (id_uzivatele) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_schvalil) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_czech_ci
SQL,
);

$idPrava = Pravo::SCHVALOVANI_MEDAILONKU;
$this->q(<<<SQL
INSERT INTO r_prava_soupis (id_prava, jmeno_prava, popis_prava)
VALUES ({$idPrava}, 'Administrace - schvalování medailonků', 'Schvaluje texty medailonků, které vypravěči napsali v Nastavení na webu (Web → Schvalování medailonků, záložka Uživatel)')
ON DUPLICATE KEY UPDATE
    jmeno_prava = VALUES(jmeno_prava),
    popis_prava = VALUES(popis_prava)
SQL,
);

// Schválit smí kterýkoli org, tedy role s titulem organizátora, stejně jako u Binga. Další role ho dostanou v Právech.
$titulOrganizator = Pravo::TITUL_ORGANIZATOR;
$this->q(<<<SQL
INSERT IGNORE INTO prava_role (id_role, id_prava)
SELECT prava_role.id_role, {$idPrava}
FROM prava_role
WHERE prava_role.id_prava = {$titulOrganizator}
SQL,
);
