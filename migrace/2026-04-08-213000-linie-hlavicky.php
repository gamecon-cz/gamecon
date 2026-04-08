<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
CREATE TABLE IF NOT EXISTS akce_typy_hlavicky
(
    id_typu int(10) unsigned NOT NULL,
    sekce   varchar(255)     DEFAULT NULL,
    jmeno   varchar(255)     DEFAULT NULL,
    email   varchar(255)     DEFAULT NULL,
    PRIMARY KEY (id_typu),
    CONSTRAINT FK_AKCE_TYPY_HLAVICKY_TYP FOREIGN KEY (id_typu) REFERENCES akce_typy (id_typu) ON UPDATE CASCADE ON DELETE CASCADE
)
SQL,
);

$legacyDir = ADRESAR_WEBU_S_OBRAZKY . '/soubory/systemove/linie-ikony';
$sourceDir = $legacyDir;

if (!is_dir($legacyDir)) {
    @mkdir($legacyDir, 0775, true);
}

if (is_dir($sourceDir)) {
    $idTypy = [];
    $result = $this->q('SELECT id_typu FROM akce_typy');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $idTypy[] = (int)$row['id_typu'];
        }
        $result->close();
    }

    foreach ($idTypy as $idTypu) {
        $file   = $sourceDir . '/' . $idTypu . '.txt';
        if (!is_file($file)) {
            continue;
        }
        $lines = file($file, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            continue;
        }
        $sekce = trim($lines[0] ?? '');
        $jmeno = trim($lines[1] ?? '');
        $email = trim($lines[2] ?? '');
        if ($sekce === '' && $jmeno === '' && $email === '') {
            continue;
        }
        $sekceEsc = dbQv($sekce);
        $jmenoEsc = dbQv($jmeno);
        $emailEsc = dbQv($email);
        $this->q(<<<SQL
INSERT INTO akce_typy_hlavicky (id_typu, sekce, jmeno, email)
VALUES ($idTypu, $sekceEsc, $jmenoEsc, $emailEsc)
ON DUPLICATE KEY UPDATE
    sekce = VALUES(sekce),
    jmeno = VALUES(jmeno),
    email = VALUES(email)
SQL,
        );

        $jpgSource = $sourceDir . '/org_' . $idTypu . '.jpg';
        $jpgTarget = $legacyDir . '/org_' . $idTypu . '.jpg';
        if (is_file($jpgSource) && (!is_file($jpgTarget) || filesize($jpgTarget) === 0)) {
            @copy($jpgSource, $jpgTarget);
        }
    }
}

// DnD turnaj: text nastavit natvrdo, fotku převzít z "akční hry a bonusy".
$dndRow = null;
$dndResult = $this->q(<<<SQL
SELECT id_typu FROM akce_typy WHERE url_typu_mn = 'dnd' LIMIT 1
SQL,
);
if ($dndResult) {
    $dndRow = $dndResult->fetch_assoc();
    $dndResult->close();
}

$bonusRow = null;
$bonusResult = $this->q(<<<SQL
SELECT id_typu FROM akce_typy WHERE url_typu_mn = 'bonusy' LIMIT 1
SQL,
);
if ($bonusResult) {
    $bonusRow = $bonusResult->fetch_assoc();
    $bonusResult->close();
}

if ($dndRow) {
    $dndId      = (int)$dndRow['id_typu'];
    $dndSekce   = dbQv('Šéf D&D/JaD turnaje');
    $dndJmeno   = dbQv('Lukáš „Bunny“ Králík');

    $this->q(<<<SQL
INSERT INTO akce_typy_hlavicky (id_typu, sekce, jmeno, email)
VALUES ($dndId, $dndSekce, $dndJmeno, NULL)
ON DUPLICATE KEY UPDATE
    sekce = VALUES(sekce),
    jmeno = VALUES(jmeno),
    email = akce_typy_hlavicky.email
SQL,
    );
}

if ($dndRow && $bonusRow) {
    $dndId   = (int)$dndRow['id_typu'];
    $bonusId = (int)$bonusRow['id_typu'];
    $bonusJpg = $legacyDir . '/org_' . $bonusId . '.jpg';
    $dndJpg   = $legacyDir . '/org_' . $dndId . '.jpg';
    if (is_file($bonusJpg) && (!is_file($dndJpg) || filesize($dndJpg) === 0)) {
        @copy($bonusJpg, $dndJpg);
    }
}
