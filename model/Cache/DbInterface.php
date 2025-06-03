<?php

namespace Gamecon\Cache;

interface DbInterface
{
    public function clearPrefetchedDataVersions(): void;

    /**
     * @param array<string> $relatedTables
     * @return array<array<string, mixed>>
     */
    public function dbFetchAll(
        array                 $relatedTables,
        string                $sql,
        ?DataSourcesCollector $dataSourcesCollector = null,
    ): array;
}
