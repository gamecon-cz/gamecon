<?php

/** @var \Godric\DbMigrations\Migration $this */

$dbName = $this->q("SELECT DATABASE()")->fetchColumn();

// Helper: check if a column exists
$columnExists = function (string $table, string $column) use ($dbName): bool {
    $result = $this->q(
        "SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '$dbName' AND TABLE_NAME = '$table' AND COLUMN_NAME = '$column'",
    );

    return (int) $result->fetch(\PDO::FETCH_ASSOC)['cnt'] > 0;
};

// Helper: check if an index exists
$indexExists = function (string $table, string $indexName) use ($dbName): bool {
    $result = $this->q(
        "SELECT COUNT(*) AS cnt FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = '$dbName' AND TABLE_NAME = '$table' AND INDEX_NAME = '$indexName'",
    );

    return (int) $result->fetch(\PDO::FETCH_ASSOC)['cnt'] > 0;
};

// Drop auto-increment and PK from akce_lokace.id_akce_lokace, then drop the column
if ($columnExists('akce_lokace', 'id_akce_lokace')) {
    $this->q("ALTER TABLE akce_lokace MODIFY id_akce_lokace BIGINT UNSIGNED NOT NULL");
    if ($indexExists('akce_lokace', 'id_akce_lokace')) {
        $this->q("DROP INDEX id_akce_lokace ON akce_lokace");
    }
    $this->q("ALTER TABLE akce_lokace DROP id_akce_lokace");
}

if ($columnExists('akce_lokace', 'je_hlavni')) {
    $this->q("ALTER TABLE akce_lokace DROP je_hlavni");
}

if ($indexExists('akce_lokace', 'fk_akce_lokace_lokace') && !$indexExists('akce_lokace', 'IDX_49CCDE68259B4755')) {
    $this->q("ALTER TABLE akce_lokace RENAME INDEX fk_akce_lokace_lokace TO IDX_49CCDE68259B4755");
}
