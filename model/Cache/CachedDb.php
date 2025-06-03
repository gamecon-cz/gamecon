<?php

declare(strict_types=1);

namespace Gamecon\Cache;

class CachedDb implements DbInterface
{
    private array $tableDataVersions = [];

    public function __construct(
        private readonly QueryCache $queryCache,
    ) {
    }

    public function clearPrefetchedDataVersions(): void
    {
        $this->tableDataVersions = [];
    }

    /**
     * @param array<string> $relatedTables
     * @return array<array<string, mixed>>
     */
    public function dbFetchAll(
        array                 $relatedTables,
        string                $sql,
        ?DataSourcesCollector $dataSourcesCollector = null,
    ): array {
        $relatedTables = array_unique($relatedTables);

        $this->checkUsedTables($relatedTables, $sql);

        $dataSourcesCollector?->addDataSources($relatedTables);

        $tableDataVersions = $this->getTableDataVersions($relatedTables);
        $queryHash         = $this->getHash($sql);

        $cacheKey = $this->getCacheKey($tableDataVersions, $queryHash);

        $data = $this->queryCache->get($cacheKey);

        if ($data !== false) {
            return $data;
        }

        $data = dbFetchAll($sql);
        $this->queryCache->set($cacheKey, $queryHash, $data, $tableDataVersions);

        return $data;
    }

    private function getCacheKey(
        array  $tableDataVersions,
        string $queryHash,
    ): string {
        $tableDataVersionsString = json_encode($tableDataVersions)
            ?: throw new \RuntimeException(
                sprintf(
                    'Failed to encode tables data versions to JSON. '
                    . 'Tables data versions: %s',
                    var_export($tableDataVersions, true),
                ),
            );

        $tableDataVersionsHash = $this->getHash($tableDataVersionsString);

        return sprintf(
            '%s:%s',
            $tableDataVersionsHash,
            $queryHash,
        );
    }

    private function checkUsedTables(
        array  $relatedTables,
        string $sql,
    ): void {
        $usedTables = $this->parseUsedTables($sql);
        sort($usedTables);
        sort($relatedTables);
        if ($usedTables !== $relatedTables) {
            throw new \RuntimeException(
                sprintf(
                    'Used tables in SQL do not match the related tables. '
                    . 'Really used tables: %s, given related tables: %s',
                    var_export($usedTables, true),
                    var_export($relatedTables, true),
                ),
            );
        }
    }

    /**
     * @return array<string>
     */
    private function parseUsedTables(string $sql): array
    {
        return dbParseUsedTables($sql);
    }

    /**
     * @param array<string> $relatedTables
     * @return array<array<string, int>>
     */
    private function getTableDataVersions(
        array $relatedTables,
    ): array {
        $tableDataVersions = array_intersect_key($this->tableDataVersions, array_flip($relatedTables));
        if (count($tableDataVersions) === count($relatedTables)) {
            return $tableDataVersions;
        }

        $tableVersions = dbFetchPairs(<<<SQL
            SELECT table_name, version
            FROM _table_data_versions
            WHERE table_name IN ($0)
            SQL,
            [0 => $relatedTables],
        );
        if (count($tableVersions) !== count($relatedTables)) {
            throw new \RuntimeException(
                sprintf(
                    'Not all tables have a version in the cache. '
                    . 'Please check the _table_data_versions table. For tables: %s fetched versions: %s',
                    var_export($relatedTables, true),
                    var_export($tableVersions, true),
                ),
            );
        }

        foreach ($tableVersions as $tableName => $dataVersion) {
            $this->tableDataVersions[$tableName] = $dataVersion;
        }

        return $tableVersions;
    }

    private function getHash(string $sql): string
    {
        return md5($sql);
    }
}
