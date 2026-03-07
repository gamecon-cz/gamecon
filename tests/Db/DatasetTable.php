<?php

declare(strict_types=1);

namespace Gamecon\Tests\Db;

class DatasetTable
{
    private $name;
    private $columns = [];
    private $rows = [];

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function addRow(array $row)
    {
        if (! $this->columns) {
            throw new \LogicException("Columns for table '{$this->name}' not yet defined");
        }
        if (count($row) !== count($this->columns)) {
            throw new \LogicException('Values count does not match column count');
        }

        $this->rows[] = $row;
    }

    public function getColumns(): array
    {
        return $this->columns;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getRows(): array
    {
        return $this->rows;
    }

    public function setColumns(array $columns)
    {
        $this->columns = $columns;
    }
}
