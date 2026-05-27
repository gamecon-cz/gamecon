<?php

namespace Godric\DbTest;

class Dataset {

    private
        $replaceNull = true,
        $tables = [];

    function addCsv($csvString) {
        foreach (explode("\n", $csvString) as $line) {
            $line = trim($line);
            $values = array_map('trim', str_getcsv($line));
            $lastTable = $this->tables[count($this->tables) - 1] ?? null;

            if (empty($line)) {
                continue;
            } else if ($line[0] == '#') {
                // table name
                $name = substr($line, 2);
                $this->tables[] = new DatasetTable($name);
            } else if (!empty($lastTable->getColumns())) {
                // data line
                $lastTable->addRow(array_map([$this, 'replaceValues'], $values));
            } else {
                // header line
                $lastTable->setColumns($values);
            }
        }
    }

    function getTables() {
        return $this->tables;
    }

    /**
     * Do replacement of special values to non-string datatypes (ie NULL)
     */
    private function replaceValues($value) {
        if ($this->replaceNull && $value == 'NULL') return null;

        return $value;
    }

}
