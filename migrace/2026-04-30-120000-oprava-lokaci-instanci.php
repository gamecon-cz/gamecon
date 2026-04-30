<?php
/** @var \Godric\DbMigrations\Migration $this */

$columnExists = function (string $tableName, string $columnName): bool {
    $result = $this->q(<<<SQL
        SELECT COUNT(*) AS cnt
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = '{$tableName}'
          AND COLUMN_NAME = '{$columnName}'
        SQL,
    );
    if (!$result instanceof mysqli_result) {
        return false;
    }
    $row = $result->fetch_assoc();
    if (!is_array($row) || !array_key_exists('cnt', $row)) {
        return false;
    }

    return (int)$row['cnt'] > 0;
};

$idHlavniLokaceExists = $columnExists('akce_seznam', 'id_hlavni_lokace');
$akceLokaceIdAkceExists = $columnExists('akce_lokace', 'id_akce');
$akceLokaceIdLokaceExists = $columnExists('akce_lokace', 'id_lokace');

if (!$idHlavniLokaceExists || !$akceLokaceIdAkceExists || !$akceLokaceIdLokaceExists) {
    return;
}

$this->q(<<<SQL
    INSERT IGNORE INTO akce_lokace (id_akce, id_lokace)
    SELECT akce_seznam.id_akce, akce_seznam.id_hlavni_lokace
    FROM akce_seznam
    LEFT JOIN akce_lokace
      ON akce_lokace.id_akce = akce_seznam.id_akce
     AND akce_lokace.id_lokace = akce_seznam.id_hlavni_lokace
    WHERE akce_seznam.id_hlavni_lokace IS NOT NULL
      AND akce_lokace.id_akce IS NULL
    SQL,
);
