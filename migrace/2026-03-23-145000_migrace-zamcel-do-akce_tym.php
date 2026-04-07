<?php

declare(strict_types=1);

/*
 * Pomocné selekty pro vizuální prohlídnutí dat (lze spustit nad produkcí před/po migraci)
 *
 * === SELECT 1: Přehled týmů (kořenové aktivity bez rodiče) ===
 *
 * SELECT
 *     prvni_prihlaseny.id_uzivatele AS id_kapitana,
 *     akce_seznam.id_akce AS id_aktivity,
 *     akce_seznam.team_nazev,
 *     akce_seznam.team_limit,
 *     (SELECT COUNT(*) FROM akce_prihlaseni
 *      WHERE akce_prihlaseni.id_akce = akce_seznam.id_akce) AS pocet_clenu
 * FROM akce_seznam
 * JOIN akce_prihlaseni AS prvni_prihlaseny
 *     ON prvni_prihlaseny.id_akce = akce_seznam.id_akce
 *     AND prvni_prihlaseny.id = (
 *         SELECT MIN(ap.id) FROM akce_prihlaseni AS ap
 *         WHERE ap.id_akce = akce_seznam.id_akce
 *     )
 * WHERE akce_seznam.teamova = 1
 *   AND NOT EXISTS (
 *       SELECT 1 FROM akce_seznam AS rodic
 *       WHERE rodic.dite RLIKE CONCAT('(^|,)', akce_seznam.id_akce, '(,|$)')
 *   )
 * ORDER BY akce_seznam.id_akce;
 *
 *
 * === SELECT 2: Členové týmů (kapitán + hráči, pro přihlašování do nového systému) ===
 *
 * SELECT
 *     prvni_prihlaseny.id_uzivatele AS id_kapitana,
 *     akce_seznam.id_akce AS id_aktivity,
 *     akce_prihlaseni.id_uzivatele AS id_uzivatele
 * FROM akce_seznam
 * JOIN akce_prihlaseni ON akce_prihlaseni.id_akce = akce_seznam.id_akce
 * JOIN akce_prihlaseni AS prvni_prihlaseny
 *     ON prvni_prihlaseny.id_akce = akce_seznam.id_akce
 *     AND prvni_prihlaseny.id = (
 *         SELECT MIN(ap.id) FROM akce_prihlaseni AS ap
 *         WHERE ap.id_akce = akce_seznam.id_akce
 *     )
 * WHERE akce_seznam.teamova = 1
 *   AND NOT EXISTS (
 *       SELECT 1 FROM akce_seznam AS rodic
 *       WHERE rodic.dite RLIKE CONCAT('(^|,)', akce_seznam.id_akce, '(,|$)')
 *   )
 * ORDER BY akce_seznam.id_akce, akce_prihlaseni.id;
 *
 *
 * === SELECT 3: Kde všude byl tým (kořenová aktivita + všechny dětské aktivity navštívené týmem) ===
 *
 * SELECT DISTINCT
 *     tymy.id_kapitana,
 *     tymy.id_aktivity AS id_rodicovske_aktivity,
 *     dite_akce.id_akce AS id_dite_aktivity
 * FROM (
 *     SELECT
 *         prvni_prihlaseny.id_uzivatele AS id_kapitana,
 *         akce_seznam.id_akce AS id_aktivity,
 *         akce_seznam.dite
 *     FROM akce_seznam
 *     JOIN akce_prihlaseni AS prvni_prihlaseny
 *         ON prvni_prihlaseny.id_akce = akce_seznam.id_akce
 *         AND prvni_prihlaseny.id = (
 *             SELECT MIN(ap.id) FROM akce_prihlaseni AS ap
 *             WHERE ap.id_akce = akce_seznam.id_akce
 *         )
 *     WHERE akce_seznam.teamova = 1
 *       AND akce_seznam.dite IS NOT NULL
 *       AND NOT EXISTS (
 *           SELECT 1 FROM akce_seznam AS rodic
 *           WHERE rodic.dite RLIKE CONCAT('(^|,)', akce_seznam.id_akce, '(,|$)')
 *       )
 * ) AS tymy
 * JOIN akce_seznam AS dite_akce
 *     ON dite_akce.id_akce IN (
 *         SELECT TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(tymy.dite, ',', cisla.n), ',', -1)) AS dite_id
 *         FROM (
 *             SELECT 1 AS n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5
 *             UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10
 *         ) AS cisla
 *         WHERE cisla.n <= 1 + CHAR_LENGTH(tymy.dite) - CHAR_LENGTH(REPLACE(tymy.dite, ',', ''))
 *     )
 * JOIN akce_prihlaseni AS tym_clen
 *     ON tym_clen.id_akce = dite_akce.id_akce
 * JOIN akce_prihlaseni AS rodic_prihlaseny
 *     ON rodic_prihlaseny.id_akce = tymy.id_aktivity
 *     AND rodic_prihlaseny.id_uzivatele = tym_clen.id_uzivatele
 * ORDER BY tymy.id_kapitana, tymy.id_aktivity, dite_akce.id_akce;
 */


