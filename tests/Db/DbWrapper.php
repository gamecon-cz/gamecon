<?php

namespace Gamecon\Tests\Db;

use Godric\DbMigrations\DbMigrations;
use Godric\DbMigrations\DbMigrationsConfig;

/**
 * Wrapper for calling database operations.
 *
 * Raw connection cannot be used, because tests need nested transactions
 * mechanism, which has to be the same mechanism as used for transactions in
 * actual implementations.
 */
class DbWrapper
{

    public function begin()
    {
        dbBegin();
    }

    public function escape($value): string
    {
        return dbQv($value);
    }

    function query(
        string $sql,
        array  $params = null,
    ) {
        return dbQuery($sql, $params);
    }

    public function commit()
    {
        dbCommit();
    }

    public function rollback()
    {
        dbRollback();
    }

    public function import(Dataset $dataset)
    {
        foreach ($dataset->getTables() as $table) {
            $tableName = $table->getName();
            $columnNames = implode(',', $table->getColumns());

            $sql = "INSERT INTO $tableName ($columnNames) VALUES";
            foreach ($table->getRows() as $row) {
                $escapedValues = array_map([$this, 'escape'], $row);
                $escapedValues = implode(',', $escapedValues);
                $sql .= "\n($escapedValues),";
            }
            $sql[strlen($sql) - 1] = ';';

            $this->query($sql);
        }
    }

    public function resetTestDb(): void
    {
        // příprava databáze
        $connection = dbConnectTemporary(false);
        dbQuery(sprintf('DROP DATABASE IF EXISTS `%s`', DB_NAME), [], $connection);
        dbQuery(sprintf('CREATE DATABASE IF NOT EXISTS `%s` COLLATE "utf8_czech_ci"', DB_NAME), [], $connection);
        dbQuery(sprintf('USE `%s`', DB_NAME), [], $connection);

        /*$testDumps = scandir(__DIR__ . '/data', SCANDIR_SORT_DESCENDING);
        assert($testDumps !== false, 'Nepodařilo se načíst testovací SQL');
        $latestDump = __DIR__ . '/data/' . reset($testDumps);

        // naimportujeme databázi s už proběhnutými staršími migracemi
        (new \MySQLImport($connection))->load($latestDump);*/

        (new DbMigrations(new DbMigrationsConfig(
            connection: $connection, // předpokládá se, že spojení pro testy má administrativní práva
            migrationsDirectory: SQL_MIGRACE_DIR,
            doBackups: false,
        )))->run();
    }
}
