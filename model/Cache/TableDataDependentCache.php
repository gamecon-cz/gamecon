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
    ): ?ItemWithMetadata {
        $encodedRawItem = $this->getEncodedRawItem($key);

        if ($encodedRawItem === null) {
            return null;
        }

        $array = json_decode(json: $encodedRawItem, associative: true, flags: JSON_THROW_ON_ERROR);
        $rawItem = ItemWithMetadata::array_decode($array);
        return $this->checkItem($rawItem);
    }

    private function checkItem(ItemWithMetadata $rawItem): ?ItemWithMetadata
    {
        $cachedTableDataVersions = $rawItem->usedTableDataVersions;
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

        return $rawItem;
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
    ): ItemWithMetadata {
        $filePath = $this->getOriginalFilePath($key);

        $dir = dirname($filePath);
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new \RuntimeException("Unable to create cache directory: {$dir}");
        }

        $item = $this->createItem($key, $value, $dataSourcesCollector);

        $encodedItem = json_encode($item->array_encode());
        if (file_put_contents($filePath, $encodedItem) === false) {
            throw new \RuntimeException("Failed to write cache file: {$filePath}");
        }

        return $item;
    }

    private function createItem(
        $key,
        $value,
        DataSourcesCollector $dataSourcesCollector,
    ): ItemWithMetadata {
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

        return new ItemWithMetadata($key, $value, $usedTableDataVersions);
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
}

class ItemWithMetadata {
    public readonly string $hash;

    /** @var array<string|int, array{table_name: string, version: int} $usedTableDataVersions */
    public function __construct(
        public string $key,
        public mixed $data,
        public array $usedTableDataVersions,
    ) {
        // todo: somehow sort usedTableDataVersions
        $this->hash = md5(json_encode([$data, $usedTableDataVersions]));
    }

    private const JSON_KEY = 'key';
    private const JSON_DATA = 'data';
    private const JSON_USEDTABLEDATAVERSIONS = 'usedTableDataVersions';

    public function array_encode() {
        return [
            ItemWithMetadata::JSON_KEY => $this->key,
            ItemWithMetadata::JSON_DATA => $this->data,
            ItemWithMetadata::JSON_USEDTABLEDATAVERSIONS => $this->usedTableDataVersions,
        ];
    }

    public static function array_decode(array $obj): ?ItemWithMetadata {
        if (!isset($obj[ItemWithMetadata::JSON_KEY])
            || !isset($obj[ItemWithMetadata::JSON_DATA])
            || !isset($obj[ItemWithMetadata::JSON_USEDTABLEDATAVERSIONS])) {
            return null;
        }

        return new self(
            $obj[ItemWithMetadata::JSON_KEY],
            $obj[ItemWithMetadata::JSON_DATA],
            $obj[ItemWithMetadata::JSON_USEDTABLEDATAVERSIONS]
        );
    }
}
