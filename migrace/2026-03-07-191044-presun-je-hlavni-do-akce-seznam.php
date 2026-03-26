<?php
/** @var \Godric\DbMigrations\Migration $this */

$dbName = DB_NAME;

// 1. Add id_hlavni_lokace column to akce_seznam (if not exists)
$columnExists = $this->q(<<<SQL
    SELECT COUNT(*) AS cnt
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = '{$dbName}'
        AND TABLE_NAME = 'akce_seznam'
        AND COLUMN_NAME = 'id_hlavni_lokace'
    SQL,
)->fetch(PDO::FETCH_ASSOC)['cnt'];

if ($columnExists == 0) {
    $this->q("ALTER TABLE akce_seznam ADD COLUMN id_hlavni_lokace BIGINT UNSIGNED DEFAULT NULL AFTER probehla_korekce");
    $this->q("ALTER TABLE akce_seznam ADD CONSTRAINT FK_2EE8EBF09E0F2899 FOREIGN KEY (id_hlavni_lokace) REFERENCES lokace (id_lokace) ON UPDATE CASCADE ON DELETE SET NULL");
    $this->q("CREATE INDEX IDX_2EE8EBF09E0F2899 ON akce_seznam (id_hlavni_lokace)");
}

// 2. Migrate data from akce_lokace.je_hlavni to akce_seznam.id_hlavni_lokace (if je_hlavni column exists)
$jeHlavniExists = $this->q(<<<SQL
    SELECT COUNT(*) AS cnt
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = '{$dbName}'
        AND TABLE_NAME = 'akce_lokace'
        AND COLUMN_NAME = 'je_hlavni'
    SQL,
)->fetch(PDO::FETCH_ASSOC)['cnt'];

if ($jeHlavniExists > 0) {
    $this->q(<<<SQL
        UPDATE akce_seznam
        SET id_hlavni_lokace = (
            SELECT akce_lokace.id_lokace
            FROM akce_lokace
            WHERE akce_lokace.id_akce = akce_seznam.id_akce
              AND akce_lokace.je_hlavni = 1
            LIMIT 1
        )
        WHERE id_hlavni_lokace IS NULL
          AND EXISTS (
            SELECT 1
            FROM akce_lokace
            WHERE akce_lokace.id_akce = akce_seznam.id_akce
              AND akce_lokace.je_hlavni = 1
        )
        SQL,
    );

    $this->q("ALTER TABLE akce_lokace DROP COLUMN je_hlavni");
}

// 3. Rename akce_lokace foreign keys to Doctrine naming convention
$fksResult = $this->q(<<<SQL
    SELECT kcu.CONSTRAINT_NAME, kcu.COLUMN_NAME, kcu.REFERENCED_TABLE_NAME, kcu.REFERENCED_COLUMN_NAME, rc.DELETE_RULE, rc.UPDATE_RULE
    FROM information_schema.KEY_COLUMN_USAGE kcu
    JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
        ON rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
        AND rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
    WHERE kcu.CONSTRAINT_SCHEMA = '{$dbName}'
        AND kcu.TABLE_NAME = 'akce_lokace'
        AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
    SQL,
);

$fkRows = [];
while ($row = $fksResult->fetch(PDO::FETCH_ASSOC)) {
    $fkRows[] = $row;
}
$fksResult->closeCursor();

$expectedNames = [
    'id_akce' => 'FK_49CCDE681E74DA0A',
    'id_lokace' => 'FK_49CCDE68259B4755',
];

foreach ($fkRows as $fk) {
    $column = $fk['COLUMN_NAME'];
    if (!isset($expectedNames[$column])) {
        continue;
    }
    $expectedName = $expectedNames[$column];
    if ($fk['CONSTRAINT_NAME'] === $expectedName) {
        continue; // already has the right name
    }

    $onDelete = $fk['DELETE_RULE'] !== 'RESTRICT' && $fk['DELETE_RULE'] !== 'NO ACTION'
        ? " ON DELETE {$fk['DELETE_RULE']}"
        : '';
    $onUpdate = $fk['UPDATE_RULE'] !== 'RESTRICT' && $fk['UPDATE_RULE'] !== 'NO ACTION'
        ? " ON UPDATE {$fk['UPDATE_RULE']}"
        : '';

    $this->q("ALTER TABLE akce_lokace DROP FOREIGN KEY `{$fk['CONSTRAINT_NAME']}`");
    $this->q("ALTER TABLE akce_lokace ADD CONSTRAINT `{$expectedName}` FOREIGN KEY ({$column}) REFERENCES `{$fk['REFERENCED_TABLE_NAME']}` ({$fk['REFERENCED_COLUMN_NAME']}){$onUpdate}{$onDelete}");
}

// 4. Rename index to Doctrine naming convention
$indexExists = $this->q(<<<SQL
    SELECT COUNT(*) AS cnt
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = '{$dbName}'
        AND TABLE_NAME = 'akce_lokace'
        AND INDEX_NAME = 'fk_akce_lokace_lokace'
    SQL,
)->fetch(PDO::FETCH_ASSOC)['cnt'];

if ($indexExists > 0) {
    $this->q("ALTER TABLE akce_lokace RENAME INDEX fk_akce_lokace_lokace TO IDX_49CCDE68259B4755");
}
