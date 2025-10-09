<?php

namespace Godric\DbMigrations;

class Migration
{

    private \mysqli $connection;
    private string  $code;
    private string  $path;
    private bool    $endless = false;

    public function __construct(
        string  $path,
        string  $code,
        \mysqli $connection,
    ) {
        $this->path       = $path;
        $this->code       = removeDiacritics($code);
        $this->connection = $connection;
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

    public function getVersion(): int
    {
        return is_numeric($this->code)
            ? 1
            : 2;
    }

    /**
     * @param $query
     * @return false|\mysqli_result
     * @throws \Exception
     */
    public function q($query)
    {
        $this->connection->multi_query($query);

        $i = 0;
        do {
            $result = $this->connection->use_result();
            $i++;
        } while ($this->connection->more_results() && $this->connection->next_result());

        if ($this->connection->error) {
            $i++;
            throw new \Exception("Error in multi_query number $i: {$this->connection->error}");
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
        while ($constrain = mysqli_fetch_column($result)) {
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
            ? mysqli_fetch_column($result)
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
