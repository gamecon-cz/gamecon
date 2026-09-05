<?php

namespace Godric\DbMigrations;

class Migration
{

    private \PDO   $connection;
    private string $code;
    private string $path;
    private string $relativePath;
    private bool   $endless = false;

    public function __construct(
        string $path,
        string $code,
        \PDO   $connection,
        string $relativePath = '',
    ) {
        $this->path         = $path;
        $this->code         = removeDiacritics($code);
        $this->connection   = $connection;
        $this->relativePath = removeDiacritics($relativePath);
        // jen malý, neškodný hack, aby se migrace pouštěla pořád
        $this->setEndless(str_ends_with(basename($path, '.php'), 'endless'));
    }

    public function apply(): void
    {
        if (!file_exists($this->path)) {
            throw new \RuntimeException('Migration file does not exist: ' . $this->path);
        }
        if (!is_readable($this->path)) {
            throw new \RuntimeException('Migration file is not readable: ' . $this->path);
        }
        if (str_ends_with($this->path, '.php')) {
            require $this->path;

            return;
        }
        if (str_ends_with($this->path, '.sql')) {
            $query = file_get_contents($this->path);
            if ($query === false) {
                throw new \RuntimeException('Can not read DB migration file ' . $this->path);
            }
            try {
                $this->q($query);
            } catch (\Exception $exception) {
                throw new \RuntimeException('Error in DB migration file ' . $this->path . ': ' . $exception->getMessage(), previous: $exception);
            }

            return;
        }
        throw new \RuntimeException('Migration file type is not supported: ' . $this->path);
    }

    public function getHash(): string
    {
        $hash = sha1_file($this->path);
        if ($hash === false) {
            throw new \RuntimeException('Can not read DB migration file ' . $this->path);
        }

        return $hash;
    }

    public function getId(): ?int
    {
        return $this->getVersion() === 1
            ? (int)$this->getCode()
            : null;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getRelativePath(): string
    {
        return $this->relativePath;
    }

    public function getVersion(): int
    {
        $codeWithoutExtension = preg_replace('~\.(php|sql)$~', '', $this->code);

        return is_numeric($codeWithoutExtension)
            ? 1
            : 2;
    }

    /**
     * @param $query
     * @return false|\PDOStatement
     * @throws \Exception
     */
    public function q($query)
    {
        $this->connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // Check if this looks like a single SELECT/SHOW query (returns result set)
        $trimmed = ltrim($query);
        if (preg_match('/^(SELECT|SHOW|DESCRIBE|EXPLAIN)\b/i', $trimmed)) {
            return $this->connection->query($query);
        }

        // For DDL/DML multi-statement SQL, use query() with nextRowset() to consume
        // all result sets. \PDO::MYSQL_ATTR_MULTI_STATEMENTS must be enabled on the connection.
        $result = $this->connection->query($query);

        if ($result instanceof \PDOStatement) {
            // Advance through all result sets from multi-statement queries.
            // nextRowset() returns false when there are no more results.
            // It may also return false on error — we ignore that to match
            // the old mysqli::multi_query() behavior which continued past errors.
            try {
                while ($result->nextRowset()) {
                    // consume
                }
            } catch (\PDOException) {
                // Some result sets may fail (e.g. IF NOT EXISTS checks),
                // continue like mysqli::multi_query() did
            }
        }

        return $result;
    }

    public function dropForeignKeysIfExist(
        array  $foreignKeysToDrop,
        string $tableName,
    ): void {
        $db          = $this->getCurrentDb();
        $result      = $this->q(<<<SQL
SELECT
    CONSTRAINT_NAME
FROM
    information_schema.KEY_COLUMN_USAGE
WHERE
	TABLE_SCHEMA = '$db'
    AND TABLE_NAME = '$tableName';
SQL,
        );
        $constraints = [];
        while ($constrain = $result->fetchColumn()) {
            $constraints[] = $constrain;
        }
        $existingForeignKeysToDrop = array_intersect(
            $foreignKeysToDrop,
            $constraints,
        );

        $foreignKeysToDropSqlParts = array_map(static function (
            string $foreignKeyToDrop,
        ) {
            return "DROP FOREIGN KEY `$foreignKeyToDrop`";
        }, $existingForeignKeysToDrop);

        if ($foreignKeysToDropSqlParts) {
            $foreignKeysToDropSql = implode("\n,", $foreignKeysToDropSqlParts);
            $this->q(<<<SQL
ALTER TABLE `$tableName`
    $foreignKeysToDropSql
SQL,
            );
        }
    }

    private function getCurrentDb(): string
    {
        $result = $this->q(<<<SQL
SELECT DATABASE()
SQL,
        );
        $db     = $result !== false
            ? $result->fetchColumn()
            : null;
        if ((string)$db === '') {
            throw new \RuntimeException('Can not determine current DB as no DB is selected');
        }

        return $db;
    }

    private function setEndless(bool $endless = true): void
    {
        $this->endless = $endless;
    }

    /**
     * Should run again and again and again...
     */
    public function isEndless(): bool
    {
        return $this->endless;
    }

}
