<?php
/** @var \Godric\DbMigrations\Migration $this */

use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

$systemoveNastaveni ??= SystemoveNastaveni::vytvorZGlobals();
$systemoveNastaveni->queryCache()->clear();

$tablesResult = $this->q(<<<SQL
SHOW TABLES
SQL,
)->fetch_all();
$tables       = array_map(static fn(
    array $row,
) => reset($row), $tablesResult);

foreach ($tables as $table) {
    if ($table === '_table_data_versions') {
        continue;
    }
    $createTable = $this->q(<<<SQL
SHOW CREATE TABLE $table
SQL,
    )->fetch_assoc();
    if (empty($createTable['Table'])) {
        // it is not a table, but a view
        continue;
    }
    $tableEscaped = $this->connection->real_escape_string($table);
    $this->q(<<<SQL
INSERT IGNORE INTO _table_data_versions (table_name, version)
VALUES ('{$tableEscaped}', 0)
SQL,
    );
    $this->q(<<<SQL
DROP TRIGGER IF EXISTS `{$tableEscaped}_insert`
SQL,
    );
    $this->q(<<<SQL
CREATE TRIGGER IF NOT EXISTS `{$tableEscaped}_insert`
AFTER INSERT ON `{$table}`
FOR EACH ROW
BEGIN
    INSERT INTO _table_data_versions (table_name, version)
    VALUES ('{$tableEscaped}', 0)
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
AFTER UPDATE ON `{$table}`
FOR EACH ROW
BEGIN
    INSERT INTO _table_data_versions (table_name, version)
    VALUES ('{$tableEscaped}', 0)
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
AFTER DELETE ON `{$table}`
FOR EACH ROW
BEGIN
    INSERT INTO _table_data_versions (table_name, version)
    VALUES ('{$tableEscaped}', 0)
    ON DUPLICATE KEY UPDATE version = version + 1;
END
SQL,
    );

}
