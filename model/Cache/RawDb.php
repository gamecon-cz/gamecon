<?php

declare(strict_types=1);

namespace Gamecon\Cache;

readonly class RawDb implements DbInterface
{
    /**
     * @param array<string> $relatedTables
     * @return array<array<string, mixed>>
     */
    public function dbFetchAll(
        array                 $relatedTables,
        string                $sql,
        ?DataSourcesCollector $dataSourcesCollector = null,
    ): array {
        return dbFetchAll($sql, $relatedTables, $dataSourcesCollector);
    }

}
