<?php

declare(strict_types=1);

namespace Gamecon\Cache;

class TableDataVersionsRepository
{
    /**
     * @return array<array<string, int>>
     */
    public function fetchTableDataVersions(): array
    {
        return dbFetchPairs(<<<SQL
SELECT table_name, version FROM `_table_data_versions`
SQL,
        );
    }
}
