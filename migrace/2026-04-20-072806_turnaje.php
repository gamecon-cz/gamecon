<?php
/** @var \Godric\DbMigrations\Migration $this */

// Vytvoříme tabulku turnajů
$this->q(<<<SQL
CREATE TABLE `turnaje` (
  `id_turnaje` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nazev`      varchar(255) COLLATE utf8_czech_ci NOT NULL,
  `rok`        smallint(4) UNSIGNED NOT NULL,
  PRIMARY KEY (`id_turnaje`),
  KEY (`rok`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb3
  COLLATE=utf8mb3_czech_ci
SQL,
);

// Přidáme sloupce na akce_seznam
$this->q(<<<SQL
ALTER TABLE `akce_seznam`
  ADD COLUMN `id_turnaje`  int(10) UNSIGNED DEFAULT NULL
      COMMENT 'FK do tabulky turnaje; NULL = aktivita není součástí turnaje'
      AFTER `dite`,
  ADD COLUMN `turnaj_kolo` tinyint(3) UNSIGNED DEFAULT NULL
      COMMENT 'číslo kola v turnaji (1 = první kolo); NULL pokud id_turnaje IS NULL'
      AFTER `id_turnaje`,
  ADD CONSTRAINT `fk_akce_seznam_turnaj`
      FOREIGN KEY (`id_turnaje`) REFERENCES `turnaje` (`id_turnaje`)
      ON DELETE SET NULL ON UPDATE CASCADE
SQL,
);

// Naplníme tabulku turnajů z existujících dite vazeb

$aktivitySDitetem = $this->q(<<<SQL
SELECT *
FROM akce_seznam
WHERE dite IS NOT NULL
  AND dite != ''
SQL,
)->fetch_all(MYSQLI_ASSOC);

// Sestavíme graf rodič -> děti a dítě -> rodiče
$detiPodleRodice   = []; // int ID -> int[] IDs dětí
$rodicePodleDitete = []; // int ID -> int[] IDs rodičů
$vsechnaId         = [];
$nazevPodleId      = []; // int ID -> string nazev_akce
$rokPodleId        = []; // int ID -> int rok

foreach ($aktivitySDitetem as $aktivita) {
    $rodicId                 = (int)$aktivita['id_akce'];
    $vsechnaId[$rodicId]     = true;
    $nazevPodleId[$rodicId]  = $aktivita['nazev_akce'];
    $rokPodleId[$rodicId]    = (int)$aktivita['rok'];
    $detiIds                 = array_map('intval', array_filter(array_map('trim', explode(',', $aktivita['dite']))));
    foreach ($detiIds as $diteId) {
        $vsechnaId[$diteId]            = true;
        $detiPodleRodice[$rodicId][]   = $diteId;
        $rodicePodleDitete[$diteId][]  = $rodicId;
    }
}

// Doplníme název a rok i pro aktivity které jsou jen dětmi (nejsou v $aktivitySDitetem)
$chybejiciIds = array_diff(array_keys($vsechnaId), array_keys($nazevPodleId));
if ($chybejiciIds) {
    $chybejiciIdsCsv = implode(',', $chybejiciIds);
    $chybejiciAktivity = $this->q(<<<SQL
SELECT id_akce, nazev_akce, rok
FROM akce_seznam
WHERE id_akce IN ($chybejiciIdsCsv)
SQL,
    )->fetch_all(MYSQLI_ASSOC);
    foreach ($chybejiciAktivity as $aktivita) {
        $id                  = (int)$aktivita['id_akce'];
        $nazevPodleId[$id]   = $aktivita['nazev_akce'];
        $rokPodleId[$id]     = (int)$aktivita['rok'];
    }
}

// Rozdělíme aktivity do turnajů (spojených komponent přes relaci dítě)
$turnaje          = []; // int tempTurnajId -> int[] aktivitaIds
$aktivitaNaTurnaj = []; // int aktivitaId -> int tempTurnajId

$tempTurnajId = 0;
foreach (array_keys($vsechnaId) as $startId) {
    if (isset($aktivitaNaTurnaj[$startId])) {
        continue;
    }
    $fronta     = [$startId];
    $navstivene = [];
    while ($fronta) {
        $aktualniId = array_shift($fronta);
        if (isset($navstivene[$aktualniId])) {
            continue;
        }
        $navstivene[$aktualniId]         = true;
        $aktivitaNaTurnaj[$aktualniId]   = $tempTurnajId;
        $turnaje[$tempTurnajId][]        = $aktualniId;
        foreach ($detiPodleRodice[$aktualniId] ?? [] as $diteId) {
            if (!isset($navstivene[$diteId])) {
                $fronta[] = $diteId;
            }
        }
        foreach ($rodicePodleDitete[$aktualniId] ?? [] as $rodicId) {
            if (!isset($navstivene[$rodicId])) {
                $fronta[] = $rodicId;
            }
        }
    }
    $tempTurnajId++;
}

// Pro každý turnaj přiřadíme kola a vložíme záznam do tabulky turnaje
foreach ($turnaje as $idTempTurnaje => $aktivityVTurnaji) {
    $koreny = array_values(array_filter($aktivityVTurnaji, static fn(int $id) => empty($rodicePodleDitete[$id])));
    if (!$koreny) {
        throw new \RuntimeException(
            "Turnaj $idTempTurnaje (aktivity: " . implode(', ', $aktivityVTurnaji) . ') nemá žádný kořen — graf obsahuje cyklus.',
        );
    }

    // BFS přiřazení kol (1-based)
    $kola   = []; // int aktivitaId -> int kolo (1-based)
    $fronta = [];
    foreach ($koreny as $korenId) {
        $kola[$korenId] = 1;
        $fronta[]       = $korenId;
    }
    while ($fronta) {
        $aktualniId   = array_shift($fronta);
        $aktualniKolo = $kola[$aktualniId];
        foreach ($detiPodleRodice[$aktualniId] ?? [] as $diteId) {
            $ocekavaneKolo = $aktualniKolo + 1;
            if (isset($kola[$diteId])) {
                continue; // již přiřazeno (nejednoznačnost ignorujeme pro migraci dat)
            }
            $kola[$diteId] = $ocekavaneKolo;
            $fronta[]      = $diteId;
        }
    }

    // Název turnaje = nazev_akce prvního kořene, rok = rok prvního kořene
    $korenProNazev    = $koreny[0];
    $nazevTurnaje     = $this->connection->real_escape_string($nazevPodleId[$korenProNazev] ?? '');
    $rokTurnaje       = (int)($rokPodleId[$korenProNazev] ?? 0);

    $this->q(<<<SQL
INSERT INTO `turnaje` (`nazev`, `rok`)
VALUES ('{$nazevTurnaje}', {$rokTurnaje})
SQL,
    );

    $dbIdTurnaje = (int)$this->connection->insert_id;

    // UPDATE aktivit v tomto turnaji
    foreach ($aktivityVTurnaji as $aktivitaId) {
        $kolo = $kola[$aktivitaId] ?? 1;
        $this->q(<<<SQL
UPDATE `akce_seznam`
SET `id_turnaje`  = {$dbIdTurnaje},
    `turnaj_kolo` = {$kolo}
WHERE `id_akce` = {$aktivitaId}
SQL,
        );
    }
}