$this->q(<<<SQL
CREATE TABLE `akce_tym_akce` (
  `id_tymu` bigint(20) UNSIGNED NOT NULL,
  `id_akce` bigint(20) UNSIGNED NOT NULL,
  PRIMARY KEY (`id_tymu`, `id_akce`),
  KEY (`id_akce`),
  FOREIGN KEY (`id_tymu`) REFERENCES `akce_tym` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`id_akce`) REFERENCES `akce_seznam` (`id_akce`) ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb3
  COLLATE=utf8mb3_czech_ci
SQL,
);

// Pomocná funkce: rekurzivně vrátí všechna id dětí a jejich potomků pro danou aktivitu
$nactiVsechnyPotomky = function (int $idAkce) use (&$nactiVsechnyPotomky): array {
    $result  = $this->q(<<<SQL
        SELECT dite FROM akce_seznam WHERE id_akce = {$idAkce}
    SQL);
    $row     = $result->fetch_assoc();
    $result->free();
    $diteIds = array_filter(array_map('intval', explode(',', (string)($row['dite'] ?? ''))));
    $vsichniPotomci = [];
    foreach ($diteIds as $idDitete) {
        $vsichniPotomci[] = $idDitete;
        foreach ($nactiVsechnyPotomky($idDitete) as $potomek) {
            $vsichniPotomci[] = $potomek;
        }
    }
    return $vsichniPotomci;
};

// Načteme kořenové týmové aktivity (nemají rodiče = první aktivita turnaje) do PHP pole,
// aby se result uvolnil před následujícími INSERT dotazy uvnitř smyčky
$resultTymy = $this->q(<<<SQL
    SELECT
        akce_seznam.id_akce,
        akce_seznam.team_nazev,
        akce_seznam.team_limit,
        prvni_prihlaseny.id_uzivatele AS id_kapitana,
        COALESCE(
            (SELECT MIN(akce_prihlaseni_log.kdy)
             FROM akce_prihlaseni_log
             WHERE akce_prihlaseni_log.id_akce = akce_seznam.id_akce),
             -- kvuli automatizaci vratime nejake nesmyslne datum v minulosti
            '2019-01-01 00:00:00'
        ) AS zalozen
    FROM akce_seznam
    JOIN akce_prihlaseni AS prvni_prihlaseny
        ON prvni_prihlaseny.id_akce = akce_seznam.id_akce
        AND prvni_prihlaseny.id = (
            SELECT MIN(ap.id) FROM akce_prihlaseni AS ap
            WHERE ap.id_akce = akce_seznam.id_akce
        )
    WHERE akce_seznam.teamova = 1
      AND NOT EXISTS (
          SELECT 1 FROM akce_seznam AS rodic
          WHERE rodic.dite RLIKE CONCAT('(^|,)', akce_seznam.id_akce, '(,|$)')
      )
SQL);
$tymy = [];
while ($row = $resultTymy->fetch_assoc()) {
    $tymy[] = $row;
}
$resultTymy->free();

