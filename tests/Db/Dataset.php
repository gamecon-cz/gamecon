<?php

namespace Gamecon\Tests\Db;

class Dataset
{

    private bool  $replaceNull = true;
    private array $tables      = [];

    public function addCsv(string $csvString)
    {
        foreach (explode("\n", $csvString) as $line) {
            $line      = trim($line);
            $values    = array_map('trim', array_map(static fn($value) => (string)$value, str_getcsv($line)));
            $lastTable = $this->tables[count($this->tables) - 1] ?? null;

            if (empty($line)) {
                continue;
            }
            $firstValue = reset($values);
            if (str_starts_with($firstValue, '#')) {
                // table name
                $name           = trim(str_replace('#', '', $firstValue));
                $this->tables[] = new DatasetTable($name);
                continue;
            }
            if (!empty($lastTable->getColumns())) {
                // data line
                $lastTable->addRow(array_map([$this, 'replaceValues'], $values));
                continue;
            }
            // header line
            $lastTable->setColumns($values);
        }
    }

    public function getTables(): array
    {
        return $this->tables;
    }

    /**
     * Do replacement of special values to non-string datatypes (ie NULL)
     */
    private function replaceValues(string $value): ?string
    {
        if ($this->replaceNull && strtoupper($value) === 'NULL') {
            return null;
        }

        return $value;
    }

}
