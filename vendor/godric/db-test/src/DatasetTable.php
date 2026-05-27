<?php

namespace Godric\DbTest;

class DatasetTable {

    private
        $columns,
        $name,
        $rows;

    function __construct($name) {
        $this->name = $name;
    }

    function addRow($row) {
        if (!$this->columns)
            throw new \Exception("Columns for table '$this->name' not yet defined.");
        if (count($row) != count($this->columns))
            throw new \Exception('Values count doesnt match column count.');

        $this->rows[] = $row;
    }

    function getColumns() {
        return $this->columns;
    }

    function getName() {
        return $this->name;
    }

    function getRows() {
        return $this->rows;
    }

    function setColumns($columns) {
        $this->columns = $columns;
    }

}