foreach ($tymy as $tym) {
    $idAktivity = (int)$tym['id_akce'];
    $idKapitana = (int)$tym['id_kapitana'];
    $nazev      = $this->connection->real_escape_string((string)($tym['team_nazev'] ?? ''));
    $limit      = $tym['team_limit'] !== null ? (int)$tym['team_limit'] : 'NULL';
    $zalozen    = $this->connection->real_escape_string($tym['zalozen']);

    // Vytvoříme tým
    $this->q(<<<SQL
        INSERT INTO akce_tym (kod, id_kapitan, zalozen, nazev, `limit`)
        VALUES (FLOOR(1000 + RAND() * 9000), {$idKapitana}, '{$zalozen}', '{$nazev}', {$limit})
    SQL);
    $idTymu = $this->connection->insert_id;

    // Přihlásíme všechny členy týmu (přihlášené na kořenové aktivitě)
    $this->q(<<<SQL
        INSERT IGNORE INTO akce_tym_prihlaseni (id_uzivatele, id_tymu)
        SELECT akce_prihlaseni.id_uzivatele, {$idTymu}
        FROM akce_prihlaseni
        WHERE akce_prihlaseni.id_akce = {$idAktivity}
    SQL);

    // Zaznamenáme kořenovou aktivitu týmu
    $this->q(<<<SQL
        INSERT IGNORE INTO akce_tym_akce (id_tymu, id_akce)
        VALUES ({$idTymu}, {$idAktivity})
    SQL);

    // Rekurzivně zaznamenáme všechny potomky kde byl přihlášen alespoň jeden člen týmu
    foreach ($nactiVsechnyPotomky($idAktivity) as $idPotomka) {
        $this->q(<<<SQL
            INSERT IGNORE INTO akce_tym_akce (id_tymu, id_akce)
            SELECT {$idTymu}, {$idPotomka}
            FROM akce_prihlaseni
            WHERE akce_prihlaseni.id_akce = {$idPotomka}
              AND akce_prihlaseni.id_uzivatele IN (
                  SELECT akce_tym_prihlaseni.id_uzivatele
                  FROM akce_tym_prihlaseni
                  WHERE akce_tym_prihlaseni.id_tymu = {$idTymu}
              )
            LIMIT 1
        SQL);
    }
}

// Smazeme trigger na team_limit
$this->q(<<<SQL
    DROP TRIGGER IF EXISTS `trigger_check_and_apply_team_limit`
SQL,
);

// Smazeme všechny foreign key constrainty na sloupci zamcel
$dbName = $this->getCurrentDb();
$result = $this->q(<<<SQL
    SELECT kcu.CONSTRAINT_NAME
    FROM information_schema.KEY_COLUMN_USAGE kcu
    WHERE kcu.CONSTRAINT_SCHEMA = '{$dbName}'
      AND kcu.TABLE_NAME = 'akce_seznam'
      AND kcu.COLUMN_NAME = 'zamcel'
      AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
SQL,
);
$fkNames = [];
while ($row = $result->fetch_assoc()) {
    $fkNames[] = $row['CONSTRAINT_NAME'];
}
foreach ($fkNames as $fkName) {
    $this->q("ALTER TABLE akce_seznam DROP FOREIGN KEY `$fkName`");
}

// Smazeme staré sloupce
$this->q(<<<SQL
    ALTER TABLE akce_seznam
    DROP COLUMN zamcel,
    DROP COLUMN zamcel_cas,
    DROP COLUMN team_nazev,
    DROP COLUMN team_limit
SQL,
);

// Aktualizujeme komentář u team_kapacita
$this->q(<<<SQL
    ALTER TABLE akce_seznam
    MODIFY COLUMN team_kapacita int(11) DEFAULT NULL COMMENT 'max. počet týmů na aktivitě'
SQL,
);
