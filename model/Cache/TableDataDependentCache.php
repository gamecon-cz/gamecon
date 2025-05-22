<?php

declare(strict_types=1);

namespace Gamecon\Cache;

class TableDataDependentCache
{
    private ?array $tableDataVersions = null;

    public function __construct(
        private readonly string                      $cacheDir,
        private readonly TableDataVersionsRepository $tableDataVersionsRepository,
    ) {
    }

    /**
     * Sometimes we have to fetch all data versions before fetching cacheable data itself,
     * because after that we could fetch invalidly new,
     * by some other process changed version and that would cache old data under new version.
     */
    public function preloadTableDataVersions(): void
    {
        if ($this->tableDataVersions !== null) {
            return;
        }
        $this->tableDataVersions = $this->tableDataVersionsRepository->fetchTableDataVersions();
    }

    public function clear(): void
    {
        if (shell_exec('rm -rf ' . escapeshellarg($this->cacheDir)) === false) {
            throw new \RuntimeException("Failed to clear cache directory: {$this->cacheDir}");
        }
        if (!mkdir($this->cacheDir, 0777, true) && !is_dir($this->cacheDir)) {
            throw new \RuntimeException("Unable to create cache directory after clear: {$this->cacheDir}");
        }
        $this->tableDataVersions = null;
    }

    public function getItem(
        string $key,
    ): mixed {
        $encodedRawItem = $this->getEncodedRawItem($key);

        if ($encodedRawItem === null) {
            return null;
        }

        $rawItem = $this->decodeRawItem($encodedRawItem);

        if (!is_array($rawItem)) {
            return null;
        }

        return $this->decodeItem($rawItem);
    }

    private function decodeItem(array $rawItem): mixed
    {
        $cachedTableDataVersions = $rawItem['_metadata']['usedTableDataVersions'] ?? null;
        if (!is_array($cachedTableDataVersions)) {
            return null;
        } else {
            $allTableDataVersions = $this->getAllTableDataVersions();
            foreach ($cachedTableDataVersions as $tableName => $tableDataVersion) {
                if (($allTableDataVersions[$tableName] ?? null) !== $tableDataVersion) {
                    return null;
                }
            }
        }

        return $rawItem['_value'] ?? null;
    }

    private function getEncodedRawItem(string $key): ?string
    {
        $filePath = $this->getOriginalFilePath($key);
        if (!file_exists($filePath)) {
            return null;
        }
        $value = file_get_contents($filePath);
        if ($value === false) {
            throw new \RuntimeException("Failed to read cache file: {$filePath}");
        }

        return $value;
    }

    /**
     * @return array<array<string, int>>
     */
    private function getAllTableDataVersions(): array
    {
        $this->preloadTableDataVersions();

        return $this->tableDataVersions;
    }

    public function setItem(
        string               $key,
        mixed                $value,
        DataSourcesCollector $dataSourcesCollector,
    ): string {
        $filePath = $this->getOriginalFilePath($key);

        $dir = dirname($filePath);
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new \RuntimeException("Unable to create cache directory: {$dir}");
        }

        $item = $this->createItem($value, $dataSourcesCollector);

        $encodedItem = $this->encodeValue($item);
        if (file_put_contents($filePath, $encodedItem) === false) {
            throw new \RuntimeException("Failed to write cache file: {$filePath}");
        }

        return $filePath;
    }

    private function createItem(
        $value,
        DataSourcesCollector $dataSourcesCollector,
    ): array {
        $usedTables = $dataSourcesCollector->getDataSources();
        /**
         * @var array<array{
         *     table_name: string,
         *     version: int
         * }> $usedTableDataVersions
         */
        $usedTableDataVersions = array_filter(
            $this->getAllTableDataVersions(),
            fn(
                string $tableName,
            ) => in_array($tableName, $usedTables, true),
            ARRAY_FILTER_USE_KEY,
        );

        return [
            '_value'    => $value,
            '_metadata' => ['usedTableDataVersions' => $usedTableDataVersions],
        ];
    }

    private function getOriginalFilePath(string $key): string
    {
        $sanitizedKey = $this->sanitizeKey($key);

        return $this->cacheDir . '/original/' . $sanitizedKey;
    }

    private function sanitizeKey(string $key): string
    {
        if (strlen($key) > 127 || preg_match('~[^0-9a-zA-Z_-]~', $key)) {
            return md5($key);
        }

        return $key;
    }

    private function encodeValue($value): string
    {
        $encoded = json_encode($value);
        if ($encoded === false) {
            throw new \RuntimeException('Failed to encode value: ' . json_last_error_msg());
        }

        return $encoded;
    }

    private function decodeRawItem(string $encoded): mixed
    {
        return json_decode(json: $encoded, associative: true, flags: JSON_THROW_ON_ERROR);
    }
}
