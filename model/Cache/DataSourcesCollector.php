<?php

declare(strict_types=1);

namespace Gamecon\Cache;

class DataSourcesCollector implements TagsCollectionInterface
{
    private array $dataSources = [];

    public function addDataSource(
        string $name,
    ): void {
        $this->dataSources[$name] = $name;
    }

    public function addDataSources(
        iterable $datasourceNames,
    ): void {
        foreach ($datasourceNames as $datasourceName) {
            $this->addDataSource($datasourceName);
        }
    }

    /**
     * @return array<string>
     */
    public function getDataSources(): array
    {
        return $this->dataSources;
    }

    /**
     * @return array<string>
     */
    public function getTags(): array
    {
        return $this->getDataSources();
    }
}
