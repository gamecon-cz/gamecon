<?php

declare(strict_types=1);

namespace Gamecon\Cache;

readonly class QueryCache
{
    public function __construct(private string $cacheDir)
    {
        if (!is_dir($this->cacheDir)) {
            throw new \RuntimeException('Cache directory does not exist');
        }
    }

    public function flush(): void
    {
        $this->getTableForCache()->query(<<<SQL
            DELETE FROM query_cache
            SQL,
        );
    }

    /**
     * @return array|false
     */
    public function get(
        string $key,
        string $queryHash,
    ): false | array {
        $table = $this->getTableForCache();
        $stmt  = $table->prepare(<<<SQLITE
            SELECT "value" FROM query_cache WHERE "key" = :KEY
            SQLITE,
            [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ],
        );
        if (!$stmt->execute(['KEY' => $key])) {
            throw new \RuntimeException('Failed to execute query');
        }

        $encodedValues = $stmt->fetchColumn();
        if ($encodedValues === false) {
            // remove invalid cache of the same query (but different query-and-data-version key)
            $table->prepare(<<<SQLITE
                DELETE FROM query_cache WHERE "query_hash" = :QUERY_HASH
            SQLITE,
            )->execute(['QUERY_HASH' => $queryHash]);

            return false;
        }

        return json_decode($encodedValues, true, 512, JSON_THROW_ON_ERROR);
    }

    private function getTableForCache(): \EPDO
    {
        $sqlite = new \EPDO('sqlite:' . $this->cacheDir . '/query_cache.sqlite');

        $sqlite->query(<<<SQLITE
            CREATE TABLE IF NOT EXISTS query_cache(
                "key"   VARCHAR(255) NOT NULL PRIMARY KEY,
                "query_hash" VARCHAR(255) NOT NULL,
                "value" TEXT NULL,
                "data_versions" TEXT NULL
            )
            SQLITE,
        );

        $sqlite->query(<<<SQLITE
            CREATE INDEX IF NOT EXISTS idx_query_hash ON query_cache("query_hash")
            SQLITE,
        );

        return $sqlite;
    }

    public function set(
        string $key,
        string $queryHash,
        array  $values,
        ?array $dataVersions = null,
    ): void {
        $stmt = $this->getTableForCache()->prepare(<<<SQLITE
            INSERT INTO query_cache("key", "query_hash", "value", "data_versions")
            VALUES (:KEY, :QUERY_HASH, :VALUE, :DATA_VERSIONS)
            ON CONFLICT("key") DO UPDATE SET "value" = :VALUE
            SQLITE,
        );
        if (!$stmt->execute(['KEY' => $key, 'QUERY_HASH' => $queryHash, 'VALUE' => json_encode($values), 'DATA_VERSIONS' => json_encode($dataVersions)])) {
            throw new \RuntimeException('Failed to execute query');
        }
    }
}
