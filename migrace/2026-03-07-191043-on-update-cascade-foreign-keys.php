<?php
/** @var \Godric\DbMigrations\Migration $this */

$dbName = $this->getCurrentDb();

// Add ON UPDATE CASCADE only to foreign keys referencing uzivatele_hodnoty.id_uzivatele.
// This is needed for data anonymization (AnonymizovanaDatabaze::exportuj),
// which updates id_uzivatele primary key values.
$result = $this->q(<<<SQL
    SELECT
        kcu.TABLE_NAME,
        kcu.CONSTRAINT_NAME,
        kcu.COLUMN_NAME,
        kcu.REFERENCED_TABLE_NAME,
        kcu.REFERENCED_COLUMN_NAME,
        rc.DELETE_RULE,
        rc.UPDATE_RULE
    FROM information_schema.KEY_COLUMN_USAGE kcu
    JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
        ON rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
        AND rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
    WHERE kcu.CONSTRAINT_SCHEMA = '{$dbName}'
        AND kcu.REFERENCED_TABLE_NAME = 'uzivatele_hodnoty'
        AND kcu.REFERENCED_COLUMN_NAME = 'id_uzivatele'
        AND rc.UPDATE_RULE != 'CASCADE'
    ORDER BY kcu.TABLE_NAME, kcu.CONSTRAINT_NAME
    SQL,
);

// Group by constraint name (for composite FKs)
$constraints = [];
while ($row = $result->fetch_assoc()) {
    $key = $row['TABLE_NAME'] . '.' . $row['CONSTRAINT_NAME'];
    if (!isset($constraints[$key])) {
        $constraints[$key] = [
            'table' => $row['TABLE_NAME'],
            'name' => $row['CONSTRAINT_NAME'],
            'columns' => [],
            'ref_table' => $row['REFERENCED_TABLE_NAME'],
            'ref_columns' => [],
            'delete_rule' => $row['DELETE_RULE'],
        ];
    }
    $constraints[$key]['columns'][] = $row['COLUMN_NAME'];
    $constraints[$key]['ref_columns'][] = $row['REFERENCED_COLUMN_NAME'];
}

foreach ($constraints as $fk) {
    $columns = implode(', ', $fk['columns']);
    $refColumns = implode(', ', $fk['ref_columns']);
    $onDelete = $fk['delete_rule'] !== 'RESTRICT' && $fk['delete_rule'] !== 'NO ACTION'
        ? " ON DELETE {$fk['delete_rule']}"
        : '';

    $this->q("ALTER TABLE `{$fk['table']}` DROP FOREIGN KEY `{$fk['name']}`");
    $this->q("ALTER TABLE `{$fk['table']}` ADD CONSTRAINT `{$fk['name']}` FOREIGN KEY ({$columns}) REFERENCES `{$fk['ref_table']}` ({$refColumns}) ON UPDATE CASCADE{$onDelete}");
}
