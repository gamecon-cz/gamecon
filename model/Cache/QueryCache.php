<?php

declare(strict_types=1);

namespace Gamecon\Cache;

class QueryCache
{
    private ?\EPDO $table = null;

    public function __construct(private readonly string $cacheDir)
    {
        if (!is_dir($cacheDir) && !@mkdir($cacheDir, 0777, true) && !is_dir($cacheDir)) {
            throw new \RuntimeException('Cache directory can not be created: ' . var_export($cacheDir, true));
        }
    }

    public function clear(): void
    {
        /* SQLite does not have TRUNCATE, but DELETE without WHERE is the same
        (it is a TRUNCATE optimizer for the DELETE statement) */
        $this->executeQuery('DELETE FROM query_cache');
    }

    /**
     * @return array|false
     */
    public function get(
        string $key,
        string $queryHash,
    ): false | array {
        $stmt = $this->executeQuery(
            'SELECT "value" FROM query_cache WHERE "key" = :KEY',
            ['KEY' => $key],
        );

        $encodedValues = $stmt->fetchColumn();
        if ($encodedValues === false) {
            return false;
        }

        return json_decode($encodedValues, true, 512, JSON_THROW_ON_ERROR);
    }

    public function set(
        string $key,
        string $queryHash,
        array  $values,
        ?array $dataVersions = null,
    ): void {
        $this->executeQuery(
            'INSERT OR REPLACE INTO query_cache("key", "query_hash", "value", "data_versions")
            VALUES (:KEY, :QUERY_HASH, :VALUE, :DATA_VERSIONS)',
            [
                'KEY'           => $key,
                'QUERY_HASH'    => $queryHash,
                'VALUE'         => json_encode($values),
                'DATA_VERSIONS' => json_encode($dataVersions),
            ],
        );
    }

    private function executeQuery(
        string $query,
        ?array  $params = null,
    ): \PDOStatement {
        $stmt = $this->getTableForCache()->prepare($query);
        if (!$stmt->execute($params)) {
            throw new \RuntimeException(
                'Failed to execute query: ' . var_export($stmt->errorInfo(), true),
            );
        }

        return $stmt;
    }

    private function getTableForCache(): \EPDO
    {
        if ($this->table) {
            return $this->table;
        }

        $sqlite = new \EPDO('sqlite:' . $this->cacheDir . '/query_cache.sqlite');

        $sqlite->query(<<<SQLITE
            CREATE TABLE IF NOT EXISTS query_cache(
                "key"   VARCHAR(255) NOT NULL PRIMARY KEY,
                "query_hash" VARCHAR(255) NOT NULL UNIQUE,
                "value" TEXT NULL,
                "data_versions" TEXT NULL
            )
            SQLITE,
        );

        $this->table = $sqlite;

        return $this->table;
    }
}
