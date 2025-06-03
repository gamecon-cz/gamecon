<?php
/** @var \Godric\DbMigrations\Migration $this */

use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

$systemoveNastaveni ??= SystemoveNastaveni::zGlobals();
$systemoveNastaveni->queryCache()->clear();
$systemoveNastaveni->tableDataDependentCache()->clear();

$tableNamesResult = $this->q(<<<SQL
SHOW TABLES
SQL,
)->fetch_all();
$tableNames       = array_map(static fn(
    array $row,
) => reset($row), $tableNamesResult);

$tablesMetadata = [];
foreach ($tableNames as $tableName) {
    if (in_array($tableName, ['_table_data_versions', '_tables_used_in_view_data_versions'], true)) {
        continue;
    }
    $showCreateTable  = $this->q(<<<SQL
SHOW CREATE TABLE $tableName
SQL,
    )->fetch_assoc();
    $tablesUsedInView = [];
    $tableMetadata    = ['is_view' => false, 'view_tables' => []];
    if (!empty($showCreateTable['Create View'])) {
        // it is not a table, but a view
        $tableMetadata['view_tables'] = dbParseUsedTables($showCreateTable['Create View']);
        $tableMetadata['is_view']     = true;
    } else {
        assert(
            !empty($showCreateTable['Table']),
            sprintf(
                'Table %s does not exist, but is listed in SHOW TABLES. 
            This is a bug in the migration script.',
                $tableName,
            ),
        );
    }
    $tablesMetadata[$tableName] = $tableMetadata;
}

$getRealUsedTables = static function (
    string $viewName,
    array  $tablesMetadata,
    array  $checkedTables,
) use
(
    &
    $getRealUsedTables,
): array {
    assert($tablesMetadata[$viewName]['is_view']);
    if (in_array($viewName, $checkedTables, true)) {
        return [];
    }
    $checkedTables[] = $viewName;
    $usedTables      = [];
    foreach ($tablesMetadata[$viewName]['view_tables'] as $tableUsedInView) {
        if (in_array($tableUsedInView, $checkedTables, true)) {
            continue;
        }
        if ($tablesMetadata[$tableUsedInView]['is_view']) {
            $usedTables = [
                ...$usedTables,
                ...$getRealUsedTables($tableUsedInView, $tablesMetadata, $checkedTables),
            ];
        } else {
            $usedTables[] = $tableUsedInView;
        }
    }

    return $usedTables;
};

foreach ($tablesMetadata as $tableName => $tableMetadata) {
    $tableEscaped = $this->connection->real_escape_string($tableName);
    $this->q(<<<SQL
INSERT IGNORE INTO _table_data_versions (table_name, version)
VALUES ('{$tableEscaped}', 0)
SQL,
    );

    if ($tableMetadata['is_view']) {
        $tablesUsedInView = $getRealUsedTables(
            $tableName,
            $tablesMetadata,
            [],
        );
        $this->q(<<<SQL
START TRANSACTION 
SQL,
        );
        $this->q(<<<SQL
DELETE FROM _tables_used_in_view_data_versions
WHERE view_name = '{$tableEscaped}'
SQL,
        );
        foreach ($tablesUsedInView as $tableUsedInView) {
            $tableUsedInViewEscaped = $this->connection->real_escape_string($tableUsedInView);
            $this->q(<<<SQL
INSERT IGNORE INTO _tables_used_in_view_data_versions (view_name, table_used_in_view)
VALUES ('{$tableEscaped}', '{$tableUsedInViewEscaped}')
SQL,
            );
        }
        $this->q(<<<SQL
COMMIT
SQL,
        );

        // trigger can not react on view changes
        continue;
    }

    $this->q(<<<SQL
DROP TRIGGER IF EXISTS `{$tableEscaped}_insert`
SQL,
    );
    $this->q(<<<SQL
CREATE TRIGGER IF NOT EXISTS `{$tableEscaped}_insert`
AFTER INSERT ON `{$tableName}`
FOR EACH ROW
BEGIN
    INSERT INTO _table_data_versions (table_name, version)
    VALUES ('{$tableEscaped}', 0)
    ON DUPLICATE KEY UPDATE version = version + 1;
    
    INSERT INTO _table_data_versions (table_name, version)
    SELECT view_name, 0
    FROM _tables_used_in_view_data_versions
    WHERE table_used_in_view = '{$tableEscaped}'
    ON DUPLICATE KEY UPDATE version = version + 1;
END
SQL,
    );
    $this->q(<<<SQL
DROP TRIGGER IF EXISTS `{$tableEscaped}_update`
SQL,
    );
    $this->q(<<<SQL
CREATE TRIGGER IF NOT EXISTS `{$tableEscaped}_update`
AFTER UPDATE ON `{$tableName}` -- sadly AFTER UPDATE is triggered even if nothing changed
FOR EACH ROW
BEGIN
    INSERT INTO _table_data_versions (table_name, version)
    VALUES ('{$tableEscaped}', 0)
    ON DUPLICATE KEY UPDATE version = version + 1;
    
    INSERT INTO _table_data_versions (table_name, version)
    SELECT view_name, 0
    FROM _tables_used_in_view_data_versions
    WHERE table_used_in_view = '{$tableEscaped}'
    ON DUPLICATE KEY UPDATE version = version + 1;
END
SQL,
    );
    $this->q(<<<SQL
DROP TRIGGER IF EXISTS `{$tableEscaped}_delete`
SQL,
    );
    $this->q(<<<SQL
CREATE TRIGGER IF NOT EXISTS `{$tableEscaped}_delete`
AFTER DELETE ON `{$tableName}`
FOR EACH ROW
BEGIN
    INSERT INTO _table_data_versions (table_name, version)
    VALUES ('{$tableEscaped}', 0)
    ON DUPLICATE KEY UPDATE version = version + 1;
    
    INSERT INTO _table_data_versions (table_name, version)
    SELECT view_name, 0
    FROM _tables_used_in_view_data_versions
    WHERE table_used_in_view = '{$tableEscaped}'
    ON DUPLICATE KEY UPDATE version = version + 1;
END
SQL,
    );
}
